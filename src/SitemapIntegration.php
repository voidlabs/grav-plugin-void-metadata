<?php

declare(strict_types=1);

namespace VoidLabs\Metadata;

use Grav\Plugin\Sitemap\SitemapEntry;

/** Adapt the existing site selection to the official XML/llms renderers. */
final class SitemapIntegration
{
    /** @return array<string,SitemapEntry> */
    public static function entries(array $selected, array $native, string $root, ?string $language, array $physicalRoutes): array
    {
        $byUrl = [];
        foreach ($native as $entry) {
            if ($entry instanceof SitemapEntry) {
                $url = SitemapRenderer::normalizeUrl($root, (string) $entry->location);
                if ($url !== null) $byUrl[$url] = $entry;
            }
        }
        $result = [];
        foreach ($selected as $row) {
            $url = SitemapRenderer::normalizeUrl($root, $row['url'] ?? '');
            if ($url === null) throw new \RuntimeException('Invalid sitemap URL from the site provider.');
            $route = substr($url, strlen(rtrim($root, '/'))) ?: '/';
            // A physical page rejected by upstream (access, redirect, external URL,
            // sitemap.ignore) must stay rejected. Missing virtual routes are added.
            if (isset($physicalRoutes[rawurldecode($route)]) && !isset($byUrl[$url])) continue;
            $entry = isset($byUrl[$url]) ? clone $byUrl[$url] : new SitemapEntry($url);
            $entry->location = $url;
            $entry->route = $route;
            $entry->lang = $language;
            $entry->home = $route === '/';
            $entry->title = $entry->title ?: rawurldecode($route);
            $entry->markdown = rtrim($root, '/') . ($route === '/' ? '/index' : $route) . '.md';
            $entry->lastmod = $row['lastmod'] ?? date('c', (int) ($row['modified'] ?? 0));
            $entry->changefreq = $row['changefreq'] ?? null;
            $entry->priority = $row['priority'] ?? null;
            $entry->hreflangs = [];
            $result[$url] = $entry;
        }
        return $result;
    }
}
