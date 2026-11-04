<?php

declare(strict_types=1);

/*
 * Fills the harness project the way `composer require` fills a real application: requires
 * the Lexical packages (plus Stimulus) at the constraint the bundle declares in
 * assets/package.json through AssetMapper's ImportMapManager - the code behind
 * `php bin/console importmap:require` - and downloads them into assets/vendor/.
 *
 * Usage: php tests/Browser/Harness/build.php
 *        php tests/Browser/Harness/build.php '^0.54.0'   # another constraint, e.g. to try a bump
 */

use FlexibleUx\LexicalBundle\Tests\Browser\Harness\HarnessKernel;
use Symfony\Component\AssetMapper\ImportMap\PackageRequireOptions;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$package = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/assets/package.json'), true, 512, \JSON_THROW_ON_ERROR);
$importmap = $package['symfony']['importmap'];
$override = $argv[1] ?? null;

$projectDir = __DIR__.'/project';
foreach (['/public', '/var', '/assets/controllers', '/assets/vendor'] as $dir) {
    if (!is_dir($projectDir.$dir)) {
        mkdir($projectDir.$dir, 0o777, true);
    }
}
if (!is_file($projectDir.'/importmap.php')) {
    file_put_contents($projectDir.'/importmap.php', <<<'PHP'
        <?php

        return [
            'app' => [
                'path' => './assets/app.js',
                'entrypoint' => true,
            ],
            '@symfony/stimulus-bundle' => [
                'path' => '@symfony/stimulus-bundle/loader.js',
            ],
        ];

        PHP);
}

$kernel = new HarnessKernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

$packages = [];
foreach ($importmap as $name => $constraint) {
    $packages[] = new PackageRequireOptions($name, null !== $override && ('lexical' === $name || str_starts_with($name, '@lexical/')) ? $override : $constraint);
}

$added = $container->get('harness.importmap.manager')->require($packages);
$container->get('harness.importmap.downloader')->downloadPackages();

$entries = [];
foreach ($container->get('harness.importmap.config_reader')->getEntries() as $entry) {
    $entries[] = \sprintf('%-52s %s', $entry->importName, $entry->isRemotePackage() ? $entry->version : '(local) '.$entry->path);
}
sort($entries);
printf("importmap.php: %d entries (%d required)\n%s\n", \count($entries), \count($added), implode("\n", $entries));
