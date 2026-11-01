<?php

declare(strict_types=1);

namespace FlexibleUx\LexicalBundle;

use FlexibleUx\LexicalBundle\Form\Type\LexicalFormType;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

/**
 * Registers the Lexical rich-text form type together with the assets that power it.
 *
 * On top of the tagged {@see \FlexibleUx\LexicalBundle\Form\Type\LexicalFormType} service, the bundle
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
     * Application-wide defaults, set in `config/packages/flexible_ux_lexical.yaml`. The
     * top-level keys are the `default` preset every field starts from; `presets` adds named
     * ones a field selects with its `preset` option. Per-field options still take precedence.
     */
    public function configure(DefinitionConfigurator $definition): void
    {
        $toolbarInfo = \sprintf(
            'Ordered toolbar entries. Available buttons: "%s". Use "%s" to draw a separator.',
            implode('", "', LexicalFormType::AVAILABLE_BUTTONS),
            LexicalFormType::SEPARATOR,
        );

        $definition->rootNode()
            ->children()
                ->arrayNode('toolbar')
                    ->info($toolbarInfo)
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
                ->arrayNode('presets')
                    ->info(\sprintf(
                        'Named presets a field selects with its "preset" option. A key a preset leaves out is taken from the "%s" preset (the three options above); "%s" (every button) is built in.',
                        LexicalFormType::DEFAULT_PRESET,
                        LexicalFormType::FULL_PRESET,
                    ))
                    ->useAttributeAsKey('name')
                    ->validate()
                        ->ifTrue(static fn (array $presets): bool => isset($presets[LexicalFormType::DEFAULT_PRESET]))
                        ->thenInvalid(\sprintf('The "%s" preset is configured through the top-level "toolbar", "height" and "allowed_link_schemes" keys, not under "presets".', LexicalFormType::DEFAULT_PRESET))
                    ->end()
                    ->arrayPrototype()
                        ->children()
                            // Variable nodes keep "left out" apart from "set to []": only a key the
                            // preset actually sets overrides the default preset.
                            ->variableNode('toolbar')
                                ->info($toolbarInfo)
                                ->validate()
                                    ->ifTrue(static fn ($v): bool => !\is_array($v) || $v !== array_filter($v, 'is_string'))
                                    ->thenInvalid('The toolbar of a preset must be a list of strings.')
                                ->end()
                            ->end()
                            ->scalarNode('height')
                                ->info('Minimum editable height, as a CSS length.')
                            ->end()
                            ->variableNode('allowed_link_schemes')
                                ->info('URL schemes the link modal accepts, with or without the trailing colon.')
                                ->validate()
                                    ->ifTrue(static fn ($v): bool => !\is_array($v) || $v !== array_filter($v, 'is_string'))
                                    ->thenInvalid('The allowed link schemes of a preset must be a list of strings.')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $presets = [
            LexicalFormType::DEFAULT_PRESET => [
                'toolbar' => $config['toolbar'],
                'height' => $config['height'],
                'allowed_link_schemes' => $config['allowed_link_schemes'],
            ],
        ] + $config['presets'];

        // A typo in a configured toolbar fails here, at the first cache warm-up, instead of
        // the first time a field happens to use the preset.
        foreach ($presets as $name => $preset) {
            if (!isset($preset['toolbar'])) {
                continue;
            }
            try {
                LexicalFormType::normalizeToolbar(array_values($preset['toolbar']));
            } catch (InvalidOptionsException $e) {
                throw new InvalidConfigurationException(\sprintf('Invalid "flexible_ux_lexical" toolbar for the "%s" preset: %s', $name, $e->getMessage()), 0, $e);
            }
        }

        $container->setParameter('flexible_ux_lexical.presets', $presets);

        $configurator->import('../config/services.php');
    }

    public function prependExtension(ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if ($container->hasExtension('framework')) {
            // Expose the bundle's `assets/` dir to AssetMapper under the same name the
            // Stimulus controller is referenced by. Without this, vendored bundle assets
            // are NOT auto-discovered and the `lexical` controller cannot be resolved
            // ("Could not find an asset mapper path that points to the lexical controller").
            $container->prependExtensionConfig('framework', [
                'asset_mapper' => [
                    'paths' => [
                        $this->getPath().'/assets' => '@flexible-ux/lexical-bundle',
                    ],
                ],
            ]);
        }

        if ($container->hasExtension('twig')) {
            $container->prependExtensionConfig('twig', [
                'form_themes' => ['@FlexibleUxLexical/form/lexical_widget.html.twig'],
            ]);
        }

        if ($container->hasExtension('ux_icons')) {
            $container->prependExtensionConfig('ux_icons', [
                'icon_sets' => [
                    'lexical' => ['path' => $this->getPath().'/assets/icons'],
                ],
            ]);
        }
    }
}
