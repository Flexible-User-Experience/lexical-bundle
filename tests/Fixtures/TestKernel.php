<?php

declare(strict_types=1);

namespace FlexibleUx\Tests\Fixtures;

use FlexibleUx\FlexibleUxLexicalBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\Icons\UXIconsBundle;

/**
 * Minimal application kernel used by the integration test to boot the bundle inside a
 * real container (FrameworkBundle + TwigBundle + UX Icons + this bundle) and render a
 * form, exercising the `prependExtension()` wiring, the tagged service, the form theme
 * and `ux_icon()` end to end.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new UXIconsBundle();
        yield new FlexibleUxLexicalBundle();
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/flexible-ux-lexical-bundle/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/flexible-ux-lexical-bundle/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'php_errors' => ['log' => true],
            'form' => true,
            'csrf_protection' => false,
            'router' => ['utf8' => true],
            'assets' => false,
        ]);

        $container->extension('twig', [
            'strict_variables' => true,
        ]);

        // Expose the services the integration test renders with; otherwise the compiler
        // inlines these private services and the test container can no longer fetch them.
        $container->services()
            ->alias('test.form.factory', 'form.factory')->public()
            ->alias('test.twig', 'twig')->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
    }
}
