<?php

namespace App\Http\Controllers\Api;

use App\Concerns\HasAuditLogging;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupportTicketController extends Controller
{
    use HasAuditLogging;

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user) {
            abort(401);
        }

        $query = SupportTicket::query()
            ->with([
                'user:id,name,email,role,bank_id',
                'bank:id,name,code',
                'department:id,name',
                'resolver:id,name',
            ])
            ->withCount('messages')
            ->latest('updated_at');

        $viewMode = $request->query('view_mode', 'default');

        if ($viewMode === 'my_tickets' || (!$user->isAdmin() && !$user->isSuperAdmin())) {
            $query->where('user_id', $user->id);
        } elseif ($user->isAdmin() && !$user->isSuperAdmin()) {
            $bankIds = $user->accessibleBankIds() ?: $user->resolvedBankIds();
            $query->where(function ($q) use ($user, $bankIds) {
                $q->where('user_id', $user->id);
                if (!empty($bankIds)) {
                    $q->orWhereIn('bank_id', $bankIds);
                }
            });
        }

        // Filters
        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        if ($priority = $request->query('priority')) {
            if ($priority !== 'all') {
                $query->where('priority', $priority);
            }
        }

        if ($category = $request->query('category')) {
            if ($category !== 'all') {
                $query->where('category', $category);
            }
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Stats counts
        $baseCountQuery = SupportTicket::query();
        if ($viewMode === 'my_tickets' || (!$user->isAdmin() && !$user->isSuperAdmin())) {
            $baseCountQuery->where('user_id', $user->id);
        } elseif ($user->isAdmin() && !$user->isSuperAdmin()) {
            $bankIds = $user->accessibleBankIds() ?: $user->resolvedBankIds();
            $baseCountQuery->where(function ($q) use ($user, $bankIds) {
                $q->where('user_id', $user->id);
                if (!empty($bankIds)) {
                    $q->orWhereIn('bank_id', $bankIds);
                }
            });
        }

        $counts = [
            'total' => (clone $baseCountQuery)->count(),
            'open' => (clone $baseCountQuery)->where('status', 'open')->count(),
            'in_progress' => (clone $baseCountQuery)->where('status', 'in_progress')->count(),
            'resolved' => (clone $baseCountQuery)->where('status', 'resolved')->count(),
            'closed' => (clone $baseCountQuery)->where('status', 'closed')->count(),
        ];

        $perPage = min((int) $request->query('per_page', 15), 100);
        $tickets = $query->paginate($perPage);

        return response()->json([
            'data' => $tickets->items(),
            'current_page' => $tickets->currentPage(),
            'last_page' => $tickets->lastPage(),
            'per_page' => $tickets->perPage(),
            'total' => $tickets->total(),
            'counts' => $counts,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user) {
            abort(401);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:64'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'description' => ['required', 'string', 'min:10'],
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $bankId = $validated['bank_id'] ?? ($user->bank_id ?? ($user->accessibleBankIds()[0] ?? null));
        $deptId = $validated['department_id'] ?? $user->resolvedDepartmentId();

        $ticket = DB::transaction(function () use ($user, $validated, $bankId, $deptId) {
            $ticket = SupportTicket::create([
                'user_id' => $user->id,
                'bank_id' => $bankId,
                'department_id' => $deptId,
                'subject' => $validated['subject'],
                'category' => $validated['category'],
                'priority' => $validated['priority'] ?? 'medium',
                'status' => 'open',
                'description' => $validated['description'],
            ]);

            SupportTicketMessage::create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'message' => $validated['description'],
                'is_staff_reply' => false,
            ]);

            return $ticket;
        });

        $this->audit('create_support_ticket', 'Support', [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'category' => $ticket->category,
        ]);

        $ticket->load([
            'user:id,name,email,role,bank_id',
            'bank:id,name,code',
            'department:id,name',
            'messages.user:id,name,email,role',
        ]);

        return response()->json($ticket, 201);
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorizeTicketAccess($ticket);

        $ticket->load([
            'user:id,name,email,role,bank_id',
            'bank:id,name,code',
            'department:id,name',
            'resolver:id,name',
            'messages.user:id,name,email,role',
        ]);

        return response()->json($ticket);
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $this->authorizeTicketAccess($ticket);

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2'],
        ]);

        $isStaff = ($user->isAdmin() || $user->isSuperAdmin()) && ((int) $user->id !== (int) $ticket->user_id);

        $reply = DB::transaction(function () use ($ticket, $user, $validated, $isStaff) {
            $msg = SupportTicketMessage::create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'message' => $validated['message'],
                'is_staff_reply' => $isStaff,
            ]);

            if ($isStaff && $ticket->status === 'open') {
                $ticket->update(['status' => 'in_progress']);
            } elseif (!$isStaff && in_array($ticket->status, ['resolved', 'closed'], true)) {
                $ticket->update([
                    'status' => 'in_progress',
                    'resolved_at' => null,
                    'resolved_by_user_id' => null,
                ]);
            } else {
                $ticket->touch();
            }

            return $msg;
        });

        $reply->load('user:id,name,email,role');

        return response()->json($reply, 201);
    }

    public function updateStatus(Request $request, SupportTicket $ticket): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $this->authorizeTicketAccess($ticket);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:open,in_progress,resolved,closed'],
            'note' => ['nullable', 'string'],
        ]);

        $newStatus = $validated['status'];

        // Regular users can only close their own tickets or reopen if resolved
        if (!$user->isAdmin() && !$user->isSuperAdmin() && (int) $ticket->user_id === (int) $user->id) {
            if (!in_array($newStatus, ['closed', 'in_progress'], true)) {
                abort(403, 'You are only allowed to close your ticket or request reopening.');
            }
        }

        DB::transaction(function () use ($ticket, $user, $validated, $newStatus) {
            $updates = ['status' => $newStatus];

            if ($newStatus === 'resolved') {
                $updates['resolved_at'] = now();
                $updates['resolved_by_user_id'] = $user->id;
            } elseif ($ticket->status === 'resolved' && $newStatus !== 'resolved') {
                $updates['resolved_at'] = null;
                $updates['resolved_by_user_id'] = null;
            }

            $ticket->update($updates);

            if (!empty($validated['note'])) {
                SupportTicketMessage::create([
                    'support_ticket_id' => $ticket->id,
                    'user_id' => $user->id,
                    'message' => "Status changed to " . ucfirst(str_replace('_', ' ', $newStatus)) . ": " . $validated['note'],
                    'is_staff_reply' => $user->isAdmin() || $user->isSuperAdmin(),
                ]);
            }
        });

        $this->audit('update_ticket_status', 'Support', [
            'ticket_id' => $ticket->id,
            'status' => $newStatus,
        ]);

        $ticket->load([
            'user:id,name,email,role,bank_id',
            'bank:id,name,code',
            'department:id,name',
            'resolver:id,name',
            'messages.user:id,name,email,role',
        ]);

        return response()->json($ticket);
    }

    protected function authorizeTicketAccess(SupportTicket $ticket): void
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user) {
            abort(401);
        }

        if ($user->isSuperAdmin()) {
            return;
        }

        if ((int) $ticket->user_id === (int) $user->id) {
            return;
        }

        if ($user->isAdmin()) {
            $bankIds = $user->accessibleBankIds() ?: $user->resolvedBankIds();
            if (empty($ticket->bank_id) || in_array((int) $ticket->bank_id, $bankIds, true)) {
                return;
            }
        }

        abort(403, 'You are not authorized to view or modify this support ticket.');
    }
}
