<?php

declare(strict_types=1);

use FlexibleUx\LexicalBundle\Form\Type\LexicalFormType;
use FlexibleUx\LexicalBundle\Html\LexicalHtmlSanitizerConfigFactory;
use FlexibleUx\LexicalBundle\Twig\LexicalContentExtension;
use FlexibleUx\LexicalBundle\Twig\LexicalContentRuntime;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * Reusable bundles register their own services explicitly (no autowiring or
 * autoconfiguration) and prefix service ids with the bundle alias. The `form.type`
 * tag maps the type by its class, so `->add('body', LexicalFormType::class)` resolves
 * to this definition.
 *
 * The form type's arguments are the presets from `config/packages/flexible_ux_lexical.yaml`
 * (the top-level options as the `default` preset plus the named ones), the bundle's HTML
 * sanitizer and the default of the `sanitize` option; per-field options still override
 * whatever preset a field uses.
 *
 * The sanitizer is built from the same allowlist the editor enforces (see
 * LexicalHtmlSanitizerConfigFactory) and is also exposed for autowiring as
 * `HtmlSanitizerInterface $lexicalHtmlSanitizer`, for application code that renders or
 * imports stored content outside Twig.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('flexible_ux_lexical.html_sanitizer.config', HtmlSanitizerConfig::class)
        ->factory([LexicalHtmlSanitizerConfigFactory::class, 'create'])
        ->args([
            param('flexible_ux_lexical.sanitizer.allowed_link_schemes'),
        ]);

    $services->set('flexible_ux_lexical.html_sanitizer', HtmlSanitizer::class)
        ->args([
            service('flexible_ux_lexical.html_sanitizer.config'),
        ]);
    $services->alias(HtmlSanitizerInterface::class.' $lexicalHtmlSanitizer', 'flexible_ux_lexical.html_sanitizer');

    $services->set('flexible_ux_lexical.form.type.lexical', LexicalFormType::class)
        ->args([
            param('flexible_ux_lexical.presets'),
            service('flexible_ux_lexical.html_sanitizer'),
            param('flexible_ux_lexical.sanitize'),
        ])
        ->tag('form.type');

    $services->set('flexible_ux_lexical.twig.content_extension', LexicalContentExtension::class)
        ->tag('twig.extension');

    $services->set('flexible_ux_lexical.twig.content_runtime', LexicalContentRuntime::class)
        ->args([
            service('flexible_ux_lexical.html_sanitizer'),
        ])
        ->tag('twig.runtime');
};
