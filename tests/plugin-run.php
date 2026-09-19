<?php

declare(strict_types=1);

namespace Grav\Common {
    final class Plugins
    {
        /** @var array<string, object|null> */
        public static array $registered = [];

        public static function getPlugin(string $name): ?object
        {
            return self::$registered[$name] ?? null;
        }
    }

    class Plugin
    {
        public object $config;

        /** @var array<string, object> */
        public array $grav = [];
    }
}

namespace {
    require_once dirname(__DIR__) . '/void-metadata.php';

    use Grav\Common\Plugins;
    use Grav\Plugin\VoidMetadataPlugin;

    final class TestConfig
    {
        /** @param array<string, mixed> $values */
        public function __construct(private array $values = [])
        {
        }

        public function get(string $path, mixed $default = null): mixed
        {
            return array_key_exists($path, $this->values) ? $this->values[$path] : $default;
        }
    }

    final class TestUri
    {
        public function __construct(private string $path = '/pagina')
        {
        }

        public function rootUrl(bool $absolute = false): string
        {
            return 'https://example.test';
        }

        public function path(): string
        {
            return $this->path;
        }
    }

    final class TestHeader
    {
        /** @param array<string, mixed> $metadata */
        public function __construct(
            public array $metadata = [],
            public string $robots = '',
            public string $modified = '',
        ) {
        }
    }

    final class TestPage
    {
        public function __construct(
            private string $body,
            private string $pageUrl = 'https://example.test/pagina',
            private string $pageRoute = '/pagina',
            private string $pageTemplate = 'default',
            private TestHeader $pageHeader = new TestHeader(),
            private bool $isPublished = true,
            private bool $isRoutable = true,
            private int $pageModified = 0,
            private int $pageDate = 0,
        ) {
        }

        public function header(): TestHeader
        {
            return $this->pageHeader;
        }

        public function content(): string
        {
            return $this->body;
        }

        public function url(bool $absolute = false, bool $includeLanguage = false): string
        {
            return $this->pageUrl;
        }

        public function route(): string
        {
            return $this->pageRoute;
        }

        public function template(): string
        {
            return $this->pageTemplate;
        }

        public function published(): bool
        {
            return $this->isPublished;
        }

        public function routable(): bool
        {
            return $this->isRoutable;
        }

        public function modified(): int
        {
            return $this->pageModified;
        }

        public function date(): int
        {
            return $this->pageDate;
        }
    }

    final class TestPages
    {
        /** @param list<TestPage> $pages */
        public function __construct(private array $pages)
        {
        }

        /** @return list<TestPage> */
        public function all(): array
        {
            return $this->pages;
        }
    }

    $plugin = new VoidMetadataPlugin();
    $plugin->config = new TestConfig([
        'plugins.void-metadata.description_limit' => 40,
        'plugins.void-metadata.noindex_routes' => ['/private/*'],
        'plugins.void-metadata.sitemap_excluded_templates' => ['feed'],
    ]);
    $plugin->grav = [
        'uri' => new TestUri(),
        'pages' => new TestPages([
            new TestPage('<p>Una descrizione &amp; molto utile con parole sufficienti per essere accorciata.</p>'),
            new TestPage('Feed', 'https://example.test/feed', '/feed', 'feed'),
            new TestPage('Privata', 'https://example.test/private', '/private'),
        ]),
        'config' => new TestConfig(),
    ];

    $checks = 0;
    $failures = [];

    $check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
        ++$checks;
        if (!$condition) $failures[] = $message;
    };

    $bodyDescription = $plugin->description(new TestPage('<p>Prima &amp; dopo</p>'));
    $check($bodyDescription === 'Prima & dopo', 'La description non estrae e decodifica il testo HTML.');
    $check($plugin->description(new TestPage('Testo ignorato', pageHeader: new TestHeader(['description' => '  Override  ']))) === 'Override', 'Override description non applicato.');
    $longDescription = $plugin->description(new TestPage('<p>Una descrizione molto lunga con parole che superano il limite configurato.</p>'));
    $check(mb_strlen($longDescription, 'UTF-8') <= 40 && str_ends_with($longDescription, '…'), 'Description non troncata secondo il limite.');

    $check($plugin->metadata('/private', 'Pagina', 'Sito')['robots'] === 'noindex,nofollow,noarchive,nosnippet', 'Noindex route non applicata dal plugin.');
    $check($plugin->metadata('/home', 'Pagina', 'Sito', 'Titolo browser')['title'] === 'Titolo browser', 'Titolo browser non rispettato dal plugin.');
    $check($plugin->canonical(null) === 'https://example.test/', 'Canonical homepage errata.');
    $check($plugin->canonical(new TestPage('', 'https://example.test/pagina/')) === 'https://example.test/pagina/', 'Canonical assoluta errata.');

    $plugin->grav['uri'] = new TestUri('/pagina/page:2');
    $check($plugin->canonical(new TestPage('', 'https://example.test/pagina/page:2')) === 'https://example.test/pagina/page:2', 'Canonical paginata errata.');
    $plugin->grav['uri'] = new TestUri();

    $entries = $plugin->sitemapEntries();
    $check(count($entries) === 1 && $entries[0]['url'] === 'https://example.test/pagina', 'Sitemap plugin non filtra template e noindex route.');

    Plugins::$registered['virtual-collections'] = new class {
        /** @return list<array{route:string,sitemap:bool,modified:int}> */
        public function virtualRoutes(): array
        {
            return [
                ['route' => '/virtuale', 'sitemap' => true, 'modified' => 1735689600],
                ['route' => '/private/virtuale', 'sitemap' => true, 'modified' => 1735689600],
            ];
        }
    };
    $entries = $plugin->sitemapEntries();
    $check(count($entries) === 2 && $entries[1]['url'] === 'https://example.test/virtuale', 'Route virtuali valide non aggiunta alla sitemap.');
    Plugins::$registered = [];

    if ($failures !== []) {
        foreach ($failures as $failure) fwrite(STDERR, "FAIL: {$failure}" . PHP_EOL);
        exit(1);
    }

    echo "OK: {$checks} plugin checks." . PHP_EOL;
}
