<?php

declare(strict_types=1);

namespace FlexibleUx\LexicalBundle\Tests\Form\Type;

use FlexibleUx\LexicalBundle\Form\Type\LexicalFormType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

/**
 * The attribute opts out of PHPUnit 13's expectation-less-mock notice: the mock is the
 * EventDispatcherInterface created by {@see TypeTestCase::setUp()} in vendor code, not ours
 * to refactor. PHPUnit 11 does not know the attribute and ignores it.
 */
#[AllowMockObjectsWithoutExpectations]
final class LexicalFormTypeTest extends TypeTestCase
{
    public function testDefaultViewVars(): void
    {
        $view = $this->factory->create(LexicalFormType::class)->createView();

        self::assertSame('default', $view->vars['lexical_preset']);
        self::assertSame(LexicalFormType::DEFAULT_TOOLBAR, $view->vars['lexical_toolbar']);
        // The default toolbar is the lean one: no clipboard buttons, alignment,
        // sub/superscript or source editor until a field (or preset) asks for them.
        self::assertSame([
            'undo', 'redo',
            '|',
            'bold', 'italic', 'underline', 'strikethrough', 'remove-format',
            '|',
            'bullet', 'number',
            '|',
            'indent', 'outdent',
            '|',
            'link', 'unlink',
            '|',
            'iframe',
        ], $view->vars['lexical_toolbar']);
        self::assertSame('|', $view->vars['lexical_separator']);
        self::assertSame('200px', $view->vars['lexical_height']);
    }

    public function testFullPresetListsEveryButton(): void
    {
        $view = $this->factory->create(LexicalFormType::class, null, ['preset' => 'full'])->createView();

        self::assertSame('full', $view->vars['lexical_preset']);
        self::assertSame(LexicalFormType::FULL_TOOLBAR, $view->vars['lexical_toolbar']);
        self::assertSame(
            LexicalFormType::AVAILABLE_BUTTONS,
            array_values(array_filter($view->vars['lexical_toolbar'], static fn (string $entry): bool => '|' !== $entry)),
        );
        // The rest of the preset is the default's.
        self::assertSame('200px', $view->vars['lexical_height']);
        self::assertSame(['http', 'https', 'mailto', 'tel'], $view->vars['lexical_allowed_link_schemes']);
    }

    public function testUnknownPresetIsRejected(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessageMatches('/"preset"/');

        $this->factory->create(LexicalFormType::class, null, ['preset' => 'nope']);
    }

    public function testConfiguredPresetSuppliesTheOptionsItSets(): void
    {
        $factory = $this->createFactoryWithPresets([
            'compact' => ['toolbar' => ['bold', 'italic'], 'height' => '100px'],
        ]);

        $view = $factory->create(LexicalFormType::class, null, ['preset' => 'compact'])->createView();

        self::assertSame('compact', $view->vars['lexical_preset']);
        self::assertSame(['bold', 'italic'], $view->vars['lexical_toolbar']);
        self::assertSame('100px', $view->vars['lexical_height']);
        // A key the preset leaves out comes from the default preset.
        self::assertSame(['http', 'https', 'mailto', 'tel'], $view->vars['lexical_allowed_link_schemes']);
    }

    public function testApplicationDefaultPresetFeedsTheOtherPresets(): void
    {
        // The application's own defaults (the top-level configuration keys) are the base
        // every other preset, the built-in `full` included, falls back to.
        $factory = $this->createFactoryWithPresets([
            'default' => ['height' => '300px', 'allowed_link_schemes' => ['https']],
            'compact' => ['toolbar' => ['bold']],
        ]);

        $compact = $factory->create(LexicalFormType::class, null, ['preset' => 'compact'])->createView();
        self::assertSame('300px', $compact->vars['lexical_height']);
        self::assertSame(['https'], $compact->vars['lexical_allowed_link_schemes']);

        $full = $factory->create(LexicalFormType::class, null, ['preset' => 'full'])->createView();
        self::assertSame(LexicalFormType::FULL_TOOLBAR, $full->vars['lexical_toolbar']);
        self::assertSame('300px', $full->vars['lexical_height']);

        $default = $factory->create(LexicalFormType::class)->createView();
        self::assertSame(LexicalFormType::DEFAULT_TOOLBAR, $default->vars['lexical_toolbar']);
        self::assertSame('300px', $default->vars['lexical_height']);
    }

    public function testFieldOptionsWinOverThePreset(): void
    {
        $factory = $this->createFactoryWithPresets([
            'compact' => ['toolbar' => ['bold', 'italic'], 'height' => '100px'],
        ]);

        $view = $factory->create(LexicalFormType::class, null, ['preset' => 'compact', 'height' => '150px'])->createView();

        self::assertSame(['bold', 'italic'], $view->vars['lexical_toolbar']);
        self::assertSame('150px', $view->vars['lexical_height']);
    }

    public function testApplicationCanRedefineTheFullPreset(): void
    {
        $factory = $this->createFactoryWithPresets(['full' => ['toolbar' => ['bold']]]);

        $view = $factory->create(LexicalFormType::class, null, ['preset' => 'full'])->createView();

        self::assertSame(['bold'], $view->vars['lexical_toolbar']);
    }

    /**
     * @param array<string, array<string, mixed>> $presets
     */
    private function createFactoryWithPresets(array $presets): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([new LexicalFormType($presets)], []))
            ->getFormFactory();
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

    public function testEmbedButtonIsOptionalLikeEveryOther(): void
    {
        // `iframe` is a toolbar entry like the rest: a field that wants embeds lists it,
        // one that does not simply leaves it out.
        $view = $this->factory->create(LexicalFormType::class, null, [
            'toolbar' => ['bold', '|', 'iframe'],
        ])->createView();

        self::assertSame(['bold', '|', 'iframe'], $view->vars['lexical_toolbar']);
        self::assertContains('iframe', LexicalFormType::AVAILABLE_BUTTONS);
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

    public function testRelativeLinksAreAllowedByDefaultAndPerField(): void
    {
        self::assertTrue($this->factory->create(LexicalFormType::class)->createView()->vars['lexical_allow_relative_links']);
        self::assertFalse(
            $this->factory->create(LexicalFormType::class, null, ['allow_relative_links' => false])->createView()->vars['lexical_allow_relative_links'],
        );
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
