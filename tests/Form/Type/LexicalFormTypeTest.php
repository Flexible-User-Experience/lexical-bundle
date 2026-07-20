<?php

declare(strict_types=1);

namespace FlexibleUx\Tests\Form\Type;

use FlexibleUx\Form\Type\LexicalFormType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Test\TypeTestCase;

final class LexicalFormTypeTest extends TypeTestCase
{
    public function testDefaultViewVars(): void
    {
        $view = $this->factory->create(LexicalFormType::class)->createView();

        self::assertSame(LexicalFormType::DEFAULT_TOOLBAR, $view->vars['lexical_toolbar']);
        self::assertSame([
            'bold', 'italic', 'underline', 'strikethrough', 'subscript', 'superscript',
            '|',
            'bullet', 'number',
            '|',
            'indent', 'outdent',
            '|',
            'link', 'unlink',
        ], $view->vars['lexical_toolbar']);
        self::assertSame('|', $view->vars['lexical_separator']);
        self::assertSame('200px', $view->vars['lexical_height']);
    }

    public function testCustomOptions(): void
    {
        $view = $this->factory->create(LexicalFormType::class, null, [
            'toolbar' => ['bold', 'italic', 'link', 'unlink'],
            'height' => '320px',
        ])->createView();

        self::assertSame(['bold', 'italic', 'link', 'unlink'], $view->vars['lexical_toolbar']);
        self::assertSame('320px', $view->vars['lexical_height']);
    }

    public function testRejectsNonStringToolbarEntries(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);

        $this->factory->create(LexicalFormType::class, null, ['toolbar' => [42]]);
    }

    public function testGroupingIsEntirelyCallerDefined(): void
    {
        // Separators are explicit entries, so any grouping the caller wants is possible.
        $view = $this->factory->create(LexicalFormType::class, null, [
            'toolbar' => ['bold', '|', 'link', 'unlink', '|', 'indent'],
        ])->createView();

        self::assertSame(['bold', '|', 'link', 'unlink', '|', 'indent'], $view->vars['lexical_toolbar']);
    }

    public function testNoSeparatorsMeansNoGrouping(): void
    {
        $view = $this->factory->create(LexicalFormType::class, null, [
            'toolbar' => ['bold', 'bullet', 'link'],
        ])->createView();

        self::assertSame(['bold', 'bullet', 'link'], $view->vars['lexical_toolbar']);
    }

    public function testRedundantSeparatorsAreDropped(): void
    {
        // Leading, trailing and repeated separators can never render a stray divider.
        $view = $this->factory->create(LexicalFormType::class, null, [
            'toolbar' => ['|', '|', 'bold', '|', '|', 'italic', '|'],
        ])->createView();

        self::assertSame(['bold', '|', 'italic'], $view->vars['lexical_toolbar']);
    }

    public function testRejectsUnknownToolbarEntry(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $this->expectExceptionMessageMatches('/Unknown toolbar entry "blink"/');

        $this->factory->create(LexicalFormType::class, null, ['toolbar' => ['bold', 'blink']]);
    }

    public function testDefaultAllowedLinkSchemes(): void
    {
        $view = $this->factory->create(LexicalFormType::class)->createView();

        self::assertSame(
            LexicalFormType::DEFAULT_ALLOWED_LINK_SCHEMES,
            $view->vars['lexical_allowed_link_schemes'],
        );
        self::assertSame(['http', 'https', 'mailto', 'tel'], $view->vars['lexical_allowed_link_schemes']);
    }

    public function testCustomAllowedLinkSchemesAreNormalised(): void
    {
        // Callers may write the scheme with or without the trailing colon, in any case.
        $view = $this->factory->create(LexicalFormType::class, null, [
            'allowed_link_schemes' => ['HTTPS:', ' mailto ', 'ftp'],
        ])->createView();

        self::assertSame(['https', 'mailto', 'ftp'], $view->vars['lexical_allowed_link_schemes']);
    }

    public function testAllowedLinkSchemesCanBeNarrowed(): void
    {
        $view = $this->factory->create(LexicalFormType::class, null, [
            'allowed_link_schemes' => ['https'],
        ])->createView();

        self::assertSame(['https'], $view->vars['lexical_allowed_link_schemes']);
    }

    public function testRejectsNonStringAllowedLinkSchemes(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);

        $this->factory->create(LexicalFormType::class, null, ['allowed_link_schemes' => [42]]);
    }

    public function testBlockPrefixIsLexical(): void
    {
        // The `lexical_widget` form theme block hangs off this prefix.
        self::assertSame('lexical', (new LexicalFormType())->getBlockPrefix());
    }

    public function testParentIsTextarea(): void
    {
        self::assertSame(TextareaType::class, (new LexicalFormType())->getParent());
    }
}
