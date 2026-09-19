<?php

declare(strict_types=1);

namespace VoidLabs\Metadata;

final class MetadataPolicy
{
    /** @param list<string> $excludedRoutes */
    public static function metadata(
        string $route,
        string $pageTitle,
        string $siteTitle,
        string $browserTitle = '',
        string $description = '',
        string $robots = '',
        array $excludedRoutes = [],
        string $titleSeparator = ' | ',
        string $noindexRobots = 'noindex,nofollow,noarchive,nosnippet',
    ): array {
        $title = trim($browserTitle);
        if ($title === '') {
            $parts = array_values(array_filter([
                trim($pageTitle),
                trim($siteTitle),
            ], static fn(string $part): bool => $part !== ''));
            $title = implode($titleSeparator, $parts);
        }

        if (self::isExcludedRoute($route, $excludedRoutes)) {
            $robots = $noindexRobots;
        }

        return [
            'title' => $title,
            'description' => trim($description),
            'robots' => trim($robots),
        ];
    }

    /** @param list<string> $patterns */
    public static function isExcludedRoute(string $route, array $patterns): bool
    {
        $path = self::normalizeRoute($route);
        foreach ($patterns as $pattern) {
            $pattern = self::normalizeRoute((string) $pattern);
            if ($pattern === '/') {
                if ($path === '/') return true;
                continue;
            }
            if (str_ends_with($pattern, '/*') && $path === rtrim(substr($pattern, 0, -2), '/')) {
                return true;
            }
            $quoted = preg_quote($pattern, '#');
            $quoted = str_replace('\\*', '.*', $quoted);
            if (preg_match('#^' . $quoted . '(?:/|$)#u', $path) === 1) {
                return true;
            }
        }

        return false;
    }

    public static function normalizeRoute(string $route): string
    {
        $path = parse_url($route, PHP_URL_PATH);
        $path = is_string($path) ? $path : '';
        $path = '/' . trim(rawurldecode($path), '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
