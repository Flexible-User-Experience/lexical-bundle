<?php

declare(strict_types=1);

namespace FlexibleUx\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A rich-text editor field backed by Meta's Lexical, exposed through the reusable
 * `lexical` Stimulus controller.
 *
 * The `lexical_widget` form theme renders the toolbar (icons via `ux_icon`) and the
 * editable surface around a hidden textarea that stores the HTML, so the field is a
 * drop-in replacement for a plain {@see TextareaType} and degrades to that textarea
 * when JavaScript is unavailable.
 *
 * Options:
 *  - `toolbar`: ordered button names to show. Available buttons: `bold`, `italic`,
 *    `underline`, `strikethrough`, `subscript`, `superscript`, `bullet`, `number`,
 *    `indent`, `outdent`, `link`, `unlink`.
 *  - `height`: minimum editable height as a CSS length. Defaults to `200px`.
 *  - `allowed_link_schemes`: URL schemes the link modal accepts, written with or without
 *    the trailing colon. Defaults to `http`, `https`, `mailto` and `tel`; anything outside
 *    the list (notably `javascript:` and `data:`) is rejected.
 */
final class LexicalFormType extends AbstractType
{
    /**
     * Buttons shown when the `toolbar` option is not overridden.
     *
     * @var list<string>
     */
    public const DEFAULT_TOOLBAR = [
        'bold', 'italic', 'underline', 'strikethrough', 'subscript', 'superscript',
        'bullet', 'number',
        'indent', 'outdent',
        'link', 'unlink',
    ];

    /**
     * URL schemes the link modal accepts when `allowed_link_schemes` is not overridden.
     *
     * @var list<string>
     */
    public const DEFAULT_ALLOWED_LINK_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'toolbar' => self::DEFAULT_TOOLBAR,
            'height' => '200px',
            'allowed_link_schemes' => self::DEFAULT_ALLOWED_LINK_SCHEMES,
        ]);
        $resolver->setAllowedTypes('toolbar', 'string[]');
        $resolver->setAllowedTypes('height', 'string');
        $resolver->setAllowedTypes('allowed_link_schemes', 'string[]');
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['lexical_toolbar'] = array_values($options['toolbar']);
        $view->vars['lexical_height'] = $options['height'];
        // Normalised here (lowercase, no trailing colon) so the controller can compare
        // straight against `URL.protocol` and callers may write "https" or "https:".
        $view->vars['lexical_allowed_link_schemes'] = array_values(array_map(
            static fn (string $scheme): string => rtrim(strtolower(trim($scheme)), ':'),
            $options['allowed_link_schemes'],
        ));
    }

    public function getParent(): string
    {
        return TextareaType::class;
    }

    /**
     * Pinned so the theme block is `lexical_widget` and every layer shares the same
     * `lexical` name; the class-derived default would otherwise be `lexical_form`.
     */
    public function getBlockPrefix(): string
    {
        return 'lexical';
    }
}
