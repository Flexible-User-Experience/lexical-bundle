<?php

declare(strict_types=1);

namespace FlexibleUx\LexicalBundle\Tests\Browser;

/**
 * The link modal, relative links and what pasting brings in.
 */
final class LinksAndPasteTest extends BrowserTestCase
{
    public function testTheLinkModalAcceptsRelativeLinksAndRejectsTheRest(): void
    {
        $client = $this->openFixture(['value' => '<p>Visit the about page</p>']);

        self::assertSame(['/about' => true, '#top' => true, 'https://symfony.com' => true, '//evil.test/x' => false, 'javascript:alert(1)' => false], $this->tryLinks($client));
        // Each accepted URL replaced the previous link on the same words; the last one stands.
        self::assertStringContainsString('<a href="https://symfony.com">Vis</a>', $this->textareaValue($client));

        $client = $this->openFixture(['value' => '<p>Visit the about page</p>', 'allow_relative_links' => 0]);
        self::assertSame(['/about' => false, '#top' => false, 'https://symfony.com' => true, '//evil.test/x' => false, 'javascript:alert(1)' => false], $this->tryLinks($client));
    }

    public function testAnOfficePasteIsCleanedAndItsListsNestedWithoutTheWordButton(): void
    {
        $client = $this->openFixture(['value' => '<p>Start</p>', 'preset' => 'full']);
        $office = '<html xmlns:o="urn:schemas-microsoft-com:office:office"><body><!--[if gte mso 9]><xml><w:WordDocument/></xml><![endif]-->'
            .'<p class="MsoNormal">Intro<o:p></o:p></p>'
            .'<p class="MsoListParagraphCxSpFirst" style="mso-list:l0 level1 lfo1"><span style="mso-list:Ignore">1.<span>&nbsp;</span></span>First</p>'
            .'<p class="MsoListParagraphCxSpMiddle" style="mso-list:l0 level2 lfo1"><span style="mso-list:Ignore">a.<span>&nbsp;</span></span>Nested</p>'
            .'<p class="MsoListParagraphCxSpLast" style="mso-list:l0 level1 lfo1"><span style="mso-list:Ignore">2.<span>&nbsp;</span></span>Second</p></body></html>';

        $value = $this->asyncScript($client, \sprintf(<<<'JS'
            const L = await import('lexical');
            const html = %s;
            const items = [{ types: ['text/html', 'text/plain'], getType: async (t) => new Blob([t === 'text/html' ? html : 'Intro'], { type: t }) }];
            Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { read: async () => items, readText: async () => 'Intro' } });
            editor.update(() => { L.$getRoot().getLastChild().selectEnd(); }, { discrete: true });
            await c.command({ currentTarget: { dataset: { command: 'paste' } } });
            await new Promise((r) => setTimeout(r, 300));
            return c.inputTarget.value;
            JS, json_encode($office)));

        self::assertSame('<p>StartIntro</p><ol><li value="1">First<ol><li value="1">Nested</li></ol></li><li value="2">Second</li></ol>', $value);
    }

    public function testACopiedEmbedPastesBackThroughLexicalsClipboardFormat(): void
    {
        $client = $this->openFixture(['preset' => 'full']);

        // Select the embed with a real click, copy it with the toolbar button (user activation
        // is what lets a script-less copy reach the clipboard), then paste the captured payload.
        $client->getMouse()->clickTo('.lexical__iframe iframe');
        $client->waitFor('.lexical__iframe.is-selected');
        $client->getMouse()->clickTo('[data-command="copy"]');
        $client->wait()->until(fn () => 0 < (int) $client->executeScript('return window.__clip.length;'));

        $value = $this->asyncScript($client, <<<'JS'
            const L = await import('lexical');
            const Clip = await import('@lexical/clipboard');
            const dt = new DataTransfer();
            dt.setData('application/x-lexical-editor', window.__clip.at(-1));
            editor.update(() => { L.$getRoot().getFirstChild().selectStart(); Clip.$insertDataTransferForRichText(dt, L.$getSelection(), editor); }, { discrete: true });
            return c.inputTarget.value;
            JS);

        self::assertSame(2, substr_count($value, '<iframe'));
        self::assertStringStartsWith('<iframe src="https://www.youtube.com/embed/', $value);
    }

    /**
     * Tries each URL in the link modal on the first three characters of the first paragraph;
     * true when the modal accepted it and closed.
     *
     * @return array<string, bool>
     */
    private function tryLinks(\Symfony\Component\Panther\Client $client): array
    {
        $result = [];
        foreach (['/about', '#top', 'https://symfony.com', '//evil.test/x', 'javascript:alert(1)'] as $url) {
            $result[$url] = $this->asyncScript($client, \sprintf(<<<'JS'
                const L = await import('lexical');
                editor.update(() => { L.$getRoot().getFirstChild().getFirstChild().select(0, 3); }, { discrete: true });
                c.command({ currentTarget: { dataset: { command: 'link' } } });
                await new Promise((r) => setTimeout(r, 150));
                c.urlInputTarget.value = %s;
                c.confirmLink();
                await new Promise((r) => setTimeout(r, 150));
                const accepted = !c.dialogTarget.open;
                if (!accepted) { c.closeDialog(); }
                return accepted;
                JS, json_encode($url)));
        }

        return $result;
    }
}
