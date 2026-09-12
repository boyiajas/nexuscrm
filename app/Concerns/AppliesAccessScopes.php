<?php

namespace App\Concerns;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\User;

trait AppliesAccessScopes
{
    protected function scopeQueryToUserBanks($query, ?User $user, string $column = 'bank_id'): void
    {
        if (!$user) {
            $query->whereRaw('1 = 0');
            return;
        }

        if ($user->isSuperAdmin()) {
            return;
        }

        $bankIds = $user->accessibleBankIds();
        if (empty($bankIds)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn($column, $bankIds);
    }

    protected function scopeQueryToUserDepartments($query, ?User $user, string $relation = 'departments'): void
    {
        if (!$user) {
            $query->whereRaw('1 = 0');
            return;
        }

        if ($user->isSuperAdmin()) {
            return;
        }

        $departmentIds = $user->resolvedDepartmentIds();
        if (empty($departmentIds)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereHas($relation, function ($departmentQuery) use ($departmentIds) {
            $departmentQuery->whereIn('departments.id', $departmentIds);
        });
    }

    protected function scopeClientQueryToUser($query, ?User $user, string $bankColumn = 'clients.bank_id', string $departmentRelation = 'departments'): void
    {
        $this->scopeQueryToUserBanks($query, $user, $bankColumn);
        $this->scopeQueryToUserDepartments($query, $user, $departmentRelation);

        if ($user && !$user->isSuperAdmin() && $user->isPortfolioScoped()) {
            $query->where('clients.assigned_to_id', $user->id);
        }
    }

    protected function scopeCampaignQueryToUser($query, ?User $user, string $bankColumn = 'campaigns.bank_id'): void
    {
        $this->scopeQueryToUserBanks($query, $user, $bankColumn);
        $this->scopeQueryToUserDepartments($query, $user);

        if ($user && !$user->isSuperAdmin() && $user->isPortfolioScoped()) {
            $query->whereHas('clients', function ($clientQuery) use ($user) {
                $clientQuery->where('clients.assigned_to_id', $user->id);
            });
        }
    }

    protected function authorizeClientScopeForUser(?User $user, Client $client, string $action = 'access'): void
    {
        if (!$user) {
            abort(401);
        }

        if ($user->isSuperAdmin()) {
            return;
        }

        if (!$user->canAccessBankId($client->bank_id)) {
            abort(403, "You are not allowed to {$action} this client.");
        }

        $client->loadMissing('departments:id');
        if (!$user->canAccessAnyDepartment($client->departments->pluck('id')->all())) {
            abort(403, "You are not allowed to {$action} this client.");
        }

        if ($user->isPortfolioScoped() && (int) $client->assigned_to_id !== (int) $user->id) {
            abort(403, "You are not allowed to {$action} this client.");
        }
    }

    protected function authorizeCampaignScopeForUser(?User $user, Campaign $campaign, string $action = 'access'): void
    {
        if (!$user) {
            abort(401);
        }

        if ($user->isSuperAdmin()) {
            return;
        }

        if (!$user->canAccessBankId($campaign->bank_id)) {
            abort(403, "You are not allowed to {$action} this campaign.");
        }

        $campaign->loadMissing('departments:id');
        if (!$user->canAccessAnyDepartment($campaign->departments->pluck('id')->all())) {
            abort(403, "You are not allowed to {$action} this campaign.");
        }

        if ($user->isPortfolioScoped()) {
            $hasAssignedClient = $campaign->clients()
                ->where('clients.assigned_to_id', $user->id)
                ->exists();

            if (!$hasAssignedClient) {
                abort(403, "You are not allowed to {$action} this campaign.");
            }
        }
    }

    protected function resolveAllowedDepartmentIds(?User $user, array $requestedDepartmentIds): array
    {
        $departmentIds = collect($requestedDepartmentIds)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($departmentIds)) {
            return [];
        }

        if ($user?->isSuperAdmin()) {
            return $departmentIds;
        }

        $invalid = array_diff($departmentIds, $user?->resolvedDepartmentIds() ?? []);
        if (!empty($invalid)) {
            abort(403, 'You are not allowed to access one or more selected departments.');
        }

        return $departmentIds;
    }
}
