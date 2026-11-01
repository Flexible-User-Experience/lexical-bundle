<?php

declare(strict_types=1);

/*
 * Router for PHP's built-in web server, which Panther starts for the browser tests:
 * everything goes through the kernel, including `/assets/*`, which AssetMapper's dev-server
 * subscriber answers from the bundle's working tree and the downloaded vendor files.
 */

use FlexibleUx\LexicalBundle\Tests\Browser\Harness\HarnessKernel;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$kernel = new HarnessKernel('dev', true);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
