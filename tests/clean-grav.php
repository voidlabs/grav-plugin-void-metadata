<?php

declare(strict_types=1);

$gravRoot = $argv[1] ?? '';
$autoload = $gravRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if ($gravRoot === '' || !is_dir($gravRoot) || !is_file($autoload)) {
    fwrite(STDERR, "Usage: php tests/clean-grav.php /path/to/grav" . PHP_EOL);
    exit(2);
}

require_once $autoload;
require_once dirname(__DIR__) . '/void-metadata.php';

$class = \Grav\Plugin\VoidMetadataPlugin::class;
if (!class_exists($class)) {
    fwrite(STDERR, "FAIL: plugin class was not loadable with the Grav autoloader." . PHP_EOL);
    exit(1);
}

$reflection = new ReflectionClass($class);
if ($reflection->getParentClass()?->getName() !== \Grav\Common\Plugin::class) {
    fwrite(STDERR, "FAIL: plugin does not extend Grav\\Common\\Plugin." . PHP_EOL);
    exit(1);
}

$events = $class::getSubscribedEvents();
foreach (['onTwigInitialized', 'onPageHeaders'] as $event) {
    if (!isset($events[$event])) {
        fwrite(STDERR, "FAIL: missing {$event} subscription." . PHP_EOL);
        exit(1);
    }
}

if (!class_exists(\VoidLabs\Metadata\MetadataPolicy::class)
    || !class_exists(\VoidLabs\Metadata\SitemapRenderer::class)) {
    fwrite(STDERR, "FAIL: standalone metadata classes were not loadable." . PHP_EOL);
    exit(1);
}

echo "OK: plugin loaded against clean Grav installation." . PHP_EOL;
