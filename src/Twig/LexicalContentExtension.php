<?php

declare(strict_types=1);

namespace FlexibleUx\LexicalBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig filters for rendering what a LexicalFormType field stores.
 *
 *  - `{{ post.body|lexical_content }}` sanitises the HTML through the bundle's sanitizer and
 *    wraps it in `<div class="lexical-content">`, the class `lexical-content.css` styles;
 *  - `{{ post.body|lexical_sanitize }}` sanitises without wrapping, for templates that
 *    provide a container of their own.
 *
 * Both are marked safe for HTML, so no `|raw` is needed - the sanitizer is what makes the
 * output safe, with the same allowlist the editor enforces.
 */
final class LexicalContentExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('lexical_content', [LexicalContentRuntime::class, 'render'], ['is_safe' => ['html']]),
            new TwigFilter('lexical_sanitize', [LexicalContentRuntime::class, 'sanitize'], ['is_safe' => ['html']]),
        ];
    }
}
