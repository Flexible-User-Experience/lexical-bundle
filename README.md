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
- Symfony 6.4, 7.x or 8.x with AssetMapper, StimulusBundle and UX Icons

## Installation

Make sure [Composer is installed](https://getcomposer.org/doc/00-intro.md) globally.

### Applications that use Symfony Flex

```console
composer require flexible-ux/lexical-bundle
```

### Applications that don't use Symfony Flex

#### Step 1: Download the bundle

```console
composer require flexible-ux/lexical-bundle
```

#### Step 2: Enable the bundle

```php
// config/bundles.php
return [
    // ...
    FlexibleUx\FlexibleUxLexicalBundle::class => ['all' => true],
];
```

## Setup

Two steps remain because the bundle ships JavaScript that runs in your app's importmap.

#### Step 3: Add the Lexical packages to your importmap

```console
php bin/console importmap:require lexical @lexical/rich-text @lexical/html @lexical/list @lexical/link @lexical/history @lexical/utils
```

(This also pulls in the transitive Lexical packages.)

#### Step 4: Enable the Stimulus controller

Add the controller to your app's `assets/controllers.json`:

```json
{
    "controllers": {
        "@flexible-ux/lexical-bundle": {
            "lexical": {
                "enabled": true,
                "fetch": "lazy"
            }
        }
    }
}
```

That's it. The form theme and the toolbar icons are registered automatically by the bundle; the
editor's CSS is imported by the controller, so there is nothing else to include.

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

| Option    | Type       | Default                                                                             | Description                                   |
|-----------|------------|-------------------------------------------------------------------------------------|-----------------------------------------------|
| `toolbar` | `string[]` | `['bold','italic','underline','strikethrough','bullet','number','link','unlink']`   | Ordered toolbar buttons to display.           |
| `height`  | `string`   | `'200px'`                                                                           | Minimum editable height (any CSS length).     |

Available buttons: `bold`, `italic`, `underline`, `strikethrough`, `bullet`, `number`, `link`,
`unlink`. The field extends `TextareaType`, so all textarea/text field options (`label`, `required`,
`attr`, `constraints`, …) apply too.

The editor stores **HTML**. When you render that HTML on a public page, output it as trusted markup
(e.g. Twig's `|raw`) — links are restricted to the `http`, `https`, `mailto` and `tel` schemes by the
editor, but you remain responsible for sanitising any HTML that reaches the field from other sources.

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
