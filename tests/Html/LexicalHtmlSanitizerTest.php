<?php

declare(strict_types=1);

namespace FlexibleUx\LexicalBundle\Tests\Html;

use FlexibleUx\LexicalBundle\Html\LexicalHtmlSanitizerConfigFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;

/**
 * The sanitizer mirrors the editor: what the editor exports passes untouched, what it would
 * never produce is cut down to the same shape.
 */
final class LexicalHtmlSanitizerTest extends TestCase
{
    /**
     * The exact HTML the editor exported for a document with every feature of the default
     * toolbar, captured from the browser; the sanitizer has to be transparent to it.
     */
    private const EDITOR_OUTPUT = '<p style="text-align: right;">Hello <strong>bold</strong> and <em>italic</em> text.<code><sub><s><u><em><strong> x</strong></em></u></s></sub></code></p><ul><li value="1">one</li><li value="2">two</li></ul><p style="text-align: center;">centered</p><p><a href="https://example.com" target="_blank" rel="noopener noreferrer">a link</a> to follow</p><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" width="560" height="315" title="Video" allowfullscreen=""></iframe><p style="padding-inline-start: 80px;">last paragraph</p>';

    public function testTheEditorsOwnOutputPassesWithNothingRemoved(): void
    {
        // The one difference is spelling: the sanitizer writes the boolean attribute bare where
        // the browser's serializer had written `allowfullscreen=""`. Both read back the same.
        self::assertSame(
            str_replace('allowfullscreen=""', 'allowfullscreen', self::EDITOR_OUTPUT),
            $this->sanitize(self::EDITOR_OUTPUT),
        );
    }

    public function testScriptsStylesAndEventHandlersAreRemoved(): void
    {
        $dirty = '<p onclick="alert(1)">Hi<script>alert(1)</script></p><style>p{display:none}</style><p><a href="javascript:alert(1)">x</a></p>';

        self::assertSame('<p>Hi</p><p><a>x</a></p>', $this->sanitize($dirty));
    }

    public function testLinkSchemesFollowTheConfiguredAllowlist(): void
    {
        $sanitizer = new HtmlSanitizer(LexicalHtmlSanitizerConfigFactory::create(['https', 'mailto:'], false));

        self::assertSame(
            // (The sanitizer spells the mailto's @ as an entity; browsers read it back as @.)
            '<p><a href="https://a.test">a</a> <a>b</a> <a href="mailto:x&#64;y.test">c</a> <a>d</a></p>',
            $sanitizer->sanitize('<p><a href="https://a.test">a</a> <a href="http://b.test">b</a> <a href="mailto:x@y.test">c</a> <a href="/relative">d</a></p>'),
        );
    }

    public function testRelativeLinksFollowTheOption(): void
    {
        $links = '<p><a href="/about">a</a> <a href="#top">b</a> <a href="?page=2">c</a></p>';

        // (The sanitizer spells the query's = as an entity; browsers read it back as =.)
        self::assertSame(str_replace('?page=2', '?page&#61;2', $links), $this->sanitize($links));
        self::assertSame(
            '<p><a>a</a> <a>b</a> <a>c</a></p>',
            (new HtmlSanitizer(LexicalHtmlSanitizerConfigFactory::create(['https'], false)))->sanitize($links),
        );
    }

    public function testEmbedsAreHeldToHttpSourcesLikeTheEditor(): void
    {
        $dirty = '<iframe src="data:text/html,hi"></iframe><iframe src="javascript:alert(1)" onload="x()"></iframe><iframe src="/embed/1" width="100%"></iframe>';

        self::assertSame('<iframe></iframe><iframe></iframe><iframe src="/embed/1" width="100%"></iframe>', $this->sanitize($dirty));
    }

    public function testForeignWrappersLoseTheirTagButKeepTheirText(): void
    {
        $dirty = '<div class="x"><table><tr><td>cell</td></tr></table><font color="red">old</font></div><p>kept</p>';

        self::assertSame('cellold<p>kept</p>', $this->sanitize($dirty));
    }

    public function testEverythingElseIsDroppedWithItsContent(): void
    {
        $dirty = '<p>a</p><custom-el>word</custom-el><img src="https://a.test/x.png"><form><input value="x"></form><svg><text>t</text></svg><video controls>v</video><p>b</p>';

        self::assertSame('<p>a</p><p>b</p>', $this->sanitize($dirty));
    }

    public function testInlineStylesAreCutDownToWhatTheEditorWrites(): void
    {
        $dirty = '<p style="text-align: center; position: fixed; background: url(https://a.test/x.png)">a</p>'
            .'<p style="color: red">b</p>'
            .'<span style="color: rgb(255, 0, 0); font-size: 12px; display: block">c</span>'
            .'<li style="padding-inline-start: 40px">d</li>';

        self::assertSame(
            '<p style="text-align: center;">a</p><p>b</p><span style="color: rgb(255, 0, 0); font-size: 12px;">c</span><li style="padding-inline-start: 40px;">d</li>',
            $this->sanitize($dirty),
        );
    }

    public function testLegacyBoldAndItalicTagsSurvive(): void
    {
        self::assertSame('<p><b>old</b> <i>style</i></p>', $this->sanitize('<p><b>old</b> <i>style</i></p>'));
    }

    public function testLongDocumentsAreNotTruncated(): void
    {
        $paragraph = '<p>'.str_repeat('word ', 1000).'</p>';
        $long = str_repeat($paragraph, 10);
        self::assertGreaterThan(20000, \strlen($long));

        self::assertSame($long, $this->sanitize($long));
    }

    private function sanitize(string $html): string
    {
        return (new HtmlSanitizer(LexicalHtmlSanitizerConfigFactory::create(['http', 'https', 'mailto', 'tel'])))->sanitize($html);
    }
}
