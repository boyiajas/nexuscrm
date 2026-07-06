<?php

namespace App\Http\Controllers\Api;

use App\Concerns\EnforcesMetaPermissionHealth;
use App\Contracts\WhatsAppServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Models\ChatMessage;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    use EnforcesMetaPermissionHealth;

    public function __construct(private WhatsAppServiceInterface $whatsApp)
    {
    }

    public function index(Request $request)
    {
        $user = $this->authorizeView();

        $query = ChatSession::with('client', 'agent')
            ->orderByDesc('updated_at');

        if (!$user->canAccessAllBanks() && $user->resolvedBankId()) {
            $query->where('bank_id', $user->resolvedBankId());
        }

        if ($user->isPortfolioScoped()) {
            $query->whereHas('client', function ($q) use ($user) {
                $q->where('assigned_to_id', $user->id);
            });
        }

        if ($status = $request->get('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        return $query->paginate(20);
    }

    public function show(ChatSession $session)
    {
        $user = $this->authorizeView();
        $this->authorizeSessionScope($user, $session);

        $session->load(['client', 'agent', 'messages' => function ($q) {
            $q->orderBy('created_at');
        }]);

        // Keep review-only roles read-only by skipping unread-count mutation.
        if (!$user->isReadOnlyRole()) {
            $session->update(['unread_count' => 0]);
        }

        return $session;
    }

    public function storeMessage(Request $request, ChatSession $session)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'content'     => ['required', 'string'],
            'is_template' => ['sometimes', 'boolean'],
        ]);

        if ($session->platform === 'whatsapp') {
            $this->enforceMetaPermissionHealthForProduction('Live chat WhatsApp sending');
        }

        $message = $session->messages()->create([
            'sender'      => 'agent', // future: use 'system' or 'user' as needed
            'content'     => $data['content'],
            'is_template' => $data['is_template'] ?? false,
            'sent_at'     => now(),
        ]);

        $session->update([
            'last_message' => $data['content'],
            'updated_at'   => now(),
        ]);

        // Try sending outbound WhatsApp for live chat sessions
        if ($session->platform === 'whatsapp') {
            $this->sendWhatsappReply($session, $data['content']);
        }

        return response()->json($message, 201);
    }

    // Reserved for future manual webhook entry points if needed:
    public function receiveFromClient(Request $request)
    {
        // TODO: handle inbound webhook payloads, find/create ChatSession,
        // create ChatMessage with sender='user', increment unread_count, etc.
    }

    /**
     * Ensure a chat session exists for a client and return it with messages.
     */
    public function sessionForClient(Request $request)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'platform'  => ['sometimes', 'string', 'max:50'],
        ]);

        $client = Client::findOrFail($data['client_id']);
        $this->authorizeClientScope($request->user(), $client);
        $platform = $data['platform'] ?? 'whatsapp';

        $session = ChatSession::firstOrCreate(
            [
                'client_id' => $client->id,
                'platform'  => $platform,
            ],
            [
                'client_name' => $client->name,
                'bank_id' => $client->bank_id,
                'status'      => 'active',
                'agent_id'    => Auth::id(),
                'unread_count'=> 0,
            ]
        );

        // Load messages ordered and reset unread count when fetched
        $session->load(['client', 'agent', 'messages' => function ($q) {
            $q->orderBy('created_at');
        }]);

        $session->update(['unread_count' => 0]);

        return response()->json($session);
    }

    protected function authorizeView()
    {
        $user = Auth::user();

        if (!$user || !$user->canViewOperationalData()) {
            abort(403, 'You are not allowed to access live chat.');
        }

        return $user;
    }

    protected function authorizeManage(): void
    {
        $user = Auth::user();

        if (!$user || !$user->canManageOperationalData()) {
            abort(403, 'You are not allowed to manage live chat.');
        }
    }

    protected function authorizeSessionScope($user, ChatSession $session): void
    {
        if (!$user->canAccessAllBanks() && $user->resolvedBankId() && (int) $session->bank_id !== $user->resolvedBankId()) {
            abort(403, 'You are not allowed to access this chat session.');
        }

        if ($user->isPortfolioScoped() && $session->client && (int) $session->client->assigned_to_id !== (int) $user->id) {
            abort(403, 'You are not allowed to access this chat session.');
        }
    }

    protected function authorizeClientScope($user, Client $client): void
    {
        if (!$user->canAccessAllBanks() && $user->resolvedBankId() && (int) $client->bank_id !== $user->resolvedBankId()) {
            abort(403, 'You are not allowed to access this client chat.');
        }

        if ($user->isPortfolioScoped() && (int) $client->assigned_to_id !== (int) $user->id) {
            abort(403, 'You are not allowed to access this client chat.');
        }
    }

    protected function sendWhatsappReply(ChatSession $session, string $body): void
    {
        $to = $session->client?->phone ?: $session->phone;
        if (!$to) {
            Log::warning('Chat WhatsApp reply skipped: no phone on session', ['session_id' => $session->id]);
            return;
        }

        try {
            Log::info('Chat WhatsApp reply attempt', [
                'session_id' => $session->id,
                'client_id' => $session->client_id,
                'to' => $to,
                'body_length' => mb_strlen($body),
            ]);
            $this->whatsApp->sendPlainWhatsapp($to, $body);
        } catch (\Throwable $e) {
            Log::error('Failed to send WhatsApp chat reply', [
                'session_id' => $session->id,
                'to'         => $to,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
