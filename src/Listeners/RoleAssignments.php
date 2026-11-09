<?php

namespace KraftInk\Plugins\Vote\Listeners;

use App\Events\CollectingRoleAssignments;
use App\Events\ResolvingUserRoles;
use App\Models\Accounts\Role;
use Illuminate\Database\Eloquent\Builder;
use KraftInk\Plugins\Vote\Models\Rewards\RoleGrant;
use KraftInk\Plugins\Vote\Services\Rewards\RewardPolicy;

class RoleAssignments
{
    public function collect(CollectingRoleAssignments $event): void
    {
        $roles = Role::query()
            ->whereIn('id', $event->roleIds)
            ->get()
            ->filter(fn ($role) => $role->slug !== 'owner' && !in_array('*', $role->effectivePermissions(), true))
            ->keyBy('id');
        if ($roles->isEmpty()) {
            return;
        }
        $query = $this->query()->whereIn('vote__role_grants.role_id', $roles->keys());
        $connection = $query->getConnection();
        $postgres = $connection->getDriverName() === 'pgsql';
        $column = $query->getGrammar()->wrap('vote__role_grants.permissions');
        $permissions = 'CAST('.$column.' AS '.($postgres ? 'TEXT' : 'CHAR').')';
        $snapshot = $postgres ? $connection->raw($permissions.' AS permissions') : 'vote__role_grants.permissions';
        $snapshots = (clone $query)
            ->select('vote__role_grants.role_id', $snapshot)
            ->distinct()
            ->get()
            ->filter(
                fn ($grant) => app(RewardPolicy::class)->matchesRolePermissions(
                    $roles[$grant->role_id],
                    $grant->permissions ?? [],
                ),
            );
        if ($snapshots->isEmpty()) {
            return;
        }
        $query->where(function ($query) use ($snapshots, $permissions) {
            foreach ($snapshots as $grant) {
                /** @var RoleGrant $grant */
                $query->orWhere(
                    fn ($query) => $query
                        ->where('vote__role_grants.role_id', $grant->role_id)
                        ->whereRaw($permissions.' = ?', [$grant->getRawOriginal('permissions')]),
                );
            }
        });
        $event->add($query->select('vote__role_grants.user_id', 'vote__role_grants.role_id')->toBase());
    }

    public function roles(ResolvingUserRoles $event): void
    {
        $grants = $this->query()
            ->where('vote__role_grants.user_id', $event->user->id)
            ->select('vote__role_grants.*')
            ->get();
        $roles = Role::query()
            ->whereIn('id', $grants->pluck('role_id'))
            ->get()
            ->filter(function ($role) use ($grants) {
                return $role->slug !== 'owner' &&
                    !in_array('*', $role->effectivePermissions(), true) &&
                    $grants
                        ->where('role_id', $role->id)
                        ->contains(
                            fn ($grant) => app(RewardPolicy::class)->matchesRolePermissions(
                                $role,
                                $grant->permissions ?? [],
                            ),
                        );
            });
        $event->roles = $event->roles->merge($roles);
    }

    private function query(): Builder
    {
        return RoleGrant::query()->active();
    }
}
