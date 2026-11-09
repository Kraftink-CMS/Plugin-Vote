<?php

namespace KraftInk\Plugins\Vote\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use KraftInk\Plugins\Vote\Data\ProviderChallenge;

class ChallengePayload
{
    public static function customUrl(string $url, string $token, string $player): string
    {
        [$base, $fragment] = array_pad(explode('#', $url, 2), 2, null);
        $separator = str_contains($base, '?') ? '&' : '?';

        return $base.$separator.http_build_query(['kraftink_token' => $token, 'username' => $player]).
            ($fragment === null ? '' : '#'.$fragment);
    }

    public static function encode(ProviderChallenge $challenge, string $siteUrl): string
    {
        $url = $challenge->url;
        if (
            !filter_var($url, FILTER_VALIDATE_URL) ||
            strlen($url) > 8192 ||
            parse_url($url, PHP_URL_SCHEME) !== 'https' ||
            parse_url($url, PHP_URL_USER) ||
            parse_url($url, PHP_URL_PASS) ||
            strtolower(parse_url($url, PHP_URL_HOST) ?? '') !== strtolower(parse_url($siteUrl, PHP_URL_HOST) ?? '') ||
            strlen($challenge->token ?? '') > 4096 ||
            strlen($challenge->publicCode ?? '') > 4096
        ) {
            throw ValidationException::withMessages(['vote' => __('vote::messages.verification.destination_invalid')]);
        }

        return Crypt::encryptString(
            json_encode(
                [
                    'url' => $url,
                    'token' => $challenge->token,
                    'public_code' => $challenge->publicCode,
                ],
                JSON_THROW_ON_ERROR,
            ),
        );
    }

    public static function token(object $challenge): ?string
    {
        return self::decode($challenge)['token'];
    }

    public static function url(object $challenge): string
    {
        return self::decode($challenge)['url'];
    }

    public static function publicCode(object $challenge): ?string
    {
        return $challenge->provider_token === null ? null : (self::decode($challenge)['public_code'] ?? null);
    }

    private static function decode(object $challenge): array
    {
        return json_decode(Crypt::decryptString($challenge->provider_token), true, 16, JSON_THROW_ON_ERROR);
    }
}
