<?php

declare(strict_types=1);

namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Plugins;

require_once __DIR__ . '/src/MetadataPolicy.php';
require_once __DIR__ . '/src/SitemapRenderer.php';

final class VoidMetadataPlugin extends Plugin
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onTwigInitialized' => ['onTwigInitialized', 0],
            'onPageHeaders' => ['onPageHeaders', 0],
        ];
    }

    public function onTwigInitialized(): void
    {
        $twig = $this->grav['twig']->twig;
        $twig->addFunction(new \Twig\TwigFunction('content_description', [$this, 'description']));
        $twig->addFunction(new \Twig\TwigFunction('void_canonical', [$this, 'canonical']));
        $twig->addFunction(new \Twig\TwigFunction('void_sitemap_entries', [$this, 'sitemapEntries']));
    }

    public function description(mixed $page, string $fallback = ''): string
    {
        if (!$page) return $fallback;
        $metadata = (array) ($page->header()->metadata ?? []);
        $override = trim((string) ($metadata['description'] ?? ''));
        if ($override !== '') return $override;
        $html = (string) $page->content();
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        // Keep the site's existing fallback policy: when no explicit fallback
        // is supplied and description_fallback is disabled, do not synthesize
        // a new editorial description from the body.
        if ($text === '' || ($fallback === '' && $this->config->get('plugins.void-metadata.description_fallback', true) === false)) return $fallback;
        $limit = max(40, (int) $this->config->get('plugins.void-metadata.description_limit', 160));
        if ($this->length($text) <= $limit) return $text;
        $short = rtrim($this->slice($text, 0, $limit - 1));
        $space = strrpos($short, ' ');
        if ($space !== false && $space >= (int) floor($limit * 0.7)) $short = rtrim(substr($short, 0, $space));
        return $short . '…';
    }

    /** @return array{title:string,description:string,robots:string} */
    public function metadata(
        string $route,
        string $pageTitle,
        string $siteTitle,
        string $browserTitle = '',
        string $description = '',
        string $robots = '',
    ): array {
        return \VoidLabs\Metadata\MetadataPolicy::metadata(
            $route,
            $pageTitle,
            $siteTitle,
            $browserTitle,
            $description,
            $robots,
            $this->noindexRoutes(),
            (string) $this->config->get('plugins.void-metadata.title_separator', ' | '),
            (string) $this->config->get('plugins.void-metadata.noindex_robots', 'noindex,nofollow,noarchive,nosnippet'),
        );
    }

    public function isNoindexRoute(string $route): bool
    {
        return \VoidLabs\Metadata\MetadataPolicy::isExcludedRoute($route, $this->noindexRoutes());
    }

    public function canonical(mixed $page): string
    {
        $root = rtrim((string) $this->grav['uri']->rootUrl(true), '/');
        if (!$page) return $root . '/';
        $path = '/' . trim(rawurldecode((string) $this->grav['uri']->path()), '/');
        if (preg_match('#/page:\d+/?$#D', $path) === 1) return $root . $path;
        $url = (string) $page->url(true, true);
        return $url !== '' ? $url : $root . (string) $page->route();
    }

    /** @return list<array{url:string,modified:int}> */
    public function sitemapEntries(): array
    {
        $excluded = array_map('strval', (array) $this->config->get('plugins.void-metadata.sitemap_excluded_templates', ['sitemap', 'feed', 'error', 'search']));
        $entries = [];
        $latestModified = 0;
        $root = rtrim((string) $this->grav['uri']->rootUrl(true), '/');
        foreach ($this->grav['pages']->all() as $page) {
            $route = '/' . trim((string) $page->route(), '/');
            $metadata = (array) ($page->header()->metadata ?? []);
            $robots = strtolower((string) ($metadata['robots'] ?? $page->header()->robots ?? ''));
            if (!$page->published() || !$page->routable() || in_array((string) $page->template(), $excluded, true) || $this->isNoindexRoute($route) || str_contains($robots, 'noindex')) continue;
            $modified = (int) $page->modified();
            $headerModified = trim((string) ($page->header()->modified ?? ''));
            if ($headerModified !== '') {
                $timestamp = strtotime($headerModified);
                if ($timestamp !== false) $modified = max($modified, $timestamp);
            }
            $url = \VoidLabs\Metadata\SitemapRenderer::normalizeUrl($root, (string) $page->url(true, true));
            $modified = max($modified, (int) $page->date());
            $latestModified = max($latestModified, $modified);
            if ($url !== null) $entries[$url] = ['url' => $url, 'modified' => $modified];
        }
        foreach ((array) $this->config->get('plugins.void-metadata.sitemap_route_maps', []) as $configKey) {
            foreach ((array) $this->grav['config']->get((string) $configKey, []) as $route) {
                $url = \VoidLabs\Metadata\SitemapRenderer::normalizeUrl($root, '/' . trim((string) $route, '/'));
                if ($url !== null) $entries[$url] = ['url' => $url, 'modified' => $latestModified];
            }
        }
        $virtualCollections = null;
        if (class_exists(Plugins::class)) {
            try {
                $virtualCollections = Plugins::getPlugin('virtual-collections');
            } catch (\Throwable) {
                // The shared metadata plugin also runs on sites without the
                // optional virtual-collections plugin.
            }
        }
        if ($virtualCollections !== null && method_exists($virtualCollections, 'virtualRoutes')) {
            try {
                $virtualRoutes = (array) $virtualCollections->virtualRoutes();
            } catch (\Throwable $exception) {
                if (isset($this->grav['log'])) {
                    $this->grav['log']->warning('Virtual collection routes omitted from sitemap (' . $exception::class . ').');
                }
                $virtualRoutes = [];
            }
            foreach ($virtualRoutes as $virtual) {
                $route = (string) ($virtual['route'] ?? '');
                if ($route === '' || ($virtual['sitemap'] ?? true) !== true || $this->isNoindexRoute($route)) continue;
                $url = \VoidLabs\Metadata\SitemapRenderer::normalizeUrl($root, '/' . ltrim($route, '/'));
                if ($url !== null) $entries[$url] = ['url' => $url, 'modified' => max($latestModified, (int) ($virtual['modified'] ?? 0))];
            }
        }
        ksort($entries, SORT_NATURAL | SORT_FLAG_CASE);
        return array_values($entries);
    }

    /** @param list<array{url?:string,route?:string,modified?:int}> $entries */
    public function sitemapXml(string $baseUrl, array $entries): string
    {
        return \VoidLabs\Metadata\SitemapRenderer::render($baseUrl, $entries);
    }

    public function onPageHeaders($event): void
    {
        $page = $this->grav['page'];
        if (!$page) return;
        $types = (array) $this->config->get('plugins.void-metadata.content_types', []);
        $type = $types[(string) $page->template()] ?? null;
        if (is_string($type) && $type !== '') $event['headers']->{'Content-Type'} = $type;
    }

    /** @return list<string> */
    private function noindexRoutes(): array
    {
        $routes = (array) $this->config->get('plugins.void-metadata.noindex_routes', []);
        if ($routes === []) $routes = (array) $this->config->get('plugins.void-metadata.sitemap_excluded_routes', []);
        return array_values(array_filter(array_map('strval', $routes), static fn(string $route): bool => trim($route) !== ''));
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    private function slice(string $value, int $offset, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($value, $offset, $length, 'UTF-8') : substr($value, $offset, $length);
    }
}
