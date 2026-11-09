<?php

namespace KraftInk\Plugins\Vote\Services\Rewards;

use App\Kraft\Auth\PermissionAccess;
use App\Kraft\Auth\RoleHierarchy;
use App\Models\Accounts\Role;
use App\Models\Accounts\User;
use Illuminate\Validation\ValidationException;
use KraftInk\Plugins\Vote\Models\Rewards\Reward;

class RewardPolicy
{
    public function presentationOnly(?Reward $reward, array $data): bool
    {
        if (!$reward) {
            return false;
        }
        foreach ($data as $field => $value) {
            if (in_array($field, ['titles', 'updated_at'], true)) {
                continue;
            }
            $existing = $reward->$field;
            if (in_array($field, ['commands', 'role_permissions'], true)) {
                $existing = is_array($existing) ? $existing : json_decode($existing ?: '[]', true);
                $value = is_array($value) ? $value : json_decode($value ?: '[]', true);
                if ($existing !== $value) {
                    return false;
                }
            } elseif ((string) ($existing ?? '') !== (string) ($value ?? '')) {
                return false;
            }
        }

        return true;
    }

    public function matchesRolePermissions(Role $role, array $permissions): bool
    {
        $memberPermissions = app(PermissionAccess::class)->permissions(new User(), collect());

        return !array_diff($role->effectivePermissions(), $permissions, $memberPermissions);
    }

    public function assert(User $actor, ?object $reward, bool $checkWallet = true): void
    {
        if (!$reward) {
            return;
        }
        if ($checkWallet && ($reward->wallet_minor ?? 0) > 0 && !$actor->hasPermission('wallet.manage')) {
            throw ValidationException::withMessages(['wallet_minor' => __('vote::rewards.wallet_permission')]);
        }
        if ($reward->role_id) {
            $role = Role::query()->findOrFail($reward->role_id);
            if ($role->slug === 'owner' || in_array('*', $role->effectivePermissions(), true)) {
                throw ValidationException::withMessages(['role_id' => __('vote::rewards.role_forbidden')]);
            }
            app(RoleHierarchy::class)->assertRole($actor, $role);
        }
    }

    public function assertSite(User $actor, ?int $id): void
    {
        $rewards = Reward::query()
            ->where(
                fn ($query) => $query->whereNull('site_id')->when($id, fn ($query) => $query->orWhere('site_id', $id)),
            )
            ->get();
        foreach ($rewards as $reward) {
            $this->assert($actor, $reward);
        }
    }
}
