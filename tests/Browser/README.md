# Browser tests

End-to-end tests of the editor in a real Chrome, driven by [Symfony Panther](https://github.com/symfony/panther)
against the harness application in `Harness/`: a consumer of the bundle with AssetMapper, its
importmap, StimulusBundle's loader and UX Icons wired the way `composer require` wires them, serving
one page whose form field is configured from the query string (see `Harness/FixtureController.php`).

```console
composer install
vendor/bin/bdi detect drivers            # downloads a chromedriver matching the installed Chrome into drivers/
php tests/Browser/Harness/build.php      # importmap:require of the Lexical packages into the harness (needs network)
vendor/bin/phpunit --testsuite browser
```

`vendor/bin/phpunit` alone runs the default suite (unit and integration) and leaves these out; CI
runs them in their own job. A failing test leaves a screenshot in `var/panther/screenshots/`.

To try the bundle against another Lexical version, rebuild the harness with a constraint:
`php tests/Browser/Harness/build.php '^0.54.0'`, then run the suite again.
