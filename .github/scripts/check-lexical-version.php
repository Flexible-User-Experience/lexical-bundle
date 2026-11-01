<?php

declare(strict_types=1);

/*
 * Compares the Lexical constraint the bundle declares in assets/package.json with the latest
 * release on npm. Exits non-zero - and prints a GitHub Actions error annotation - when a
 * release the constraint does not cover is out, which is the cue for a bump. Dependabot
 * cannot do this: the constraint lives in the bundle's own package.json section, not in a
 * dependency manifest it understands. No Composer dependencies, so the workflow needs none.
 */

$package = json_decode((string) file_get_contents(__DIR__.'/../../assets/package.json'), true, 512, \JSON_THROW_ON_ERROR);
$constraint = $package['symfony']['importmap']['lexical'] ?? null;
if (!\is_string($constraint) || !preg_match('/^\^(\d+)\.(\d+)\.(\d+)$/', $constraint, $declared)) {
    fwrite(\STDERR, "Expected a caret constraint for \"lexical\" in assets/package.json, got ".var_export($constraint, true)."\n");
    exit(2);
}

$context = stream_context_create(['http' => ['timeout' => 20, 'header' => "Accept: application/json\r\nUser-Agent: flexible-ux/lexical-bundle version check\r\n"]]);
$registry = json_decode((string) file_get_contents('https://registry.npmjs.org/lexical/latest', false, $context), true, 512, \JSON_THROW_ON_ERROR);
$latest = $registry['version'] ?? null;
if (!\is_string($latest) || !preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $latest, $released)) {
    fwrite(\STDERR, "Could not read the latest lexical version from npm: ".var_export($latest, true)."\n");
    exit(2);
}

[, $major, $minor, $patch] = array_map('intval', $declared);
[, $latestMajor, $latestMinor, $latestPatch] = array_map('intval', $released);

// Caret semantics: `^0.53.0` allows >=0.53.0 <0.54.0 (the leftmost non-zero part is frozen),
// `^1.2.3` allows >=1.2.3 <2.0.0.
$satisfied = 0 === $major
    ? $latestMajor === 0 && $latestMinor === $minor && $latestPatch >= $patch
    : $latestMajor === $major && [$latestMinor, $latestPatch] >= [$minor, $patch];

if ($satisfied) {
    echo "lexical $latest satisfies $constraint; nothing to do.\n";
    exit(0);
}

echo "::error title=Lexical $latest is out::assets/package.json pins the Lexical packages to $constraint, which does not cover $latest. Read https://github.com/facebook/lexical/releases/tag/v$latest, bump the nine constraints and the documented importmap:require command, and verify with the browser suite.\n";
exit(1);
