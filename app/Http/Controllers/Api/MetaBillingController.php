<?php

namespace App\Http\Controllers\Api;

use App\Concerns\AppliesAccessScopes;
use App\Http\Controllers\Controller;
use App\Services\MetaBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetaBillingController extends Controller
{
    use AppliesAccessScopes;

    public function __construct(
        protected MetaBillingService $billingService
    ) {
    }

    /**
     * Get Meta Billing and Usage Overview.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        if ($user && !$user->canAccessAllBanks() && !$user->isSuperAdmin()) {
            $accessibleBankIds = $user->accessibleBankIds() ?? [];
            $requestedBankId = $request->query('bank_id');
            if ($requestedBankId && $requestedBankId !== 'all' && !in_array((int) $requestedBankId, $accessibleBankIds, true)) {
                return response()->json(['message' => 'Unauthorized access to bank.'], 403);
            }
        }

        $params = [
            'date_range' => $request->query('date_range', 'last_30_days'),
            'bank_id' => $request->query('bank_id'),
        ];

        $forceRefresh = $request->boolean('refresh', false);

        $data = $this->billingService->getBillingOverview($params, $forceRefresh);

        return response()->json($data);
    }

    /**
     * Force live synchronization from Meta Graph API.
     */
    public function sync(Request $request): JsonResponse
    {
        $params = [
            'date_range' => $request->input('date_range', 'last_30_days'),
            'bank_id' => $request->input('bank_id'),
        ];

        $data = $this->billingService->getBillingOverview($params, true);

        return response()->json([
            'message' => 'Meta billing and telemetry successfully synchronized.',
            'data' => $data,
        ]);
    }

    /**
     * Update Meta Ad Account ID.
     */
    public function updateAdAccount(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user?->isSuperAdmin() && !$user?->hasPermission('manage_system_settings') && !$user?->hasPermission('settings_meta_whatsapp')) {
            return response()->json(['message' => 'Unauthorized to update Meta Ad Account settings.'], 403);
        }

        $validated = $request->validate([
            'ad_account_id' => ['nullable', 'string', 'max:100'],
        ]);

        $this->billingService->updateAdAccountId($validated['ad_account_id'] ?? null);

        return response()->json([
            'message' => 'Meta Ad Account ID updated successfully.',
            'ad_account_id' => $validated['ad_account_id'] ?? null,
        ]);
    }
}
