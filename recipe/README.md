# Flex recipe

This directory holds the Symfony Flex recipe for the bundle, in the layout the
[symfony/recipes-contrib](https://github.com/symfony/recipes-contrib) repository expects under
`flexible-ux/lexical-bundle/1.1/` (the configuration keys it ships exist since bundle 1.1.0):

| File | Purpose |
|------|---------|
| `manifest.json` | Registers `FlexibleUxLexicalBundle` for all environments, copies `config/` into the application and declares the `lexical` / `lexical-bundle` aliases. |
| `config/packages/flexible_ux_lexical.yaml` | The application-wide defaults, all commented out so the bundle's own defaults apply until the application overrides one. |
| `post-install.txt` | What `composer require` prints once the recipe is applied. |

Flex itself takes care of the front-end from the bundle's `assets/package.json`: the Lexical packages
land in `importmap.php` and the `lexical` controller in `assets/controllers.json`, with or without this
recipe.

## Submitting or updating it

1. Fork `symfony/recipes-contrib`, copy this directory to `flexible-ux/lexical-bundle/1.1/` (the
   version directory is the lowest bundle version the recipe applies to).
2. Open a pull request; the recipes bot validates the manifest and builds a `flex/pull-<n>` branch
   you can test with
   `composer config extra.symfony.endpoint "https://api.github.com/repos/symfony/recipes-contrib/contents/index.json?ref=flex/pull-<n>"`
   in a sample application before it is merged.
3. Until the recipe is merged, Flex falls back to its auto-generated recipe for packages of type
   `symfony-bundle`: the bundle class is still registered in `config/bundles.php`, only the
   configuration file and the post-install notes are missing.

Keep the two copies in sync: a change to the configuration reference belongs here first, then in
the pull request to recipes-contrib.
