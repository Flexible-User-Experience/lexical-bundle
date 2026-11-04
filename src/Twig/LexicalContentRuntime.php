<?php

declare(strict_types=1);

namespace FlexibleUx\LexicalBundle\Twig;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Runtime behind {@see LexicalContentExtension}: the sanitizer is only instantiated when a
 * template actually renders stored content.
 */
final class LexicalContentRuntime implements RuntimeExtensionInterface
{
    /**
     * The class the rendered content is wrapped in by default - the one the shipped
     * `lexical-content.css` styles and the editor's own surface carries.
     */
    public const CONTENT_CLASS = 'lexical-content';

    public function __construct(
        private readonly HtmlSanitizerInterface $sanitizer,
    ) {
    }

    /**
     * The stored HTML, sanitised. `null` and blank strings render as an empty string.
     */
    public function sanitize(?string $html): string
    {
        if (null === $html || '' === trim($html)) {
            return '';
        }

        return $this->sanitizer->sanitize($html);
    }

    /**
     * The stored HTML, sanitised and wrapped in a container the content stylesheet styles.
     *
     * @param string $class the container's class attribute; `lexical-content` unless the
     *                      application styles its content under another name
     */
    public function render(?string $html, string $class = self::CONTENT_CLASS): string
    {
        return \sprintf(
            '<div class="%s">%s</div>',
            htmlspecialchars($class, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'),
            $this->sanitize($html),
        );
    }
}
