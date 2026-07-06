<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class BankController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Bank::query()->orderBy('name');

        if ($user && !$user->canAccessAllBanks() && $user->resolvedBankId()) {
            $query->where('id', $user->resolvedBankId());
        }

        if ($search = trim((string) $request->get('search', ''))) {
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($status = trim((string) $request->get('status', ''))) {
            if (strcasecmp($status, 'all') !== 0) {
                $query->where('status', $status);
            }
        }

        $perPage = (int) $request->get('per_page', 20);
        $perPage = max(1, min($perPage, 200));

        return response()->json(
            $query->paginate($perPage, ['id', 'name', 'code', 'status', 'created_at', 'updated_at'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManageBanks($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:banks,name'],
            'code' => ['required', 'string', 'max:255', 'unique:banks,code'],
            'status' => ['required', 'string', 'in:Active,Inactive'],
        ]);

        $bank = Bank::create($data);

        return response()->json($bank, 201);
    }

    public function update(Request $request, Bank $bank): JsonResponse
    {
        $this->authorizeManageBanks($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:banks,name,' . $bank->id],
            'code' => ['required', 'string', 'max:255', 'unique:banks,code,' . $bank->id],
            'status' => ['required', 'string', 'in:Active,Inactive'],
        ]);

        $bank->update($data);

        return response()->json($bank->fresh());
    }

    public function destroy(Request $request, Bank $bank): JsonResponse
    {
        $this->authorizeManageBanks($request);

        $usage = $this->usageSummary($bank->id);
        if (!empty($usage)) {
            return response()->json([
                'message' => 'This bank cannot be deleted because it is already referenced in the system.',
                'usage' => $usage,
            ], 422);
        }

        $bank->delete();

        return response()->json(['message' => 'Bank deleted successfully.']);
    }

    protected function authorizeManageBanks(Request $request): void
    {
        $user = $request->user();
        if (!$user || !$user->canManageUsersAndDepartments()) {
            abort(403, 'You are not allowed to manage banks.');
        }
    }

    protected function usageSummary(int $bankId): array
    {
        $tables = [
            'users' => 'Users',
            'clients' => 'Clients',
            'campaigns' => 'Campaigns',
            'chat_sessions' => 'Chat Sessions',
            'audit_logs' => 'Audit Logs',
            'import_uploads' => 'Import Uploads',
            'export_requests' => 'Export Requests',
            'security_incidents' => 'Security Incidents',
            'data_subject_requests' => 'Data Subject Requests',
            'complaint_cases' => 'Complaint Cases',
            'information_officers' => 'Information Officers',
            'retention_policies' => 'Retention Policies',
            'retention_actions' => 'Retention Actions',
            'bank_transfer_profiles' => 'Bank Transfer Profiles',
        ];

        $usage = [];
        foreach ($tables as $table => $label) {
            if (!DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            $count = DB::table($table)->where('bank_id', $bankId)->count();
            if ($count > 0) {
                $usage[] = [
                    'table' => $table,
                    'label' => $label,
                    'count' => $count,
                ];
            }
        }

        return $usage;
    }
}
