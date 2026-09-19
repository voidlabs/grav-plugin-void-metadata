<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/MetadataPolicy.php';
require_once dirname(__DIR__) . '/src/SitemapRenderer.php';

use VoidLabs\Metadata\MetadataPolicy;
use VoidLabs\Metadata\SitemapRenderer;

$checks = 0;
$failures = [];

function check_metadata(bool $condition, string $message): void
{
    global $checks, $failures;
    ++$checks;
    if (!$condition) {
        $failures[] = $message;
    }
}

check_metadata(MetadataPolicy::isExcludedRoute('/search', ['/search/*']), 'La wildcard deve includere la route base.');
check_metadata(MetadataPolicy::isExcludedRoute('/search/help/', ['/search/*']), 'La wildcard deve includere le discendenze.');
check_metadata(!MetadataPolicy::isExcludedRoute('/searching', ['/search/*']), 'La wildcard non deve catturare prefissi simili.');
$metadata = MetadataPolicy::metadata('/home', '  Pagina  ', '  Sito  ', '', '  Descrizione  ', 'index,follow');
check_metadata($metadata['title'] === 'Pagina | Sito', 'Il titolo derivato non elimina gli spazi.');
check_metadata($metadata['description'] === 'Descrizione', 'La description esplicita non viene normalizzata.');
check_metadata($metadata['robots'] === 'index,follow', 'La direttiva robots esplicita è stata modificata.');
$metadata = MetadataPolicy::metadata('/search', 'Ricerca', 'Sito', '', 'Descrizione', '', ['/search/*']);
check_metadata($metadata['title'] === 'Ricerca | Sito', 'Titolo browser derivato in modo errato.');
check_metadata($metadata['robots'] === 'noindex,nofollow,noarchive,nosnippet', 'Policy noindex non applicata.');
check_metadata(MetadataPolicy::metadata('/home', 'Pagina', 'Sito', 'Titolo personalizzato')['title'] === 'Titolo personalizzato', 'Titolo browser esplicito ignorato.');
check_metadata(MetadataPolicy::normalizeRoute('https://example.test/ricerca%20avanzata/') === '/ricerca avanzata', 'Route assoluta o URL-encoded non normalizzata.');

check_metadata(SitemapRenderer::normalizeUrl('https://example.test', '/foo/') === 'https://example.test/foo', 'Trailing slash non normalizzato.');
check_metadata(SitemapRenderer::normalizeUrl('https://example.test', '/tag/pubblicità') === 'https://example.test/tag/pubblicit%C3%A0', 'Percorso Unicode non codificato.');
check_metadata(SitemapRenderer::normalizeUrl('https://example.test/base', '/foo') === 'https://example.test/base/foo', 'Base path non preservato.');
check_metadata(SitemapRenderer::normalizeUrl('https://example.test', '/foo%20bar') === null, 'Spazio encoded accettato nella sitemap.');
check_metadata(SitemapRenderer::normalizeUrl('https://example.test', 'https://evil.test/foo') === null, 'Origin esterno accettato.');
check_metadata(SitemapRenderer::normalizeUrl('https://example.test', '/foo?x=1') === null, 'Query accettata nella sitemap.');
check_metadata(SitemapRenderer::normalizeUrl('https://example.test', '/foo#fragment') === null, 'Fragment accettato nella sitemap.');
check_metadata(SitemapRenderer::normalizeUrl('https://example.test', '/a/../b') === null, 'Dot segment accettato.');
$xml = SitemapRenderer::render('https://example.test', [
    ['url' => '/a&b', 'modified' => '2025-01-02'],
    ['url' => '/a&b', 'modified' => '2025-01-03'],
    ['url' => '/bad?query=1', 'modified' => '2025-01-04'],
]);
check_metadata(substr_count($xml, '<loc>https://example.test/a%26b</loc>') === 1, 'Entry duplicate o XML non escaped correttamente.');
check_metadata(str_contains($xml, '<lastmod>2025-01-03</lastmod>'), 'Last modification non massima.');
check_metadata(simplexml_load_string($xml) !== false, 'Sitemap XML non valido.');

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}" . PHP_EOL);
    }
    exit(1);
}

echo "OK: {$checks} void-metadata checks." . PHP_EOL;
