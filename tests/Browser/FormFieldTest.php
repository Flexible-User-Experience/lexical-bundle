<?php

declare(strict_types=1);

namespace FlexibleUx\LexicalBundle\Tests\Browser;

/**
 * The field as a form control: required, placeholder, label, what the server receives.
 */
final class FormFieldTest extends BrowserTestCase
{
    public function testAnEmptyRequiredFieldBlocksTheSubmissionWithAVisibleMessage(): void
    {
        $client = $this->openFixture(['value' => '', 'placeholder' => 'Write something...']);

        self::assertSame('true', $client->getCrawler()->filter('.lexical__editable')->attr('aria-required'));
        self::assertSame('Write something...', $client->getCrawler()->filter('.lexical__editable')->attr('data-placeholder'));
        self::assertCount(1, $client->getCrawler()->filter('.lexical.lexical--empty'));

        $client->getMouse()->clickTo('#harness-submit');
        $client->waitForVisibility('.lexical__error');

        self::assertCount(0, $client->getCrawler()->filter('#submitted'), 'the form must not have been submitted');
        self::assertNotSame('', trim($client->getCrawler()->filter('.lexical__error')->text()));
        self::assertSame('true', $client->getCrawler()->filter('.lexical__editable')->attr('aria-invalid'));
        self::assertTrue($this->script($client, 'return document.activeElement === c.editableTarget;'));

        // The first keystroke clears the message, and the form goes through.
        $client->getKeyboard()->sendKeys('Hi');
        $client->waitForInvisibility('.lexical__error');
        self::assertCount(0, $client->getCrawler()->filter('.lexical--empty'));
        $client->getMouse()->clickTo('#harness-submit');
        $client->waitFor('#submitted');
        self::assertSame('<p>Hi</p>', $client->getCrawler()->filter('#submitted')->text());
    }

    public function testTheLabelNamesTheSurfaceAndFocusesTheEditor(): void
    {
        $client = $this->openFixture();

        $labelId = $client->getCrawler()->filter('.lexical__editable')->attr('aria-labelledby');
        self::assertSame('Body text', trim($client->getCrawler()->filter('#'.$labelId)->text()));

        $client->getMouse()->clickTo('#'.$labelId);
        self::assertTrue($this->script($client, 'return document.activeElement === c.editableTarget;'));
    }

    public function testAChangedSubmissionIsSanitizedAndAnUntouchedOneIsNot(): void
    {
        $legacy = '<p>Legacy</p><table><tr><td>cell</td></tr></table>';
        $client = $this->openFixture(['value' => $legacy]);

        // The editor's model already dropped the table from the document, but the field still
        // holds the stored string: submitted untouched, it is stored untouched.
        self::assertSame($legacy, $this->textareaValue($client));
        $client->getMouse()->clickTo('#harness-submit');
        $client->waitFor('#submitted');
        self::assertSame($legacy, $client->getCrawler()->filter('#submitted')->text());

        // A submission the editor did not produce is cut down to what it would have produced.
        $client = $this->openFixture(['value' => '<p>Hello</p>']);
        $this->script($client, 'c.inputTarget.value = \'<p onclick="x()">Edited<script>alert(1)</script></p><div><b>old</b></div>\';');
        $client->getMouse()->clickTo('#harness-submit');
        $client->waitFor('#submitted');
        self::assertSame('<p>Edited</p><b>old</b>', $client->getCrawler()->filter('#submitted')->text());
    }

    public function testStoredContentIsRenderedThroughTheSanitizerAndTheContentStylesheet(): void
    {
        $client = $this->openFixture(['value' => '<p>Hi <strong>there</strong></p><ul><li>one</li></ul><script>alert(1)</script>']);

        self::assertCount(1, $client->getCrawler()->filter('#rendered > .lexical-content'));
        self::assertSame(
            '<p>Hi <strong>there</strong></p><ul><li>one</li></ul>',
            $client->executeScript('return document.querySelector("#rendered > .lexical-content").innerHTML;'),
        );
        // The same stylesheet styles the page and the editor's surface.
        self::assertSame('disc', $client->executeScript('return getComputedStyle(document.querySelector("#rendered ul")).listStyleType;'));
        self::assertSame('disc', $client->executeScript('return getComputedStyle(document.querySelector(".lexical__editable ul")).listStyleType;'));
    }
}
