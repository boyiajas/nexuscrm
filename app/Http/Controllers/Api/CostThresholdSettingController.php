<?php

namespace App\Http\Controllers\Api;

use App\Concerns\HasAuditLogging;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\CostThresholdSetting;
use App\Services\CostThresholdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CostThresholdSettingController extends Controller
{
    use HasAuditLogging;

    public function __construct(
        protected CostThresholdService $thresholdService
    ) {
    }

    /**
     * List all cost threshold settings accessible to the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeAccess($user);

        $rules = CostThresholdSetting::accessibleBy($user)
            ->orderByDesc('id')
            ->get();

        $accessibleBanks = ($user->canAccessAllBanks() || $user->isSuperAdmin())
            ? Bank::where('status', 'Active')->orderBy('name')->get(['id', 'name', 'code'])
            : Bank::whereIn('id', $user->accessibleBankIds() ?? [])->where('status', 'Active')->orderBy('name')->get(['id', 'name', 'code']);

        $ruleMetrics = $rules->map(fn (CostThresholdSetting $rule) => $this->thresholdService->getRuleMetrics($rule));

        $totalBudget = $rules->where('is_active', true)->sum('threshold_amount');
        $totalSpend = $ruleMetrics->where('is_active', true)->sum('current_month_spend');

        return response()->json([
            'rules' => $ruleMetrics,
            'summary' => [
                'total_rules' => $rules->count(),
                'active_rules' => $rules->where('is_active', true)->count(),
                'total_budget' => round((float) $totalBudget, 2),
                'total_spend' => round((float) $totalSpend, 2),
                'overall_percentage' => $totalBudget > 0 ? round(($totalSpend / $totalBudget) * 100, 1) : 0.0,
            ],
            'accessible_banks' => $accessibleBanks,
            'can_manage_all_banks' => $user->canAccessAllBanks() || $user->isSuperAdmin(),
        ]);
    }

    /**
     * Store a new cost threshold setting.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeAccess($user);

        $data = $request->validate([
            'name'                   => ['required', 'string', 'max:255'],
            'threshold_amount'       => ['required', 'numeric', 'min:1'],
            'bank_ids'               => ['required', 'array', 'min:1'],
            'bank_ids.*'             => ['integer', 'exists:banks,id'],
            'notification_emails'    => ['required', 'array', 'min:1'],
            'notification_emails.*'  => ['required', 'email'],
            'threshold_percentages'  => ['required', 'array', 'min:1'],
            'threshold_percentages.*'=> ['integer', 'min:1', 'max:500'],
            'is_active'              => ['sometimes', 'boolean'],
            'description'            => ['nullable', 'string', 'max:1000'],
        ]);

        // Enforce bank access restriction
        $this->assertUserCanAccessBanks($user, $data['bank_ids']);

        // Clean & sort percentages
        $percentages = array_values(array_unique(array_map('intval', $data['threshold_percentages'])));
        sort($percentages);

        $rule = CostThresholdSetting::create([
            'name'                  => trim($data['name']),
            'threshold_amount'      => (float) $data['threshold_amount'],
            'bank_ids'              => array_values(array_unique(array_map('intval', $data['bank_ids']))),
            'notification_emails'   => array_values(array_unique(array_map('trim', $data['notification_emails']))),
            'threshold_percentages' => $percentages,
            'is_active'             => $data['is_active'] ?? true,
            'created_by_user_id'    => $user->id,
            'description'           => $data['description'] ?? null,
        ]);

        $this->audit(
            action: "Created cost threshold rule: {$rule->name} (\${$rule->threshold_amount})",
            module: 'Settings',
            meta: [
                'rule_id' => $rule->id,
                'threshold_amount' => $rule->threshold_amount,
                'bank_ids' => $rule->bank_ids,
            ]
        );

        return response()->json([
            'message' => 'Threshold notification setting created successfully.',
            'rule' => $this->thresholdService->getRuleMetrics($rule),
        ], 201);
    }

    /**
     * Show a single threshold setting with live metrics.
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeAccess($user);

        $rule = CostThresholdSetting::findOrFail($id);

        if (!$rule->isAccessibleBy($user)) {
            return response()->json(['message' => 'Unauthorized access to this threshold rule.'], 403);
        }

        return response()->json([
            'rule' => $this->thresholdService->getRuleMetrics($rule),
        ]);
    }

    /**
     * Update an existing cost threshold setting.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeAccess($user);

        $rule = CostThresholdSetting::findOrFail($id);

        if (!$rule->isAccessibleBy($user)) {
            return response()->json(['message' => 'Unauthorized access to this threshold rule.'], 403);
        }

        $data = $request->validate([
            'name'                   => ['sometimes', 'required', 'string', 'max:255'],
            'threshold_amount'       => ['sometimes', 'required', 'numeric', 'min:1'],
            'bank_ids'               => ['sometimes', 'required', 'array', 'min:1'],
            'bank_ids.*'             => ['integer', 'exists:banks,id'],
            'notification_emails'    => ['sometimes', 'required', 'array', 'min:1'],
            'notification_emails.*'  => ['required', 'email'],
            'threshold_percentages'  => ['sometimes', 'required', 'array', 'min:1'],
            'threshold_percentages.*'=> ['integer', 'min:1', 'max:500'],
            'is_active'              => ['sometimes', 'boolean'],
            'description'            => ['nullable', 'string', 'max:1000'],
        ]);

        if (isset($data['bank_ids'])) {
            $this->assertUserCanAccessBanks($user, $data['bank_ids']);
            $rule->bank_ids = array_values(array_unique(array_map('intval', $data['bank_ids'])));
        }

        if (isset($data['name'])) {
            $rule->name = trim($data['name']);
        }
        if (isset($data['threshold_amount'])) {
            $rule->threshold_amount = (float) $data['threshold_amount'];
        }
        if (isset($data['notification_emails'])) {
            $rule->notification_emails = array_values(array_unique(array_map('trim', $data['notification_emails'])));
        }
        if (isset($data['threshold_percentages'])) {
            $percentages = array_values(array_unique(array_map('intval', $data['threshold_percentages'])));
            sort($percentages);
            $rule->threshold_percentages = $percentages;
        }
        if (isset($data['is_active'])) {
            $rule->is_active = (bool) $data['is_active'];
        }
        if (array_key_exists('description', $data)) {
            $rule->description = $data['description'];
        }

        $rule->save();

        $this->audit(
            action: "Updated cost threshold rule: {$rule->name}",
            module: 'Settings',
            meta: [
                'rule_id' => $rule->id,
                'threshold_amount' => $rule->threshold_amount,
                'bank_ids' => $rule->bank_ids,
            ]
        );

        return response()->json([
            'message' => 'Threshold notification setting updated successfully.',
            'rule' => $this->thresholdService->getRuleMetrics($rule),
        ]);
    }

    /**
     * Delete a cost threshold setting.
     */
    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeAccess($user);

        $rule = CostThresholdSetting::findOrFail($id);

        if (!$rule->isAccessibleBy($user)) {
            return response()->json(['message' => 'Unauthorized access to this threshold rule.'], 403);
        }

        $ruleName = $rule->name;
        $rule->delete();

        $this->audit(
            action: "Deleted cost threshold rule: {$ruleName}",
            module: 'Settings',
            meta: ['rule_id' => $id]
        );

        return response()->json([
            'message' => 'Threshold notification setting deleted successfully.',
        ]);
    }

    /**
     * Send a test notification email for a threshold rule.
     */
    public function testAlert(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeAccess($user);

        $rule = CostThresholdSetting::findOrFail($id);

        if (!$rule->isAccessibleBy($user)) {
            return response()->json(['message' => 'Unauthorized access to this threshold rule.'], 403);
        }

        $targetEmail = $request->input('email');
        if ($targetEmail && !filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'Invalid email address provided.'], 422);
        }

        $result = $this->thresholdService->sendTestNotification($rule, $targetEmail);

        return response()->json([
            'message' => 'Test alert email dispatched successfully.',
            'recipients' => $result['recipients'],
        ]);
    }

    /**
     * Evaluate rule against current spend immediately.
     */
    public function evaluate(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeAccess($user);

        $rule = CostThresholdSetting::findOrFail($id);

        if (!$rule->isAccessibleBy($user)) {
            return response()->json(['message' => 'Unauthorized access to this threshold rule.'], 403);
        }

        $force = $request->boolean('force', false);
        $result = $this->thresholdService->evaluateRule($rule, $force);

        return response()->json([
            'message' => !empty($result['alerts_sent'])
                ? 'Threshold evaluated: Alerts dispatched for tiers ' . implode('%, ', $result['alerts_sent']) . '%'
                : 'Threshold evaluated: No new alert tiers reached.',
            'evaluation' => $result,
            'rule' => $this->thresholdService->getRuleMetrics($rule->fresh()),
        ]);
    }

    /**
     * Authorize user access to threshold settings.
     */
    protected function authorizeAccess($user): void
    {
        if (!$user) {
            abort(401);
        }

        if (
            !$user->canManageSystemSettings() &&
            !$user->canAccessAnySettings() &&
            !$user->isSuperAdmin()
        ) {
            abort(403, 'You are not authorized to access threshold settings.');
        }
    }

    /**
     * Validate that the user is permitted to configure thresholds for all specified bank IDs.
     */
    protected function assertUserCanAccessBanks($user, array $bankIds): void
    {
        if ($user->canAccessAllBanks() || $user->isSuperAdmin()) {
            return;
        }

        $accessibleBankIds = $user->accessibleBankIds() ?? [];
        $unauthorizedBankIds = array_diff(array_map('intval', $bankIds), $accessibleBankIds);

        if (!empty($unauthorizedBankIds)) {
            abort(403, 'You are not authorized to configure threshold rules for banks outside your assigned portfolio.');
        }
    }
}
