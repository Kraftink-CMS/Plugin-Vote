<?php

namespace KraftInk\Plugins\Vote\Services;

use Carbon\CarbonImmutable;

class Cooldown
{
    public function next(object $site, ?string $last): ?CarbonImmutable
    {
        if (!$last) {
            return null;
        }
        $last = CarbonImmutable::parse($last, 'UTC');
        if (!$site->reset_time) {
            return $last->addMinutes((int) $site->cooldown_minutes);
        }
        [$hour, $minute] = array_map('intval', explode(':', $site->reset_time));
        $local = $last->setTimezone($site->reset_timezone);
        $reset = $local->startOfDay()->setTime($hour, $minute);

        return ($reset->greaterThan($local) ? $reset : $reset->addDay())->utc();
    }
}
