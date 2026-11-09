<?php

namespace KraftInk\Plugins\Vote\Services;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VerificationLock
{
    public const string PREFIX = 'vote:verification:';

    public function acquire(string $identity): Lock
    {
        $lock = Cache::store('database')->lock(self::PREFIX.$identity, 60);
        if (!$lock->get()) {
            throw new HttpException(409, __('vote::messages.verification.verification_busy'));
        }

        return $lock;
    }
}
