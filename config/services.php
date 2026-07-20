<?php

declare(strict_types=1);

use FlexibleUx\Form\Type\LexicalFormType;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

/*
 * Reusable bundles register their own services explicitly (no autowiring or
 * autoconfiguration) and prefix service ids with the bundle alias. The `form.type`
 * tag maps the type by its class, so `->add('body', LexicalFormType::class)` resolves
 * to this definition.
 *
 * The three arguments are the application-wide defaults coming from
 * `config/packages/flexible_ux_lexical.yaml`; per-field options still override them.
 */
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('flexible_ux_lexical.form.type.lexical', LexicalFormType::class)
            ->args([
                param('flexible_ux_lexical.toolbar'),
                param('flexible_ux_lexical.height'),
                param('flexible_ux_lexical.allowed_link_schemes'),
            ])
            ->tag('form.type');
};
