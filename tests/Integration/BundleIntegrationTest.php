<?php

declare(strict_types=1);

namespace FlexibleUx\Tests\Integration;

use FlexibleUx\FlexibleUxLexicalBundle;
use FlexibleUx\Form\Type\LexicalFormType;
use FlexibleUx\Tests\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
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

        // Translated label (default locale en) and the link dialog.
        self::assertStringContainsString('Bold', $html);
        self::assertStringContainsString('class="lexical__dialog"', $html);

        // Initial HTML round-trips into the hidden textarea.
        self::assertStringContainsString('<textarea', $html);
        self::assertStringContainsString('Hello', $html);

        // Default link-scheme allowlist reaches the controller.
        self::assertStringContainsString('data-lexical-allowed-link-schemes-value=', $html);
        self::assertStringContainsString('["http","https","mailto","tel"]', html_entity_decode($html));
    }

    public function testDefaultToolbarRendersEveryButton(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $view = $container->get('test.form.factory')->create(LexicalFormType::class)->createView();
        $html = $container->get('test.twig')->createTemplate('{{ form_widget(form) }}')->render(['form' => $view]);

        // Rendering also proves every button's `lexical:*` icon resolves: UX Icons throws
        // when an icon is missing (ignore_not_found defaults to false).
        foreach (LexicalFormType::DEFAULT_TOOLBAR as $command) {
            self::assertStringContainsString(\sprintf('data-command="%s"', $command), $html);
        }

        // Four groups (text · list · indent · link) mean three separators.
        self::assertSame(3, substr_count($html, 'lexical__sep'));
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
