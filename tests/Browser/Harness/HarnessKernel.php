<?php

declare(strict_types=1);

namespace FlexibleUx\LexicalBundle\Tests\Browser\Harness;

use FlexibleUx\LexicalBundle\FlexibleUxLexicalBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\Icons\UXIconsBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * The application the browser tests run against: a consumer of the bundle the way a real
 * Symfony application is one, with AssetMapper, its importmap, StimulusBundle's loader and
 * UX Icons wired as `composer require` would wire them. Its project dir is `project/` next
 * to this file; `build.php` fills it (the Lexical packages in `importmap.php` and
 * `assets/vendor/`), `router.php` serves it from PHP's built-in web server for Panther, and
 * `FixtureController` renders a form per scenario.
 */
final class HarnessKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new UXIconsBundle();
        yield new StimulusBundle();
        yield new FlexibleUxLexicalBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__.'/project';
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir().'/var/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'browser-harness',
            'http_method_override' => false,
            'php_errors' => ['log' => true],
            'form' => true,
            'csrf_protection' => false,
            'router' => ['utf8' => true],
            'assets' => false,
            'asset_mapper' => [
                'paths' => ['assets/'],
                // Serve `/assets/*` from the kernel: the pages are rendered on the fly and the
                // mapped files read straight from the bundle's working tree.
                'server' => true,
                'missing_import_mode' => 'strict',
            ],
        ]);

        $container->extension('twig', [
            'strict_variables' => true,
        ]);

        $container->extension('flexible_ux_lexical', [
            'presets' => [
                'compact' => ['toolbar' => ['bold', 'italic', '|', 'link', 'unlink'], 'height' => '120px'],
            ],
        ]);

        $container->services()
            ->set(FixtureController::class)
                ->args([service('twig'), service('form.factory')])
                ->public()
            // For build.php, which drives `importmap:require` without symfony/console.
            ->alias('harness.importmap.manager', 'asset_mapper.importmap.manager')->public()
            ->alias('harness.importmap.config_reader', 'asset_mapper.importmap.config_reader')->public()
            ->alias('harness.importmap.downloader', 'asset_mapper.importmap.remote_package_downloader')->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('fixture', '/')
            ->controller(FixtureController::class)
            ->methods(['GET', 'POST']);
    }
}
