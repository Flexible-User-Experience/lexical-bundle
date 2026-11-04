<?php

declare(strict_types=1);

namespace FlexibleUx\LexicalBundle\Tests\Browser;

use FlexibleUx\LexicalBundle\Tests\Browser\Harness\FixtureController;

/**
 * The editor as a user meets it: mounting, the stored value, what an edit stores, presets.
 */
final class EditorTest extends BrowserTestCase
{
    public function testTheEditorMountsAndLeavesTheStoredValueUntouched(): void
    {
        $client = $this->openFixture();

        self::assertSame(FixtureController::DEFAULT_VALUE, $this->textareaValue($client));
        self::assertSame('default', $client->getCrawler()->filter('.lexical')->attr('data-preset'));
        self::assertSame('false', $client->getCrawler()->filter('.lexical__editable')->attr('aria-invalid'));
        // The embed renders as a live, inert preview.
        self::assertSame('allow-scripts allow-same-origin', $client->getCrawler()->filter('.lexical__iframe iframe')->attr('sandbox'));
    }

    public function testTheAllowlistsStripDisallowedLinksAndEmbedsOnLoad(): void
    {
        $client = $this->openFixture(['value' => '<p>Keep <a href="javascript:alert(1)">this text</a>.</p><iframe src="data:text/html,hi"></iframe>']);

        // Loading changed the document, so the field takes the editor's HTML at once.
        self::assertSame('<p>Keep this text.</p>', $this->textareaValue($client));
    }

    public function testAnEditIsStoredAsSemanticHtml(): void
    {
        $client = $this->openFixture(['value' => '<p>Hello</p>']);

        $client->getMouse()->clickTo('.lexical__editable');
        $client->getKeyboard()->sendKeys(' there');
        // Bold on a caret applies to what is typed next.
        $client->getMouse()->clickTo('[data-command="bold"]');
        $client->getKeyboard()->sendKeys(' bold');
        $client->getMouse()->clickTo('[data-command="bold"]');
        $client->getKeyboard()->sendKeys('.');

        $client->wait()->until(fn () => str_contains($this->textareaValue($client), 'bold'));
        self::assertSame('<p>Hello there<strong> bold</strong>.</p>', $this->textareaValue($client));
        // Bold was toggled off again before the full stop, and the toggle says so.
        self::assertSame('false', $client->getCrawler()->filter('[data-command="bold"]')->attr('aria-pressed'));
    }

    public function testTheExportRoundTripsThroughLexicalsImport(): void
    {
        $client = $this->openFixture();

        $result = $this->asyncScript($client, <<<'JS'
            const L = await import('lexical');
            const Html = await import('@lexical/html');
            editor.update(() => {
                const root = L.$getRoot();
                root.getLastChild().setIndent(2);
                root.getFirstChild().append(L.$createTextNode(' x').toggleFormat('bold').toggleFormat('underline').toggleFormat('subscript'));
            }, { discrete: true });
            const exported = c.inputTarget.value;
            editor.update(() => {
                const root = L.$getRoot();
                root.clear();
                L.$insertNodes(Html.$generateNodesFromDOM(editor, new DOMParser().parseFromString(exported, 'text/html')));
            }, { discrete: true });
            return { exported, reimported: c.inputTarget.value };
            JS);

        self::assertSame($result['exported'], $result['reimported']);
        self::assertStringContainsString('<sub><u><strong> x</strong></u></sub>', $result['exported']);
        self::assertStringContainsString('<p style="padding-inline-start: 80px;">last paragraph</p>', $result['exported']);
        self::assertDoesNotMatchRegularExpression('/class=|white-space|<b>|data-lexical/', $result['exported']);
    }

    public function testTheDefaultToolbarIsLeanAndTheFullPresetHasEveryButton(): void
    {
        $client = $this->openFixture();
        self::assertSame(
            ['undo', 'redo', 'bold', 'italic', 'underline', 'strikethrough', 'remove-format', 'bullet', 'number', 'indent', 'outdent', 'link', 'unlink', 'iframe'],
            $client->getCrawler()->filter('.lexical__btn')->each(fn ($node) => $node->attr('data-command')),
        );

        $client = $this->openFixture(['preset' => 'full']);
        self::assertCount(25, $client->getCrawler()->filter('.lexical__btn'));
        self::assertSame('full', $client->getCrawler()->filter('.lexical')->attr('data-preset'));

        // A preset configured by the application.
        $client = $this->openFixture(['preset' => 'compact']);
        self::assertSame(['bold', 'italic', 'link', 'unlink'], $client->getCrawler()->filter('.lexical__btn')->each(fn ($node) => $node->attr('data-command')));
        self::assertStringContainsString('--lexical-min-height: 120px', (string) $client->getCrawler()->filter('.lexical')->attr('style'));
    }

    public function testUndoRestoresTheStoredStringAndTheToolbarFollowsTheHistory(): void
    {
        $client = $this->openFixture();

        self::assertSame('true', $client->getCrawler()->filter('[data-command="undo"]')->attr('disabled') ? 'true' : 'false');
        $client->getMouse()->clickTo('.lexical__editable');
        $client->getKeyboard()->sendKeys('!');
        $client->wait()->until(fn () => FixtureController::DEFAULT_VALUE !== $this->textareaValue($client));

        $client->getMouse()->clickTo('[data-command="undo"]');
        $client->wait()->until(fn () => FixtureController::DEFAULT_VALUE === $this->textareaValue($client));
        self::assertSame(FixtureController::DEFAULT_VALUE, $this->textareaValue($client));
    }

    public function testTheExtensionEventsFire(): void
    {
        $client = $this->openFixture();

        $events = $client->executeScript('return window.__events;');

        // (assertEquals: the driver returns the objects' keys in its own order.)
        self::assertEquals(
            [['name' => 'pre-connect', 'keys' => ['config', 'element']], ['name' => 'connect', 'keys' => ['editor', 'element']]],
            $events,
        );
    }
}
