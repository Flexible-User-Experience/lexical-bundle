<?php

declare(strict_types=1);

namespace FlexibleUx;

use FlexibleUx\Form\Type\LexicalFormType;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
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
    /**
     * Application-wide defaults, set in `config/packages/flexible_ux_lexical.yaml`. Every
     * key mirrors a `LexicalFormType` option, and per-field options still take precedence.
     */
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('toolbar')
                    ->info(\sprintf(
                        'Ordered toolbar entries. Available buttons: "%s". Use "%s" to draw a separator.',
                        implode('", "', LexicalFormType::AVAILABLE_BUTTONS),
                        LexicalFormType::SEPARATOR,
                    ))
                    ->scalarPrototype()->end()
                    ->defaultValue(LexicalFormType::DEFAULT_TOOLBAR)
                ->end()
                ->scalarNode('height')
                    ->info('Minimum editable height, as a CSS length.')
                    ->defaultValue(LexicalFormType::DEFAULT_HEIGHT)
                ->end()
                ->arrayNode('allowed_link_schemes')
                    ->info('URL schemes the link modal accepts, with or without the trailing colon.')
                    ->scalarPrototype()->end()
                    ->defaultValue(LexicalFormType::DEFAULT_ALLOWED_LINK_SCHEMES)
                ->end()
            ->end();
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('flexible_ux_lexical.toolbar', $config['toolbar']);
        $builder->setParameter('flexible_ux_lexical.height', $config['height']);
        $builder->setParameter('flexible_ux_lexical.allowed_link_schemes', $config['allowed_link_schemes']);

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
