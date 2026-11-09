<?php
namespace Grav\Plugin\Sitemap;

// Only the public data contract used by the adapter; clean-Grav tests use upstream.
final class SitemapEntry
{
    public $title, $route, $lang, $lastmod, $changefreq, $priority, $description, $markdown;
    public $home = false;
    public $hreflangs = [];
    public function __construct(public $location = null) {}
}
