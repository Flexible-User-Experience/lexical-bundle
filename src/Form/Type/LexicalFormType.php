<?php

declare(strict_types=1);

namespace FlexibleUx\LexicalBundle\Form\Type;

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
 *  - `preset`: the name of a preset the other options default to. `default` and `full`
 *    are built in; the application adds its own under `flexible_ux_lexical.presets`.
 *  - `toolbar`: ordered toolbar entries. Each entry is one of {@see AVAILABLE_BUTTONS},
 *    or {@see SEPARATOR} to draw a divider — grouping is therefore entirely up to the
 *    caller. Redundant separators (leading, trailing or repeated) are dropped.
 *  - `height`: minimum editable height as a CSS length. Defaults to `200px`.
 *  - `allowed_link_schemes`: URL schemes the link modal accepts, written with or without
 *    the trailing colon. Defaults to `http`, `https`, `mailto` and `tel`; anything outside
 *    the list (notably `javascript:` and `data:`) is rejected.
 *
 * The presets come from the bundle configuration (`config/packages/flexible_ux_lexical.yaml`)
 * and are injected through the constructor; an option passed to a field always wins over
 * the preset it would otherwise take.
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
     * The preset a field uses when its `preset` option is not set.
     */
    public const DEFAULT_PRESET = 'default';

    /**
     * The built-in preset whose toolbar lists every button.
     */
    public const FULL_PRESET = 'full';

    /**
     * Every button the form theme knows how to render.
     *
     * @var list<string>
     */
    public const AVAILABLE_BUTTONS = [
        'undo', 'redo',
        'cut', 'copy', 'paste', 'paste-word',
        'bold', 'italic', 'underline', 'strikethrough', 'subscript', 'superscript', 'remove-format',
        'align-left', 'align-center', 'align-right', 'align-justify',
        'bullet', 'number',
        'indent', 'outdent',
        'link', 'unlink',
        'iframe', 'source',
    ];

    /**
     * The toolbar of the `default` preset: what a text field needs, nothing more. The
     * clipboard buttons, alignment, sub/superscript and the source editor stay available
     * to every field that lists them (or uses the `full` preset).
     *
     * @var list<string>
     */
    public const DEFAULT_TOOLBAR = [
        'undo', 'redo',
        self::SEPARATOR,
        'bold', 'italic', 'underline', 'strikethrough', 'remove-format',
        self::SEPARATOR,
        'bullet', 'number',
        self::SEPARATOR,
        'indent', 'outdent',
        self::SEPARATOR,
        'link', 'unlink',
        self::SEPARATOR,
        'iframe',
    ];

    /**
     * The toolbar of the `full` preset: every button, in nine groups.
     *
     * @var list<string>
     */
    public const FULL_TOOLBAR = [
        'undo', 'redo',
        self::SEPARATOR,
        'cut', 'copy', 'paste', 'paste-word',
        self::SEPARATOR,
        'bold', 'italic', 'underline', 'strikethrough', 'subscript', 'superscript', 'remove-format',
        self::SEPARATOR,
        'align-left', 'align-center', 'align-right', 'align-justify',
        self::SEPARATOR,
        'bullet', 'number',
        self::SEPARATOR,
        'indent', 'outdent',
        self::SEPARATOR,
        'link', 'unlink',
        self::SEPARATOR,
        'iframe', 'source',
    ];

    /**
     * URL schemes the link modal accepts when `allowed_link_schemes` is not overridden.
     *
     * @var list<string>
     */
    public const DEFAULT_ALLOWED_LINK_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * The option keys a preset may set.
     *
     * @var list<string>
     */
    public const PRESET_OPTIONS = ['toolbar', 'height', 'allowed_link_schemes'];

    /**
     * The resolved presets: every name maps to all three {@see PRESET_OPTIONS}.
     *
     * @var array<string, array{toolbar: list<string>, height: string, allowed_link_schemes: list<string>}>
     */
    private readonly array $presets;

    /**
     * @param array<string, array{toolbar?: list<string>, height?: string, allowed_link_schemes?: list<string>}> $presets
     *                                                                                                                   Presets by name; a key a preset leaves out is taken from the `default` preset,
     *                                                                                                                   which itself falls back to the class constants. `full` is added when missing.
     */
    public function __construct(array $presets = [])
    {
        $this->presets = self::resolvePresets($presets);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $presets = $this->presets;
        $resolver->setDefaults([
            'preset' => self::DEFAULT_PRESET,
            'toolbar' => static fn (Options $options): array => $presets[$options['preset']]['toolbar'],
            'height' => static fn (Options $options): string => $presets[$options['preset']]['height'],
            'allowed_link_schemes' => static fn (Options $options): array => $presets[$options['preset']]['allowed_link_schemes'],
        ]);
        $resolver->setAllowedTypes('preset', 'string');
        $resolver->setAllowedValues('preset', array_keys($presets));
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
        $view->vars['lexical_preset'] = $options['preset'];
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
     * Public so the bundle extension can validate the configured presets at compile time,
     * where a typo surfaces at the first cache warm-up rather than when a field uses the preset.
     *
     * @param list<string> $toolbar
     *
     * @return list<string>
     *
     * @throws InvalidOptionsException when an entry is neither a known button nor a separator
     */
    public static function normalizeToolbar(array $toolbar): array
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
     * Fills every preset up to all three options: a key a preset leaves out comes from
     * the `default` preset, whose own gaps are the class constants, and `full` (every
     * button, otherwise the defaults) is added unless the application defines its own.
     *
     * @param array<string, array<string, mixed>> $presets
     *
     * @return array<string, array{toolbar: list<string>, height: string, allowed_link_schemes: list<string>}>
     */
    private static function resolvePresets(array $presets): array
    {
        $default = ($presets[self::DEFAULT_PRESET] ?? []) + [
            'toolbar' => self::DEFAULT_TOOLBAR,
            'height' => self::DEFAULT_HEIGHT,
            'allowed_link_schemes' => self::DEFAULT_ALLOWED_LINK_SCHEMES,
        ];
        $presets += [self::FULL_PRESET => ['toolbar' => self::FULL_TOOLBAR]];

        $resolved = [self::DEFAULT_PRESET => $default];
        foreach ($presets as $name => $preset) {
            $resolved[$name] = array_intersect_key($preset + $default, $default);
        }

        return $resolved;
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
