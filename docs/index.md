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
php bin/console importmap:require lexical @lexical/rich-text @lexical/html @lexical/clipboard @lexical/list @lexical/link @lexical/history @lexical/utils
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
FlexibleUx\LexicalBundle\FlexibleUxLexicalBundle::class => ['all' => true],
```

The bundle auto-registers its AssetMapper path, its form theme and its icon set through
`prependExtension()`, so no changes to `config/packages/twig.yaml` or `config/packages/ux_icons.yaml`
are required.

## Usage

```php
use FlexibleUx\LexicalBundle\Form\Type\LexicalFormType;

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
| `toolbar`              | `string[]` | all 25 buttons, in eight groups   | Ordered entries: button names and `\|` separators. |
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

The button names are `undo`, `redo`, `cut`, `copy`, `paste`, `paste-word`, `bold`, `italic`,
`underline`, `strikethrough`, `subscript`, `superscript`, `remove-format`, `align-left`,
`align-center`, `align-right`, `align-justify`, `bullet`, `number`, `indent`, `outdent`, `link`,
`unlink`, `iframe` and `source`.

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

`undo` and `redo` dispatch Lexical's history commands and stay disabled while their stack is
empty (driven by Lexical's `CAN_UNDO_COMMAND` / `CAN_REDO_COMMAND` payloads), exactly like the
keyboard shortcuts they mirror.

`cut` and `copy` dispatch Lexical's `CUT_COMMAND` / `COPY_COMMAND` with a synthesised clipboard
event, so they involve no Clipboard API permission and work wherever the keyboard shortcuts do;
both are disabled while the selection is collapsed. `paste` and `paste-word` are different: a
toolbar button can only *read* the clipboard through the asynchronous
[Clipboard API](https://developer.mozilla.org/docs/Web/API/Clipboard_API), which requires a secure
context (HTTPS or localhost) and, depending on the browser, the user's permission. When access is
denied a translated hint (`error.clipboard_denied`) tells the user to paste with
<kbd>Ctrl</kbd>+<kbd>V</kbd> / <kbd>⌘V</kbd> instead — keyboard pasting is native Lexical
behaviour and always works. Pasted content goes through Lexical's model like any other paste, so
markup the editor cannot represent is normalised away, and links are checked against
`allowed_link_schemes` (a disallowed scheme unwraps the link, as in the `source` modal).

`paste-word` additionally scrubs the clipboard's HTML the way CKEditor's *Paste from Word* did
before importing it: Word's conditional comments and Office-namespace elements (`<o:p>`, …) are
dropped, and consecutive `mso-list` paragraphs — which would otherwise import as plain paragraphs
carrying a literal "·" or "1." marker — are rebuilt as real bulleted/numbered lists. The rebuilt
lists are flat: nesting levels are not reconstructed. Everything else Word adds (Mso classes,
`mso-*` styles) is ignored by the model import anyway.

`remove-format` strips the inline text formats (bold, italic, underline, strikethrough,
sub/superscript and inline text styles) from the selection while leaving block structure — lists,
alignment, indentation — and links untouched, mirroring CKEditor's *Remove Format* scope.

`subscript` and `superscript` are Lexical text formats and toggle like the other text buttons.
`indent` and `outdent` are one-shot block actions (Lexical's `INDENT_CONTENT_COMMAND` /
`OUTDENT_CONTENT_COMMAND`), so they never render as "active"; Lexical stores the result as an inline
`padding-inline-start` on the block, which is why indentation survives into the saved HTML without
any stylesheet of its own.

The four `align-*` buttons dispatch Lexical's `FORMAT_ELEMENT_COMMAND` on the block at the caret and
behave radio-style: the button matching the block's current alignment renders as "active", and none
does while the block keeps the default (`''`) alignment. Like indentation, the result is stored as an
inline style (`text-align`) on the block, so it survives into the saved HTML and needs no CSS from
the bundle.

Alignment — like every toolbar feature — is optional: a `toolbar` (per field, or application-wide
through the bundle configuration) that lists no `align-*` entries renders no alignment buttons, and
the four entries can also be cherry-picked individually. An editor without the buttons still
**preserves** any `text-align` already present in the stored (or pasted) HTML when the content is
edited; opting out only removes the ability to change alignment from the toolbar.

The `iframe` button embeds external content — a video, a map, a booking form — the way CKEditor's
*IFrame* dialog did (the setup this replaces needed `extraAllowedContent: 'iframe[*]'` for the same
reason: an editor keeps only the markup it knows about). The modal asks for the frame URL, an
optional width and height, an advisory `title` and whether fullscreen is allowed; the result is
stored as a plain `<iframe>`, which is what the frontend renders.

Inside the editor the embed is a block of its own, rendered as a **live but inert preview**: the
real frame, so editing stays WYSIWYG, with pointer events off so a click selects the block instead
of disappearing into the framed page. A selected embed is outlined, <kbd>Backspace</kbd> or
<kbd>Delete</kbd> removes it, and pressing the toolbar button again reopens the dialog to edit it
(the button renders as "active" while an embed is selected). Insert, edit and delete are ordinary
undoable steps. The preview additionally carries `loading="lazy"` and — unless the embed brought a
`sandbox` of its own — a `sandbox="allow-scripts allow-same-origin"` that lets the framed page
render but not navigate the page hosting the form away. Neither attribute is exported.

An `<iframe>` that arrives as markup (the `source` modal, a paste, or stored content being loaded)
keeps `src`, `width`, `height`, `title`, `allow`, `sandbox` and `allowfullscreen` — enough for a
YouTube or Maps embed to survive a round-trip unchanged. Anything else it carried (`frameborder`,
`referrerpolicy`, …) is normalised away like any other markup the model cannot represent. Width and
height are the HTML dimension attributes, so the dialog takes a number of pixels or a percentage
(`560`, `100%`); leave them empty to fall back to the browser's default frame size.

The `source` button opens a modal where the document can be edited as plain-text HTML. Confirming
re-imports the markup through Lexical's model, so only markup the editor can represent survives —
anything else is normalised away — and the whole swap lands as a single undoable history step.
Links entered this way are checked against the same `allowed_link_schemes` allowlist as the link
modal: a link whose scheme is not allowed is unwrapped (its text stays, the link is dropped).

## Architecture

| Layer | File | Responsibility |
|-------|------|----------------|
| PHP   | `src/Form/Type/LexicalFormType.php` | Declares options, exposes `lexical_toolbar` / `lexical_height` view vars. |
| Bundle| `src/FlexibleUxLexicalBundle.php`   | Registers the tagged service, prepends the form theme + icon set. |
| HTML  | `templates/form/lexical_widget.html.twig` | Toolbar, editable surface, the link/source/iframe `<dialog>`s. |
| JS    | `assets/src/controller.js`          | Mounts Lexical, syncs the textarea, drives the toolbar. |
| JS    | `assets/src/iframe-node.js`         | The `IframeNode` the `iframe` button inserts (import, export, preview). |
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
application. Keys: `toolbar.*`, `dialog.link.*`, `dialog.source.*`, `dialog.iframe.*`, `dialog.cancel`,
`dialog.confirm`, `error.invalid_url`, `error.invalid_embed_url`, `error.invalid_embed_size`,
`error.clipboard_denied`.

## Security notes

- The editor only produces links whose scheme is listed in `allowed_link_schemes` (by default `http`,
  `https`, `mailto` and `tel`). Anything else — notably `javascript:` and `data:` — is rejected in the
  link modal, and everywhere else the allowlist is enforced by a Lexical node transform: whichever way
  a link enters the document — the `source` modal, the `paste` / `paste-word` buttons, a native
  Ctrl+V paste, drag-and-drop, or the initial load of already-stored content — a link whose scheme is
  not in the list is unwrapped (its text stays, the link is dropped). Note the last point: content
  that already contains a disallowed link loses that link the next time it is opened and saved
  through the editor. Widening the list widens what can be stored, so add schemes deliberately.
- An embed's `src` is held to a fixed, narrower rule than links: it must resolve to an `http(s)` URL.
  Relative URLs are fine (they resolve against the page), `javascript:` and `data:` are not — a frame
  *runs* what it loads, so this list is not configurable, and `allowed_link_schemes` (which may
  legitimately carry `mailto`/`tel`, or be widened) has no say over it. As with links, the rule is
  enforced by a node transform at the point where every path into the document converges, so an
  `<iframe>` with a disallowed `src` is dropped whether it came from the dialog, the `source` modal, a
  paste or already-stored content — the same "opening and saving strips it" caveat applies.
- A `sandbox` attribute already on an imported `<iframe>` is preserved rather than dropped, so
  round-tripping stored content through the editor cannot silently widen what an embed may do. The
  editor does not add one to the exported markup: sandboxing new embeds is the frontend's call, not
  the editor's.
- The field stores HTML. If that HTML is later rendered as raw markup, treat it as trusted content and
  sanitise anything that can reach the field from outside this editor.

## Troubleshooting

- **The editor doesn't appear / the plain textarea shows.** The Stimulus controller isn't loaded —
  check that `@flexible-ux/lexical-bundle` → `lexical` is `enabled` in `assets/controllers.json` and
  that your page renders `{{ importmap('app') }}`.
- **`Could not find an asset mapper path that points to the lexical controller`.** The bundle isn't
  registered — make sure `FlexibleUx\LexicalBundle\FlexibleUxLexicalBundle` is in `config/bundles.php` so its
  `prependExtension()` can add the AssetMapper path.
- **`Unable to find an asset ... "lexical"`.** The Lexical packages aren't in your importmap — run the
  `importmap:require` command shown in the setup steps.
- **Icons don't render.** Ensure `symfony/ux-icons` is installed; the bundle registers the `lexical`
  icon set automatically, but the UX Icons Twig function must be available.
