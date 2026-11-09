<?php

declare(strict_types=1);

require_once __DIR__ . '/stubs/SitemapEntry.php';
require_once dirname(__DIR__) . '/src/SitemapRenderer.php';
require_once dirname(__DIR__) . '/src/SitemapIntegration.php';

function testSitemapIntegration(): void
{
    foreach (['emailmarketingblog.it' => '/categoria/emailmarketingblog', 'myhq.it' => '/tags/php'] as $host => $virtual) {
        $root = 'https://' . $host;
        $home = new \Grav\Plugin\Sitemap\SitemapEntry($root . '/');
        $home->title = 'Home';
        $home->description = 'Description';
        $home->hreflangs = [['hreflang' => 'it', 'href' => $root . '/it']];
        $rows = [
            ['url' => $root . '/', 'modified' => 1700000000],
            ['url' => $root . $virtual, 'lastmod' => '2026-01-01T00:00:00+00:00', 'priority' => '0.6'],
            ['url' => $root . '/private', 'modified' => 1700000000],
        ];
        $entries = \VoidLabs\Metadata\SitemapIntegration::entries($rows, [$home], $root, 'it', ['/' => true, '/private' => true]);
        check(count($entries) === 2, 'Sitemap: virtual page lost or protected physical page restored.');
        check($entries[$root . '/']->markdown === $root . '/index.md', 'Sitemap: invalid home Markdown URL.');
        check($entries[$root . $virtual]->markdown === $root . $virtual . '.md', 'Sitemap: missing virtual Markdown URL.');
        check($entries[$root . '/']->description === 'Description', 'Sitemap: native description lost.');
        check($entries[$root . '/']->hreflangs === [], 'Sitemap: unwanted language prefixes.');
        check($home->hreflangs !== [], 'Sitemap: native cached object mutated.');
        check(strtotime($entries[$root . '/']->lastmod) === 1700000000, 'Sitemap: lastmod changed.');
        check($entries[$root . $virtual]->priority === '0.6', 'Sitemap: priority lost.');
        $encoded = \VoidLabs\Metadata\SitemapIntegration::entries([['url' => $root . '/tags/pubblicità']], [], $root, 'it', []);
        check(isset($encoded[$root . '/tags/pubblicit%C3%A0']), 'Sitemap: Unicode route not URI encoded.');
        $rejected = false;
        try {
            \VoidLabs\Metadata\SitemapIntegration::entries([['url' => 'https://other.example/private']], [], $root, 'it', []);
        } catch (RuntimeException) {
            $rejected = true;
        }
        check($rejected, 'Sitemap: external provider URL accepted.');
    }
}

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
testSitemapIntegration();
echo "OK: official Sitemap integration fixtures passed.\n";
