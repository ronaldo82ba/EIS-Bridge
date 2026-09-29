<?php

namespace App\Support;

final class UrlSecurity
{
    public static function isAllowedPublicHttpsUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return false;
        }

        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            return false;
        }

        if (str_contains($host, ':')) {
            $host = trim($host, '[]');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::isPublicIp($host);
        }

        $resolved = gethostbynamel($host);
        if ($resolved !== false) {
            foreach ($resolved as $ip) {
                if (! self::isPublicIp($ip)) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
