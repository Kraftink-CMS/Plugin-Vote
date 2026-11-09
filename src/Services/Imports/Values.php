<?php

namespace KraftInk\Plugins\Vote\Services\Imports;

use App\Kraft\Migration\ImportContext;

final class Values
{
    public static function setting(ImportContext $context, string $key, mixed $default): mixed
    {
        foreach ($context->sourceRows('settings') ?? [] as $setting) {
            if (($setting['name'] ?? ($setting['key'] ?? '')) === $key) {
                $value = $setting['value'] ?? $default;
                $decoded = is_string($value) ? json_decode($value, true) : $value;

                return $decoded ?? $value;
            }
        }

        return $default;
    }

    public static function array(mixed $value): array
    {
        $value = is_string($value) ? json_decode($value, true) : $value;

        return is_array($value) ? array_values($value) : [];
    }

    public static function commands(mixed $value, array $replacements = []): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : preg_split('/\\R/', $value);
        }
        $commands = [];
        foreach (is_array($value) ? $value : [] as $command) {
            if (is_string($command)) {
                $commands[] = strtr($command, ['{PLAYER}' => '{player}', '{USERNAME}' => '{player}'] + $replacements);
            }
        }

        return $commands;
    }
}
