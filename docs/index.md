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

See the [README](../README.md) for the four install steps. In short:

```console
composer require flexible-ux/lexical-bundle
php bin/console importmap:require lexical @lexical/rich-text @lexical/html @lexical/list @lexical/link @lexical/history @lexical/utils
```

then enable the controller in `assets/controllers.json`:

```json
{
    "controllers": {
        "@flexible-ux/lexical-bundle": {
            "lexical": { "enabled": true, "fetch": "lazy" }
        }
    }
}
```

The bundle auto-registers its form theme and icon set through `prependExtension()`, so no changes to
`config/packages/twig.yaml` or `config/packages/ux_icons.yaml` are required.

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

| Option    | Type       | Default            | Description                               |
|-----------|------------|--------------------|-------------------------------------------|
| `toolbar` | `string[]` | all eight buttons  | Ordered toolbar buttons to display.       |
| `height`  | `string`   | `'200px'`          | Minimum editable height (any CSS length). |

The button names are `bold`, `italic`, `underline`, `strikethrough`, `bullet`, `number`, `link` and
`unlink`. Reordering the array reorders the toolbar; the theme inserts a separator whenever the button
group changes (text → list → link).

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

- The editor only produces links with the `http`, `https`, `mailto` or `tel` scheme; `javascript:` and
  `data:` URLs are rejected in the link modal.
- The field stores HTML. If that HTML is later rendered as raw markup, treat it as trusted content and
  sanitise anything that can reach the field from outside this editor.

## Troubleshooting

- **The editor doesn't appear / the plain textarea shows.** The Stimulus controller isn't loaded —
  double-check step 4 (`assets/controllers.json`) and that `{{ importmap('app') }}` is rendered.
- **`Unable to find an asset ... "lexical"`.** The Lexical packages aren't in your importmap — run the
  `importmap:require` command from step 3.
- **Icons don't render.** Ensure `symfony/ux-icons` is installed; the bundle registers the `lexical`
  icon set automatically, but the UX Icons Twig function must be available.
