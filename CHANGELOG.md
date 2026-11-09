# v0.2.0
## 11/09/2026

- Integrate the optional official Sitemap plugin through onSitemapProcessed.
- Share the existing public/noindex/virtual-route selection between XML and llms.txt.
- Verified on clean Grav 2.2.28 without Sitemap, with Sitemap, and with virtual-collections.
- Preserve upstream rejection of protected physical pages; no Sitemap dependency required when unused.

# 0.1.0
## 2026-09-19

1. [](#new)
   * Initial public release of Void Metadata for Grav 2.
   * Shared metadata policy for titles, descriptions and robots directives.
   * Canonical URL helpers, including Grav pagination routes.
   * Sitemap collection and XML rendering with URL normalization and optional
     `virtual-collections` integration.
   * Composer metadata, focused contract tests and continuous integration.
