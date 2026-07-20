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
use Symfony\UX\StimulusBundle\StimulusBundle;

/**
 * Minimal application kernel used by the integration tests to boot the bundle inside a
 * real container (FrameworkBundle + TwigBundle + UX Icons + StimulusBundle + this bundle)
 * and render a form, exercising the `prependExtension()` wiring, the tagged service, the
 * form theme and `ux_icon()` end to end.
 *
 * `$bundleConfig` stands in for `config/packages/flexible_ux_lexical.yaml`.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    private const TMP_DIR = '/flexible-ux-lexical-bundle';

    /**
     * @param array<string, mixed> $bundleConfig
     */
    public function __construct(
        string $environment = 'test',
        bool $debug = false,
        private readonly array $bundleConfig = [],
    ) {
        parent::__construct($environment, $debug);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new UXIconsBundle();
        // Provides the stimulus_controller/target/action Twig helpers the form theme uses.
        yield new StimulusBundle();
        yield new FlexibleUxLexicalBundle();
    }

    /**
     * Point the project dir at a temp location so nothing (e.g. FrameworkBundle's
     * debug-mode `config/reference.php` dump) is ever written into the bundle tree.
     */
    public function getProjectDir(): string
    {
        return sys_get_temp_dir().self::TMP_DIR;
    }

    public function getCacheDir(): string
    {
        // Vary by configuration, otherwise kernels booted with different bundle config
        // would reuse each other's compiled container.
        return \sprintf(
            '%s%s/cache/%s-%s',
            sys_get_temp_dir(),
            self::TMP_DIR,
            $this->environment,
            substr(md5(serialize($this->bundleConfig)), 0, 8),
        );
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().self::TMP_DIR.'/log';
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

        $container->extension('flexible_ux_lexical', $this->bundleConfig);

        // Expose the services the integration test uses; otherwise the compiler inlines
        // these private services and the test container can no longer fetch them.
        $container->services()
            ->alias('test.form.factory', 'form.factory')->public()
            ->alias('test.twig', 'twig')->public()
            ->alias('test.asset_mapper', 'asset_mapper')->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
    }
}
