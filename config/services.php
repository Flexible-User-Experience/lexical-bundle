<?php

declare(strict_types=1);

use FlexibleUx\LexicalBundle\Form\Type\LexicalFormType;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

/*
 * Reusable bundles register their own services explicitly (no autowiring or
 * autoconfiguration) and prefix service ids with the bundle alias. The `form.type`
 * tag maps the type by its class, so `->add('body', LexicalFormType::class)` resolves
 * to this definition.
 *
 * The argument is the presets from `config/packages/flexible_ux_lexical.yaml` (the
 * top-level options as the `default` preset plus the named ones); per-field options
 * still override whatever preset a field uses.
 */
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('flexible_ux_lexical.form.type.lexical', LexicalFormType::class)
            ->args([
                param('flexible_ux_lexical.presets'),
            ])
            ->tag('form.type');
};
