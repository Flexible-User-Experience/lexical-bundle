<?php

declare(strict_types=1);

use FlexibleUx\Form\Type\LexicalFormType;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/*
 * Reusable bundles register their own services explicitly (no autowiring or
 * autoconfiguration) and prefix service ids with the bundle alias. The `form.type`
 * tag maps the type by its class, so `->add('body', LexicalFormType::class)` resolves
 * to this definition.
 */
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('flexible_ux_lexical.form.type.lexical', LexicalFormType::class)
            ->tag('form.type');
};
