<?php

declare(strict_types=1);

namespace FlexibleUx\LexicalBundle\Tests\Browser;

use FlexibleUx\LexicalBundle\Tests\Browser\Harness\HarnessKernel;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;

/**
 * Base of the browser tests: starts PHP's built-in server on the harness (see
 * `Harness/README.md`) once per process, opens the fixture page with the field options
 * given as query parameters and waits for the editor to mount.
 */
abstract class BrowserTestCase extends PantherTestCase
{
    /**
     * Panther boots a kernel alongside the web server (for `static::getContainer()`); the
     * harness kernel is the one to boot, with the environment the phpunit configuration sets.
     */
    protected static function getKernelClass(): string
    {
        return HarnessKernel::class;
    }

    protected static function createHarnessClient(): Client
    {
        return static::createPantherClient([
            'webServerDir' => __DIR__.'/Harness/project/public',
            'router' => __DIR__.'/Harness/router.php',
            'hostname' => '127.0.0.1',
            'port' => 9876,
        ]);
    }

    /**
     * @param array<string, string|int|bool> $query the fixture's field options (see FixtureController)
     */
    protected function openFixture(array $query = []): Client
    {
        $client = static::createHarnessClient();
        $client->request('GET', '/'.([] === $query ? '' : '?'.http_build_query($query)));
        $client->waitFor('.lexical--ready');

        return $client;
    }

    /**
     * Runs a script in the page with the field's controller bound to `c` and its editor to
     * `editor`; the script's `return` value comes back JSON-decoded by the driver.
     */
    protected function script(Client $client, string $body): mixed
    {
        return $client->executeScript(
            'const el = document.querySelector(\'[data-controller="lexical"]\');'
            .'const c = window.__harness.app.getControllerForElementAndIdentifier(el, "lexical");'
            .'const editor = c.editor;'
            .$body,
        );
    }

    /**
     * Like {@see script()}, for scripts that need `await`: the body runs inside an async
     * function and the resolved value comes back.
     */
    protected function asyncScript(Client $client, string $body): mixed
    {
        return $client->executeAsyncScript(
            'const done = arguments[arguments.length - 1];'
            .'(async () => {'
            .'const el = document.querySelector(\'[data-controller="lexical"]\');'
            .'const c = window.__harness.app.getControllerForElementAndIdentifier(el, "lexical");'
            .'const editor = c.editor;'
            .$body
            .'})().then(done, (error) => done({ error: String(error) }));',
        );
    }

    protected function textareaValue(Client $client): string
    {
        return (string) $this->script($client, 'return c.inputTarget.value;');
    }
}
