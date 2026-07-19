<?php

declare(strict_types=1);

namespace FlexibleUx;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Registers the Lexical rich-text form type together with the assets that power it.
 *
 * On top of the tagged {@see \FlexibleUx\Form\Type\LexicalFormType} service, the bundle
 * prepends two pieces of configuration so the field works with zero manual wiring:
 *
 *  - the `lexical_widget` form theme is appended to Twig's `form_themes`, and
 *  - the shipped Lucide SVGs are registered as the `lexical` UX Icons icon set.
 *
 * Both prepends are guarded by {@see ContainerBuilder::hasExtension()} so the bundle
 * stays loadable even if Twig or UX Icons happen to be absent.
 *
 * The bundle class lives in `src/`, so {@see AbstractBundle::getPath()} (which returns
 * two directories up from this file) resolves to the package root — no override needed.
 */
final class FlexibleUxLexicalBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework')) {
            // Expose the bundle's `assets/` dir to AssetMapper under the same name the
            // Stimulus controller is referenced by. Without this, vendored bundle assets
            // are NOT auto-discovered and the `lexical` controller cannot be resolved
            // ("Could not find an asset mapper path that points to the lexical controller").
            $builder->prependExtensionConfig('framework', [
                'asset_mapper' => [
                    'paths' => [
                        $this->getPath().'/assets' => '@flexible-ux/lexical-bundle',
                    ],
                ],
            ]);
        }

        if ($builder->hasExtension('twig')) {
            $builder->prependExtensionConfig('twig', [
                'form_themes' => ['@FlexibleUxLexical/form/lexical_widget.html.twig'],
            ]);
        }

        if ($builder->hasExtension('ux_icons')) {
            $builder->prependExtensionConfig('ux_icons', [
                'icon_sets' => [
                    'lexical' => ['path' => $this->getPath().'/assets/icons'],
                ],
            ]);
        }
    }
}
