<?php

declare(strict_types=1);

namespace VoidLabs\Metadata;

final class SitemapRenderer
{
    /** @param list<array{url?:string,route?:string,modified?:int|string|\DateTimeInterface}> $entries */
    public static function render(string $baseUrl, array $entries): string
    {
        $urls = [];
        foreach ($entries as $entry) {
            $url = self::normalizeUrl($baseUrl, $entry['url'] ?? $entry['route'] ?? '');
            if ($url === null) continue;
            $modified = self::timestamp($entry['modified'] ?? 0);
            $urls[$url] = max($urls[$url] ?? 0, $modified);
        }
        ksort($urls, SORT_STRING);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $url => $modified) {
            $xml .= '  <url><loc>' . self::xml($url) . '</loc>';
            if ($modified > 0) $xml .= '<lastmod>' . gmdate('Y-m-d', $modified) . '</lastmod>';
            $xml .= "</url>\n";
        }
        return $xml . "</urlset>\n";
    }

    public static function normalizeUrl(string $baseUrl, mixed $value): ?string
    {
        if (!is_string($value) || $value === '') return null;
        if (preg_match('//u', $value) !== 1 || preg_match('/[\\x00-\\x20\\x7f]|%(?![0-9A-Fa-f]{2})/u', $value) === 1) return null;
        if (preg_match('/[\\x00-\\x20\\x7f]/u', rawurldecode($value)) === 1) return null;
        $base = parse_url($baseUrl);
        if (!is_array($base) || !in_array(strtolower((string) ($base['scheme'] ?? '')), ['http', 'https'], true) || !isset($base['host']) || array_intersect_key($base, array_flip(['query', 'fragment'])) !== []) return null;
        $baseOrigin = strtolower((string) $base['scheme']) . '://' . strtolower((string) $base['host']) . (isset($base['port']) ? ':' . (int) $base['port'] : '');
        $basePath = '/' . trim(rawurldecode((string) ($base['path'] ?? '')), '/');
        $basePath = $basePath === '/' ? '' : rtrim($basePath, '/');
        if (preg_match('~^https?://~i', $value) === 1) {
            $parts = parse_url($value);
            if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) || !isset($parts['host']) || array_intersect_key($parts, array_flip(['query', 'fragment', 'user', 'pass'])) !== []) return null;
            $origin = strtolower((string) $parts['scheme']) . '://' . strtolower((string) $parts['host']) . (isset($parts['port']) ? ':' . (int) $parts['port'] : '');
            if ($origin !== $baseOrigin) return null;
            $path = (string) ($parts['path'] ?? '/');
            if ($path === '' || !str_starts_with($path, '/') || str_starts_with($path, '//')) return null;
            if (self::hasDotSegment($path)) return null;
            $path = self::encodePath($path);
            return $origin . ($path === '/' ? '/' : rtrim($path, '/'));
        }
        if (!str_starts_with($value, '/') || str_starts_with($value, '//') || str_contains($value, '?') || str_contains($value, '#')) return null;
        $parts = parse_url($value);
        if (!is_array($parts) || array_intersect_key($parts, array_flip(['scheme', 'host', 'query', 'fragment'])) !== []) return null;
        $path = '/' . trim((string) ($parts['path'] ?? ''), '/');
        if (self::hasDotSegment($path)) return null;
        $path = self::encodePath($path);
        return $baseOrigin . $basePath . ($path === '/' ? '/' : $path);
    }

    private static function encodePath(string $path): string
    {
        $segments = array_map(static function (string $segment): string {
            // Keep Grav's colon pagination syntax readable while converting
            // Unicode and other non-ASCII bytes to a valid URI path.
            return str_replace('%3A', ':', rawurlencode(rawurldecode($segment)));
        }, explode('/', $path));
        return implode('/', $segments);
    }

    private static function hasDotSegment(string $path): bool
    {
        foreach (explode('/', rawurldecode($path)) as $segment) {
            if ($segment === '.' || $segment === '..') return true;
        }
        return false;
    }

    private static function timestamp(mixed $value): int
    {
        if ($value instanceof \DateTimeInterface) return max(0, $value->getTimestamp());
        if (is_int($value) || is_float($value)) return max(0, (int) $value);
        if (is_string($value) && trim($value) !== '') {
            $timestamp = strtotime($value);
            return $timestamp === false ? 0 : max(0, $timestamp);
        }
        return 0;
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
