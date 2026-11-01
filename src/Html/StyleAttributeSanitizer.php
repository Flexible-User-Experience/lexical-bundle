<?php

declare(strict_types=1);

namespace FlexibleUx\LexicalBundle\Html;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Keeps, of a `style` attribute, only the declarations the editor itself writes.
 *
 * Symfony's HtmlSanitizer treats `style` as an unsafe attribute and, once an element is
 * allowed to carry one, lets any value through. The editor stores two kinds of inline style
 * and nothing else: on a block, the `text-align` of the alignment buttons and the
 * `padding-inline-start` of indentation; on a `<span>`, the text styles Lexical preserves
 * from imported content. Everything else - positioning, backgrounds with `url()`, anything
 * with a function call other than a colour - is dropped, declaration by declaration, and an
 * attribute left with nothing is removed.
 */
final class StyleAttributeSanitizer implements AttributeSanitizerInterface
{
    /**
     * Elements the editor exports a `style` on, and what each may carry.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_PROPERTIES = [
        'p' => self::BLOCK_PROPERTIES,
        'h1' => self::BLOCK_PROPERTIES,
        'h2' => self::BLOCK_PROPERTIES,
        'h3' => self::BLOCK_PROPERTIES,
        'h4' => self::BLOCK_PROPERTIES,
        'h5' => self::BLOCK_PROPERTIES,
        'h6' => self::BLOCK_PROPERTIES,
        'blockquote' => self::BLOCK_PROPERTIES,
        'li' => self::BLOCK_PROPERTIES,
        'ul' => self::BLOCK_PROPERTIES,
        'ol' => self::BLOCK_PROPERTIES,
        'span' => self::TEXT_PROPERTIES,
    ];

    private const BLOCK_PROPERTIES = ['text-align', 'padding-inline-start'];

    private const TEXT_PROPERTIES = [
        'color', 'background-color', 'font-size', 'font-family', 'font-weight', 'font-style',
        'text-decoration', 'vertical-align', 'text-transform',
    ];

    /**
     * A value is a plain CSS token list: keywords, numbers with units, colours (hex, `rgb()`,
     * `hsl()`), font names. No quotes, no slashes, no angle brackets, no `url()` or other
     * resource-loading functions.
     */
    private const SAFE_VALUE = '/^[a-zA-Z0-9 #%.,()-]+$/';

    private const RESOURCE_FUNCTION = '/\b(?:url|image|image-set|src|expression|attr|element)\s*\(/i';

    public function getSupportedElements(): ?array
    {
        return array_keys(self::ALLOWED_PROPERTIES);
    }

    public function getSupportedAttributes(): ?array
    {
        return ['style'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $allowed = self::ALLOWED_PROPERTIES[$element] ?? [];
        $kept = [];
        foreach (explode(';', $value) as $declaration) {
            if (!str_contains($declaration, ':')) {
                continue;
            }
            [$property, $propertyValue] = explode(':', $declaration, 2);
            $property = strtolower(trim($property));
            $propertyValue = trim($propertyValue);
            if (
                !\in_array($property, $allowed, true)
                || '' === $propertyValue
                || !preg_match(self::SAFE_VALUE, $propertyValue)
                || preg_match(self::RESOURCE_FUNCTION, $propertyValue)
            ) {
                continue;
            }
            $kept[] = $property.': '.$propertyValue.';';
        }

        return [] === $kept ? null : implode(' ', $kept);
    }
}
