<?php

declare(strict_types=1);

namespace FlexibleUx\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
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
 *  - `toolbar`: ordered toolbar entries. Each entry is one of {@see AVAILABLE_BUTTONS},
 *    or {@see SEPARATOR} to draw a divider — grouping is therefore entirely up to the
 *    caller. Redundant separators (leading, trailing or repeated) are dropped.
 *  - `height`: minimum editable height as a CSS length. Defaults to `200px`.
 *  - `allowed_link_schemes`: URL schemes the link modal accepts, written with or without
 *    the trailing colon. Defaults to `http`, `https`, `mailto` and `tel`; anything outside
 *    the list (notably `javascript:` and `data:`) is rejected.
 *
 * Application-wide defaults for all three come from the bundle configuration
 * (`config/packages/flexible_ux_lexical.yaml`) and are injected through the constructor;
 * per-field options always win.
 */
final class LexicalFormType extends AbstractType
{
    /**
     * Toolbar entry that renders a visual divider instead of a button.
     */
    public const SEPARATOR = '|';

    /**
     * Default minimum editable height.
     */
    public const DEFAULT_HEIGHT = '200px';

    /**
     * Every button the form theme knows how to render.
     *
     * @var list<string>
     */
    public const AVAILABLE_BUTTONS = [
        'bold', 'italic', 'underline', 'strikethrough', 'subscript', 'superscript',
        'bullet', 'number',
        'indent', 'outdent',
        'link', 'unlink',
    ];

    /**
     * Entries used when the `toolbar` option is not overridden.
     *
     * @var list<string>
     */
    public const DEFAULT_TOOLBAR = [
        'bold', 'italic', 'underline', 'strikethrough', 'subscript', 'superscript',
        self::SEPARATOR,
        'bullet', 'number',
        self::SEPARATOR,
        'indent', 'outdent',
        self::SEPARATOR,
        'link', 'unlink',
    ];

    /**
     * URL schemes the link modal accepts when `allowed_link_schemes` is not overridden.
     *
     * @var list<string>
     */
    public const DEFAULT_ALLOWED_LINK_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * @param list<string> $defaultToolbar
     * @param list<string> $defaultAllowedLinkSchemes
     */
    public function __construct(
        private readonly array $defaultToolbar = self::DEFAULT_TOOLBAR,
        private readonly string $defaultHeight = self::DEFAULT_HEIGHT,
        private readonly array $defaultAllowedLinkSchemes = self::DEFAULT_ALLOWED_LINK_SCHEMES,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'toolbar' => $this->defaultToolbar,
            'height' => $this->defaultHeight,
            'allowed_link_schemes' => $this->defaultAllowedLinkSchemes,
        ]);
        $resolver->setAllowedTypes('toolbar', 'string[]');
        $resolver->setAllowedTypes('height', 'string');
        $resolver->setAllowedTypes('allowed_link_schemes', 'string[]');
        $resolver->setNormalizer(
            'toolbar',
            static fn (Options $options, array $toolbar): array => self::normalizeToolbar($toolbar),
        );
        $resolver->setNormalizer(
            'allowed_link_schemes',
            static fn (Options $options, array $schemes): array => self::normalizeLinkSchemes($schemes),
        );
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['lexical_toolbar'] = $options['toolbar'];
        $view->vars['lexical_separator'] = self::SEPARATOR;
        $view->vars['lexical_height'] = $options['height'];
        $view->vars['lexical_allowed_link_schemes'] = $options['allowed_link_schemes'];
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

    /**
     * Rejects unknown entries, then tidies the separators: leading, trailing and repeated
     * ones are dropped, so a hand-written list can never render a stray or doubled divider.
     *
     * @param list<string> $toolbar
     *
     * @return list<string>
     */
    private static function normalizeToolbar(array $toolbar): array
    {
        $unknown = array_values(array_unique(array_diff($toolbar, self::AVAILABLE_BUTTONS, [self::SEPARATOR])));
        if ([] !== $unknown) {
            throw new InvalidOptionsException(\sprintf(
                'Unknown toolbar %s "%s". Available buttons are "%s"; use "%s" to draw a separator.',
                1 === \count($unknown) ? 'entry' : 'entries',
                implode('", "', $unknown),
                implode('", "', self::AVAILABLE_BUTTONS),
                self::SEPARATOR,
            ));
        }

        $normalized = [];
        foreach ($toolbar as $entry) {
            // Skip a separator that would be leading or would double up on the previous one.
            if (self::SEPARATOR === $entry && ([] === $normalized || self::SEPARATOR === end($normalized))) {
                continue;
            }
            $normalized[] = $entry;
        }

        while ([] !== $normalized && self::SEPARATOR === end($normalized)) {
            array_pop($normalized);
        }

        return array_values($normalized);
    }

    /**
     * Lowercases and strips any trailing colon so the controller can compare straight
     * against `URL.protocol` and callers may write "https" or "https:".
     *
     * @param list<string> $schemes
     *
     * @return list<string>
     */
    private static function normalizeLinkSchemes(array $schemes): array
    {
        return array_values(array_map(
            static fn (string $scheme): string => rtrim(strtolower(trim($scheme)), ':'),
            $schemes,
        ));
    }
}
