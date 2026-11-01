<?php

declare(strict_types=1);

namespace FlexibleUx\LexicalBundle\Tests\Integration;

use FlexibleUx\LexicalBundle\FlexibleUxLexicalBundle;
use FlexibleUx\LexicalBundle\Form\Type\LexicalFormType;
use FlexibleUx\LexicalBundle\Tests\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\AssetMapper\ImportMap\JavaScriptImport;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Environment;

/**
 * Boots a real kernel and renders the field to prove the whole bundle is wired: the
 * tagged form-type service, the auto-prepended form theme, the `ux_icon('lexical:*')`
 * icon set, translations and the editor markup the Stimulus controller binds to.
 */
final class BundleIntegrationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Booting the kernel installs Symfony's exception handler; restore it so PHPUnit
        // does not flag the test as risky for leaving a handler in place.
        restore_exception_handler();
    }

    public function testFormTypeServiceIsRegisteredAndTagged(): void
    {
        self::bootKernel();

        // Resolvable by class through the form factory ⇒ tagged `form.type`.
        self::assertInstanceOf(
            LexicalFormType::class,
            self::getContainer()->get('flexible_ux_lexical.form.type.lexical'),
        );
    }

    public function testFormThemeIsPrepended(): void
    {
        self::bootKernel();

        // The `ux_icons` icon set is exercised by testWidgetRendersEditorMarkup (the
        // rendered `<svg` only resolves when the `lexical` set path is registered).
        self::assertContains(
            '@FlexibleUxLexical/form/lexical_widget.html.twig',
            self::getContainer()->getParameter('twig.form.resources'),
        );
    }

    public function testControllerIsMappedForAssetMapper(): void
    {
        self::bootKernel();

        // Regression guard for the `framework.asset_mapper.paths` prepend: without it,
        // AssetMapper cannot resolve the vendored controller and Stimulus fails with
        // "Could not find an asset mapper path that points to the lexical controller".
        $controllerPath = (new FlexibleUxLexicalBundle())->getPath().'/assets/src/controller.js';
        $asset = self::getContainer()->get('test.asset_mapper')->getAssetFromSourcePath($controllerPath);

        self::assertNotNull($asset, 'The bundle must register its assets/ directory with AssetMapper.');
        self::assertStringStartsWith('@flexible-ux/lexical-bundle/', $asset->logicalPath);
    }

    public function testControllerRelativeImportsAreSeenByAssetMapper(): void
    {
        self::bootKernel();

        // Regression guard for the shape of the controller's own `import` statements.
        // AssetMapper discovers imports with a regex whose named-import clause is
        // `[\w\s{},*]` — no `$` — so `import { $createIframeNode } from './iframe-node.js'`
        // is invisible to it: no importmap entry is generated for the relative module, the
        // browser requests the undigested path and the whole controller fails to load with
        // "Failed to fetch dynamically imported module". Every relative import must
        // therefore be written in a form the compiler matches (a namespace import).
        $controllerPath = (new FlexibleUxLexicalBundle())->getPath().'/assets/src/controller.js';
        $asset = self::getContainer()->get('test.asset_mapper')->getAssetFromSourcePath($controllerPath);

        $imported = array_map(
            static fn (JavaScriptImport $import): string => $import->assetLogicalPath,
            $asset->getJavaScriptImports(),
        );

        self::assertContains('@flexible-ux/lexical-bundle/src/iframe-node.js', $imported);
        self::assertContains('@flexible-ux/lexical-bundle/src/html-export.js', $imported);
        self::assertContains('@flexible-ux/lexical-bundle/styles/lexical.css', $imported);
    }

    public function testWidgetRendersEditorMarkup(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var FormFactoryInterface $factory */
        $factory = $container->get('test.form.factory');
        /** @var Environment $twig */
        $twig = $container->get('test.twig');

        $view = $factory
            ->create(LexicalFormType::class, '<p>Hello</p>', ['toolbar' => ['bold', 'link', 'unlink'], 'height' => '320px'])
            ->createView();
        $html = $twig->createTemplate('{{ form_widget(form) }}')->render(['form' => $view]);

        // Stimulus wiring the controller depends on.
        self::assertStringContainsString('data-controller="lexical"', $html);
        self::assertStringContainsString('data-lexical-target="input"', $html);
        self::assertStringContainsString('data-lexical-invalid-url-message-value=', $html);
        self::assertStringContainsString('--lexical-min-height: 320px;', $html);
        self::assertStringContainsString('data-command="bold"', $html);
        self::assertStringContainsString('data-command="unlink"', $html);

        // Icon resolved from the shipped `lexical` UX Icons set.
        self::assertStringContainsString('<svg', $html);

        // Translated label (default locale en) and the three modals (link + source + iframe).
        self::assertStringContainsString('Bold', $html);
        self::assertStringContainsString('class="lexical__dialog"', $html);

        // The slot the controller fills with the browser's validation message when the form
        // is submitted with an empty required field (the hidden textarea cannot show it).
        self::assertStringContainsString('<p class="lexical__error" role="alert" hidden data-lexical-target="error"></p>', $html);
        self::assertStringContainsString('data-lexical-target="sourceDialog"', $html);
        self::assertStringContainsString('data-lexical-target="iframeDialog"', $html);

        // Initial HTML round-trips into the hidden textarea.
        self::assertStringContainsString('<textarea', $html);
        self::assertStringContainsString('Hello', $html);

        // Default link-scheme allowlist reaches the controller.
        self::assertStringContainsString('data-lexical-allowed-link-schemes-value=', $html);
        self::assertStringContainsString('["http","https","mailto","tel"]', html_entity_decode($html));
    }

    public function testRequiredAndPlaceholderReachTheTextarea(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        // The controller mirrors these two textarea attributes onto the editable surface
        // (aria-required, the placeholder) and listens to the textarea's `invalid` event, so
        // the standard form options have to reach the textarea exactly as for TextareaType.
        $view = $container->get('test.form.factory')
            ->create(LexicalFormType::class, null, ['required' => true, 'attr' => ['placeholder' => 'Write something...']])
            ->createView();
        $html = $container->get('test.twig')->createTemplate('{{ form_widget(form) }}')->render(['form' => $view]);

        self::assertMatchesRegularExpression('/<textarea[^>]* required="required"[^>]*>/', $html);
        self::assertMatchesRegularExpression('/<textarea[^>]* placeholder="Write something\.\.\."[^>]*>/', $html);
    }

    public function testDefaultToolbarRendersItsButtons(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $view = $container->get('test.form.factory')->create(LexicalFormType::class)->createView();
        $html = $container->get('test.twig')->createTemplate('{{ form_widget(form) }}')->render(['form' => $view]);

        foreach (LexicalFormType::DEFAULT_TOOLBAR as $entry) {
            if (LexicalFormType::SEPARATOR === $entry) {
                continue;
            }
            self::assertStringContainsString(\sprintf('data-command="%s"', $entry), $html);
        }
        // Nothing the lean default leaves out sneaks in.
        foreach (['cut', 'copy', 'paste', 'paste-word', 'subscript', 'superscript', 'source'] as $entry) {
            self::assertStringNotContainsString(\sprintf('data-command="%s"', $entry), $html);
        }
        self::assertStringNotContainsString('data-command="align-', $html);

        // The default groups its buttons with five separators
        // (history · text · list · indent · link · embed).
        self::assertSame(5, substr_count($html, 'lexical__sep'));
        self::assertStringContainsString('class="lexical" data-preset="default"', $html);
    }

    public function testFullPresetRendersEveryButton(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $view = $container->get('test.form.factory')->create(LexicalFormType::class, null, ['preset' => 'full'])->createView();
        $html = $container->get('test.twig')->createTemplate('{{ form_widget(form) }}')->render(['form' => $view]);

        // Rendering also proves every button's `lexical:*` icon resolves: UX Icons throws
        // when an icon is missing (ignore_not_found defaults to false).
        foreach (LexicalFormType::AVAILABLE_BUTTONS as $entry) {
            self::assertStringContainsString(\sprintf('data-command="%s"', $entry), $html);
        }

        // Eight groups, seven separators
        // (history · clipboard · text · align · list · indent · link · embed + source).
        self::assertSame(7, substr_count($html, 'lexical__sep'));
        self::assertStringContainsString('class="lexical" data-preset="full"', $html);
    }

    public function testConfiguredPresetsReachTheFormType(): void
    {
        // Stands in for a config/packages/flexible_ux_lexical.yaml declaring named presets.
        $kernel = new TestKernel('test', false, [
            'height' => '300px',
            'presets' => [
                'compact' => ['toolbar' => ['bold', '|', 'link'], 'height' => '120px'],
                'strict' => ['allowed_link_schemes' => ['https']],
            ],
        ]);
        $kernel->boot();
        $factory = $kernel->getContainer()->get('test.form.factory');

        $compact = $factory->create(LexicalFormType::class, null, ['preset' => 'compact'])->createView();
        self::assertSame(['bold', '|', 'link'], $compact->vars['lexical_toolbar']);
        self::assertSame('120px', $compact->vars['lexical_height']);
        self::assertSame(['http', 'https', 'mailto', 'tel'], $compact->vars['lexical_allowed_link_schemes']);

        // A preset only overrides what it sets; the rest comes from the application defaults.
        $strict = $factory->create(LexicalFormType::class, null, ['preset' => 'strict'])->createView();
        self::assertSame(LexicalFormType::DEFAULT_TOOLBAR, $strict->vars['lexical_toolbar']);
        self::assertSame('300px', $strict->vars['lexical_height']);
        self::assertSame(['https'], $strict->vars['lexical_allowed_link_schemes']);

        // The built-in full preset inherits the application defaults too.
        $full = $factory->create(LexicalFormType::class, null, ['preset' => 'full'])->createView();
        self::assertSame(LexicalFormType::FULL_TOOLBAR, $full->vars['lexical_toolbar']);
        self::assertSame('300px', $full->vars['lexical_height']);

        $kernel->shutdown();
    }

    public function testDefaultPresetIsConfiguredAtTheTopLevelOnly(): void
    {
        $kernel = new TestKernel('test', false, ['presets' => ['default' => ['height' => '1px']]]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/"default" preset is configured through the top-level/');

        $kernel->boot();
    }

    public function testUnknownToolbarEntryInAPresetFailsAtBoot(): void
    {
        $kernel = new TestKernel('test', false, ['presets' => ['compact' => ['toolbar' => ['bold', 'blink']]]]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/"compact" preset: Unknown toolbar entry "blink"/');

        $kernel->boot();
    }

    public function testAlignmentButtonsAreOptional(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        // A consumer that does not use alignment lists a toolbar without the align
        // entries — nothing alignment-related reaches the markup.
        $view = $container->get('test.form.factory')
            ->create(LexicalFormType::class, null, [
                'toolbar' => ['bold', 'italic', '|', 'bullet', 'number', '|', 'link', 'unlink'],
            ])
            ->createView();
        $html = $container->get('test.twig')->createTemplate('{{ form_widget(form) }}')->render(['form' => $view]);

        self::assertStringNotContainsString('data-command="align-', $html);
        self::assertStringContainsString('data-command="bold"', $html);
    }

    public function testAlignmentButtonsCanBeCherryPicked(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        // The four buttons are independent entries, not a bundle deal.
        $view = $container->get('test.form.factory')
            ->create(LexicalFormType::class, null, [
                'toolbar' => ['bold', '|', 'align-left', 'align-center'],
            ])
            ->createView();
        $html = $container->get('test.twig')->createTemplate('{{ form_widget(form) }}')->render(['form' => $view]);

        self::assertStringContainsString('data-command="align-left"', $html);
        self::assertStringContainsString('data-command="align-center"', $html);
        self::assertStringNotContainsString('data-command="align-right"', $html);
        self::assertStringNotContainsString('data-command="align-justify"', $html);
    }

    public function testThemeFallbacksResolveTheFormTypeConstants(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        // The fallbacks behind `|default(constant('…LexicalFormType::…'))` are what a custom
        // form theme gets when it renders the block without the view vars buildView() sets.
        // Twig evaluates a default filter's argument on every render, so a wrong FQCN already
        // breaks every rendering test; what only this one checks is that the fallback branch
        // hands back the same values the form type uses.
        $view = $container->get('test.form.factory')
            ->create(LexicalFormType::class, null, ['toolbar' => ['bold', '|', 'italic']])
            ->createView();
        unset($view->vars['lexical_height'], $view->vars['lexical_separator']);
        $html = $container->get('test.twig')->createTemplate('{{ form_widget(form) }}')->render(['form' => $view]);

        self::assertStringContainsString(\sprintf('--lexical-min-height: %s;', LexicalFormType::DEFAULT_HEIGHT), $html);
        self::assertSame(1, substr_count($html, 'lexical__sep'));
    }

    public function testBundleConfigurationProvidesApplicationWideDefaults(): void
    {
        // Stands in for a config/packages/flexible_ux_lexical.yaml in the host application.
        $kernel = new TestKernel('test', false, [
            'toolbar' => ['bold', '|', 'link'],
            'height' => '444px',
            'allowed_link_schemes' => ['HTTPS:'],
        ]);
        $kernel->boot();
        $factory = $kernel->getContainer()->get('test.form.factory');

        $view = $factory->create(LexicalFormType::class)->createView();
        self::assertSame(['bold', '|', 'link'], $view->vars['lexical_toolbar']);
        self::assertSame('444px', $view->vars['lexical_height']);
        self::assertSame(['https'], $view->vars['lexical_allowed_link_schemes']);

        // Per-field options still override the application-wide defaults.
        $overridden = $factory->create(LexicalFormType::class, null, ['height' => '99px'])->createView();
        self::assertSame('99px', $overridden->vars['lexical_height']);
        self::assertSame(['bold', '|', 'link'], $overridden->vars['lexical_toolbar']);

        $kernel->shutdown();
    }

    public function testConfiguredToolbarGroupingReachesTheRenderedMarkup(): void
    {
        $kernel = new TestKernel('test', false, ['toolbar' => ['bold', 'italic', '|', 'link']]);
        $kernel->boot();
        $container = $kernel->getContainer();

        $view = $container->get('test.form.factory')->create(LexicalFormType::class)->createView();
        $html = $container->get('test.twig')->createTemplate('{{ form_widget(form) }}')->render(['form' => $view]);

        self::assertSame(1, substr_count($html, 'lexical__sep'));
        self::assertStringContainsString('data-command="bold"', $html);
        self::assertStringNotContainsString('data-command="bullet"', $html);
        // The application-wide toolbar is also how alignment is opted out for every
        // field at once — no align entry in the config, no align button in the markup.
        self::assertStringNotContainsString('data-command="align-', $html);

        $kernel->shutdown();
    }

    public function testAllowedLinkSchemesReachTheStimulusValue(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $view = $container->get('test.form.factory')
            ->create(LexicalFormType::class, null, ['allowed_link_schemes' => ['https', 'mailto']])
            ->createView();
        $html = $container->get('test.twig')->createTemplate('{{ form_widget(form) }}')->render(['form' => $view]);

        self::assertStringContainsString('data-lexical-allowed-link-schemes-value=', $html);
        self::assertStringContainsString('["https","mailto"]', html_entity_decode($html));
    }
}
