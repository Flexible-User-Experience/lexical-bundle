# FlexibleUxLexicalBundle

A reusable Symfony bundle providing **`LexicalFormType`** — a lightweight rich-text editor
form field built on Meta's [Lexical](https://lexical.dev), wired for
[AssetMapper](https://symfony.com/doc/current/frontend/asset_mapper.html),
[Stimulus](https://symfony.com/bundles/StimulusBundle) and
[UX Icons](https://symfony.com/bundles/ux-icons).

The field renders a toolbar (bold, italic, underline, strikethrough, bulleted / numbered lists,
link, unlink) and a contenteditable surface around a hidden `<textarea>`. The editor reads the
textarea's HTML on load and writes HTML back on every change, so it is a drop-in replacement for a
plain textarea and **degrades to that textarea when JavaScript is disabled**. No build step is
required — everything runs through AssetMapper's importmap.

## Requirements

- PHP 8.2+
- Symfony 7.4 or 8.x with AssetMapper, StimulusBundle and UX Icons

## Installation

Make sure [Composer is installed](https://getcomposer.org/doc/00-intro.md) globally.

#### Step 1: Download the bundle

```console
composer require flexible-ux/lexical-bundle
```

With **Symfony Flex**, `composer require` already wires the front-end for you: the Lexical packages are
added to your `importmap.php` (from the bundle's `assets/package.json`) and the Stimulus controller is
enabled in your `assets/controllers.json`. Skip to step 3.

#### Step 2 (without Flex): wire the front-end manually

Add the Lexical packages to your importmap:

```console
php bin/console importmap:require lexical @lexical/rich-text @lexical/html @lexical/list @lexical/link @lexical/history @lexical/utils
```

and enable the Stimulus controller in `assets/controllers.json`:

```json
{
    "controllers": {
        "@flexible-ux/lexical-bundle": {
            "lexical": { "enabled": true, "fetch": "lazy" }
        }
    }
}
```

#### Step 3: Enable the bundle

The bundle does not ship a Flex recipe yet, so register it in `config/bundles.php` (Flex and non-Flex
alike):

```php
// config/bundles.php
return [
    // ...
    FlexibleUx\FlexibleUxLexicalBundle::class => ['all' => true],
];
```

That's it. The bundle registers its AssetMapper path, the form theme and the toolbar icons
automatically; the editor's CSS is imported by the controller, so there is nothing else to include.

## Usage

```php
use FlexibleUx\Form\Type\LexicalFormType;

$builder->add('description', LexicalFormType::class);
```

With options:

```php
$builder->add('description', LexicalFormType::class, [
    'label'   => 'Description',
    'required' => false,
    'toolbar' => ['bold', 'italic', 'bullet', 'number', 'link', 'unlink'],
    'height'  => '320px',
]);
```

### Options

| Option                 | Type       | Default                                                                           | Description                                          |
|------------------------|------------|-----------------------------------------------------------------------------------|------------------------------------------------------|
| `toolbar`              | `string[]` | `['bold','italic','underline','strikethrough','bullet','number','link','unlink']` | Ordered toolbar buttons to display.                  |
| `height`               | `string`   | `'200px'`                                                                         | Minimum editable height (any CSS length).            |
| `allowed_link_schemes` | `string[]` | `['http','https','mailto','tel']`                                                 | URL schemes the link modal accepts.                  |

Available buttons: `bold`, `italic`, `underline`, `strikethrough`, `bullet`, `number`, `link`,
`unlink`. The field extends `TextareaType`, so all textarea/text field options (`label`, `required`,
`attr`, `constraints`, …) apply too.

Restrict (or widen) which link schemes the editor may produce with `allowed_link_schemes` — entries may
be written with or without the trailing colon, and anything outside the list is rejected in the link
modal:

```php
$builder->add('description', LexicalFormType::class, [
    'allowed_link_schemes' => ['https'], // https-only links
]);
```

The editor stores **HTML**. When you render that HTML on a public page, output it as trusted markup
(e.g. Twig's `|raw`) — links are restricted to `allowed_link_schemes` by the editor, but you remain
responsible for sanitising any HTML that reaches the field from other sources.

## How it works

The bundle is deliberately split into four layers so each can be understood and overridden on its own:

- **PHP** — `FlexibleUx\Form\Type\LexicalFormType` exposes the `toolbar` / `height` options as view
  variables.
- **HTML** — the `lexical_widget` Twig form theme renders the toolbar, the editable surface and the
  link modal.
- **JS** — the `lexical` Stimulus controller mounts Lexical on the editable element and keeps the
  textarea in sync.
- **CSS** — `assets/styles/lexical.css` (imported by the controller).

See [`docs/index.md`](docs/index.md) for customisation, theming and translation details.

## License

Released under the [MIT License](LICENSE). Bundled icons are from
[Lucide](https://lucide.dev) (ISC License).
