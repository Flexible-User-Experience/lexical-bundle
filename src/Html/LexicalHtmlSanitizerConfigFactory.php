<?php

declare(strict_types=1);

namespace FlexibleUx\LexicalBundle\Html;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Builds the sanitizer configuration that mirrors what the editor produces.
 *
 * The editor only ever writes the elements and attributes listed here (see
 * `assets/src/html-export.js` and `IframeNode`), so stored content that went through the
 * editor passes the sanitizer with nothing removed, and whatever did not - a submission from a
 * client without JavaScript, content pasted into the source modal that the model would have
 * dropped anyway - is cut down to the same shape. The common wrappers other editors and word
 * processors produce (`<div>`, `<section>`, a table and its cells, `<font>`, ...) are blocked
 * rather than dropped: their tags go, their text stays, so migrated content loses structure
 * but not words. Anything else - scripts, styles, forms, media, SVG, unknown elements - is
 * dropped with its content, the sanitizer's default.
 *
 * Used for the bundle's `flexible_ux_lexical.html_sanitizer` service, behind the `sanitize`
 * form option and the `lexical_content` / `lexical_sanitize` Twig filters.
 */
final class LexicalHtmlSanitizerConfigFactory
{
    /**
     * The elements the editor exports, with the attributes each may carry.
     *
     * @var array<string, list<string>>
     */
    public const ALLOWED_ELEMENTS = [
        'p' => ['style'],
        'h1' => ['style'],
        'h2' => ['style'],
        'h3' => ['style'],
        'h4' => ['style'],
        'h5' => ['style'],
        'h6' => ['style'],
        'blockquote' => ['style'],
        'ul' => ['style'],
        'ol' => ['style', 'start'],
        'li' => ['style', 'value'],
        'br' => [],
        'strong' => [],
        'em' => [],
        'u' => [],
        's' => [],
        'sub' => [],
        'sup' => [],
        'code' => [],
        'mark' => [],
        // What the editor wrote before it exported semantic tags, and what Lexical reads as
        // the same formats, so stored content from earlier versions keeps its emphasis.
        'b' => [],
        'i' => [],
        'span' => ['style'],
        'a' => ['href', 'target', 'rel', 'title'],
        'iframe' => ['src', 'width', 'height', 'title', 'allow', 'allowfullscreen', 'sandbox'],
    ];

    /**
     * Elements whose tag is removed but whose text is kept: the structure another editor or a
     * word processor gave to content the field never produced itself. Everything that is
     * neither allowed nor listed here is dropped with its content.
     *
     * @var list<string>
     */
    public const BLOCKED_ELEMENTS = [
        'div', 'section', 'article', 'header', 'footer', 'main', 'aside', 'nav', 'address',
        'figure', 'figcaption', 'details', 'summary', 'pre',
        'table', 'caption', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th',
        'dl', 'dt', 'dd',
        'font', 'center', 'small', 'big', 'tt', 'strike', 'del', 'ins',
        'abbr', 'cite', 'q', 'dfn', 'kbd', 'samp', 'var', 'time', 'label',
    ];

    /**
     * Schemes an embed's `src` may use. Fixed, as in the editor: a frame runs what it loads.
     *
     * @var list<string>
     */
    public const EMBED_SCHEMES = ['http', 'https'];

    /**
     * @param list<string> $allowedLinkSchemes the schemes a link may use, as the `allowed_link_schemes`
     *                                         option spells them (with or without the trailing colon)
     * @param bool         $allowRelativeLinks whether a link may also be a root-relative path, a fragment or
     *                                         a query, as the `allow_relative_links` option
     */
    public static function create(array $allowedLinkSchemes, bool $allowRelativeLinks = true): HtmlSanitizerConfig
    {
        // The default action for everything not configured below is Drop: element and content.
        $config = (new HtmlSanitizerConfig())
            ->allowLinkSchemes(array_values(array_unique(array_map(
                static fn (string $scheme): string => rtrim(strtolower(trim($scheme)), ':'),
                $allowedLinkSchemes,
            ))))
            ->allowRelativeLinks($allowRelativeLinks)
            ->allowMediaSchemes(self::EMBED_SCHEMES)
            // A relative frame source resolves against the page, like the editor accepts.
            ->allowRelativeMedias()
            ->withAttributeSanitizer(new StyleAttributeSanitizer())
            // A rich-text body has no natural size; the request size is the application's limit.
            ->withMaxInputLength(-1);

        foreach (self::ALLOWED_ELEMENTS as $element => $attributes) {
            $config = $config->allowElement($element, $attributes);
        }
        foreach (self::BLOCKED_ELEMENTS as $element) {
            $config = $config->blockElement($element);
        }

        return $config;
    }
}
