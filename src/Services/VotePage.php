<?php

namespace KraftInk\Plugins\Vote\Services;

use App\Kraft\Auth\PermissionAccess;
use App\Models\Accounts\User;
use KraftInk\Plugins\Vote\Models\Rewards\RewardClaim;
use KraftInk\Plugins\Vote\Models\Voting\Site;
use KraftInk\Plugins\Vote\Models\Voting\Vote;
use KraftInk\Plugins\Vote\Services\Rewards\RewardEngine;

class VotePage
{
    public function available(?User $user): bool
    {
        if (!app(PermissionAccess::class)->allows($user, 'vote.view')) {
            return false;
        }

        return Site::query()->available()->exists() ||
            Vote::query()->scores()->exists() ||
            ($user && RewardClaim::query()->where('user_id', $user->id)->exists()) ||
            app(RewardEngine::class)->hasProgress($user);
    }
}
