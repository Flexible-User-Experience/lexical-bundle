# FlexibleUxLexicalBundle

`LexicalFormType` is a Symfony form field that turns a `<textarea>` into a small rich-text editor
powered by Meta's [Lexical](https://lexical.dev). It is wired for AssetMapper (no Node build step),
Stimulus and UX Icons, and it stores plain HTML.

- [Installation & setup](#installation--setup)
- [Usage](#usage)
- [Options](#options)
- [Architecture](#architecture)
- [Customising](#customising)
  - [The form theme](#the-form-theme)
  - [The icons](#the-icons)
  - [The styles](#the-styles)
  - [Translations](#translations)
- [Security notes](#security-notes)
- [Troubleshooting](#troubleshooting)

## Installation & setup

See the [README](../README.md) for the full steps. In short:

```console
composer require flexible-ux/lexical-bundle
```

With Symfony Flex this also adds the Lexical packages to `importmap.php` and enables the `lexical`
controller in `assets/controllers.json`. Without Flex, do both manually:

```console
php bin/console importmap:require lexical @lexical/rich-text @lexical/html @lexical/list @lexical/link @lexical/history @lexical/utils
```

```json
{
    "controllers": {
        "@flexible-ux/lexical-bundle": {
            "lexical": { "enabled": true, "fetch": "lazy" }
        }
    }
}
```

Then register the bundle in `config/bundles.php` (no Flex recipe is shipped yet):

```php
FlexibleUx\FlexibleUxLexicalBundle::class => ['all' => true],
```

The bundle auto-registers its AssetMapper path, its form theme and its icon set through
`prependExtension()`, so no changes to `config/packages/twig.yaml` or `config/packages/ux_icons.yaml`
are required.

## Usage

```php
use FlexibleUx\Form\Type\LexicalFormType;

$builder->add('description', LexicalFormType::class, [
    'toolbar' => ['bold', 'italic', 'bullet', 'number', 'link', 'unlink'],
    'height'  => '320px',
]);
```

Because the type extends `TextareaType`, the submitted value is the editor's HTML as a string; map it
to a `string`/`text` property like any textarea.

## Options

| Option                 | Type       | Default                           | Description                               |
|------------------------|------------|-----------------------------------|-------------------------------------------|
| `toolbar`              | `string[]` | all 12 buttons, in four groups    | Ordered entries: button names and `\|` separators. |
| `height`               | `string`   | `'200px'`                         | Minimum editable height (any CSS length). |
| `allowed_link_schemes` | `string[]` | `['http','https','mailto','tel']` | URL schemes the link modal accepts.       |

`allowed_link_schemes` entries may be written with or without the trailing colon and in any case
(`https`, `https:` and `HTTPS:` are equivalent). The list is normalised server-side and handed to the
Stimulus controller as the `allowedLinkSchemes` value, so validation happens in the link modal before a
link is inserted:

```php
$builder->add('description', LexicalFormType::class, [
    'allowed_link_schemes' => ['https', 'mailto'],
]);
```

The button names are `bold`, `italic`, `underline`, `strikethrough`, `subscript`, `superscript`,
`bullet`, `number`, `indent`, `outdent`, `link` and `unlink`.

### Grouping the toolbar

Grouping is **not** fixed by the bundle. The toolbar renders the entries in exactly the order given,
and the special entry `|` (`LexicalFormType::SEPARATOR`) draws a divider wherever you place one:

```php
'toolbar' => ['bold', 'italic', '|', 'bullet', 'number', '|', 'link'],
'toolbar' => ['bold', 'link'],   // no separators at all
```

Redundant separators — leading, trailing or repeated — are dropped during option normalisation, so a
hand-written list can never render a stray or doubled divider. An entry that is neither a known button
nor `|` raises an `InvalidOptionsException` naming the offending entry and listing the valid buttons.

## Application-wide defaults

All three options can be defaulted once for the whole application. Per-field options still win:

```yaml
# config/packages/flexible_ux_lexical.yaml
flexible_ux_lexical:
    toolbar: ['bold', 'italic', '|', 'bullet', 'number', '|', 'link', 'unlink']
    height: '320px'
    allowed_link_schemes: ['https']
```

The values are bound to the `flexible_ux_lexical.toolbar`, `.height` and `.allowed_link_schemes`
container parameters and injected into the form type, so `config:dump-reference flexible_ux_lexical`
documents them and an unknown key fails at container compile time.

`subscript` and `superscript` are Lexical text formats and toggle like the other text buttons.
`indent` and `outdent` are one-shot block actions (Lexical's `INDENT_CONTENT_COMMAND` /
`OUTDENT_CONTENT_COMMAND`), so they never render as "active"; Lexical stores the result as an inline
`padding-inline-start` on the block, which is why indentation survives into the saved HTML without
any stylesheet of its own.

## Architecture

| Layer | File | Responsibility |
|-------|------|----------------|
| PHP   | `src/Form/Type/LexicalFormType.php` | Declares options, exposes `lexical_toolbar` / `lexical_height` view vars. |
| Bundle| `src/FlexibleUxLexicalBundle.php`   | Registers the tagged service, prepends the form theme + icon set. |
| HTML  | `templates/form/lexical_widget.html.twig` | Toolbar, editable surface, link `<dialog>`. |
| JS    | `assets/src/controller.js`          | Mounts Lexical, syncs the textarea, drives the toolbar. |
| CSS   | `assets/styles/lexical.css`         | Editor chrome (imported by the controller). |
| Icons | `assets/icons/*.svg`                | Lucide glyphs served as `lexical:<name>`. |

The Stimulus controller reads the textarea's HTML into Lexical on `connect()` and writes HTML back on
every update; the textarea is what the browser submits, so server-side nothing special is needed.

## Customising

### The form theme

Override the `lexical_widget` block by registering your own form theme **after** the bundle's (later
themes win). For example, in `config/packages/twig.yaml`:

```yaml
twig:
    form_themes:
        - 'form/my_lexical.html.twig'
```

and start from the shipped template
(`vendor/flexible-ux/lexical-bundle/templates/form/lexical_widget.html.twig`).

That template wires Stimulus with StimulusBundle's
[Twig helpers](https://symfony.com/bundles/StimulusBundle/current/index.html#stimulus-twig-helpers)
— `stimulus_controller()` for the controller and its values, `stimulus_target()` and
`stimulus_action()` (chained as filters) for the rest — so keep using them in your override rather
than hand-writing `data-*` attributes: the helpers handle the Value API key casing and JSON-encode
array values for you.

### The icons

The toolbar uses `ux_icon('lexical:<name>')`, resolved from the icon set the bundle registers at
`assets/icons`. To swap an icon, register your own `lexical` icon set path (or a different set) in
`config/packages/ux_icons.yaml` and reference it from your overridden form theme.

### The styles

All chrome is class-based (`.lexical`, `.lexical__toolbar`, `.lexical__btn`, `.lexical__dialog`, …) and
the editable height is driven by the `--lexical-min-height` CSS custom property. Override those rules in
your own stylesheet — the bundle's CSS is plain, unscoped and low-specificity on purpose.

### Translations

All labels live in the `FlexibleUxLexical` translation domain (English, Spanish and Catalan ship with
the bundle). Add or override a locale by placing `translations/FlexibleUxLexical.<locale>.xlf` in your
application. Keys: `toolbar.*`, `dialog.link.*`, `dialog.cancel`, `dialog.confirm`, `error.invalid_url`.

## Security notes

- The editor only produces links whose scheme is listed in `allowed_link_schemes` (by default `http`,
  `https`, `mailto` and `tel`). Anything else — notably `javascript:` and `data:` — is rejected in the
  link modal. Widening the list widens what can be stored, so add schemes deliberately.
- The field stores HTML. If that HTML is later rendered as raw markup, treat it as trusted content and
  sanitise anything that can reach the field from outside this editor.

## Troubleshooting

- **The editor doesn't appear / the plain textarea shows.** The Stimulus controller isn't loaded —
  check that `@flexible-ux/lexical-bundle` → `lexical` is `enabled` in `assets/controllers.json` and
  that your page renders `{{ importmap('app') }}`.
- **`Could not find an asset mapper path that points to the lexical controller`.** The bundle isn't
  registered — make sure `FlexibleUx\FlexibleUxLexicalBundle` is in `config/bundles.php` so its
  `prependExtension()` can add the AssetMapper path.
- **`Unable to find an asset ... "lexical"`.** The Lexical packages aren't in your importmap — run the
  `importmap:require` command shown in the setup steps.
- **Icons don't render.** Ensure `symfony/ux-icons` is installed; the bundle registers the `lexical`
  icon set automatically, but the UX Icons Twig function must be available.
