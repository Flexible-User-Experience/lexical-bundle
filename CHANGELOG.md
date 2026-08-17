# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.7.2] - 2026-08-15

### Changed

- The `iframe` button now uses the Lucide `app-window` icon instead of `globe` (which leaves the
  bundled icon set). CKEditor's globe was the obvious starting point, but in this toolbar it reads
  as "the web" two buttons away from `link`, while a window frame says "an embedded page" — and,
  unlike a second code glyph, it cannot be confused with the `source` button beside it.
- The npm-side version in `assets/package.json`, left at 0.5.0 through the 0.6.x, 0.7.0 and 0.7.1
  tags, tracks the bundle version again.

## [0.7.1] - 2026-08-15

### Fixed

- The `lexical` controller failed to load in a consuming application with
  `Failed to fetch dynamically imported module: .../src/controller-<digest>.js`, leaving the
  plain textarea in place. AssetMapper discovers the imports it has to rewrite with a regex
  whose named-import clause is `[\w\s{},*]` — no `$` — so the controller's
  `import { IframeNode, $createIframeNode, … } from './iframe-node.js'` was invisible to it:
  no importmap entry was generated for the relative module, and the browser requested the
  undigested `iframe-node.js`, which does not exist. The statement is now a namespace import
  the compiler matches, and an integration test asserts every relative import of the
  controller is discovered. Consumers on 0.7.0 need no configuration change — only this
  release. Bare imports were never affected: they resolve through the importmap by name.

## [0.7.0] - 2026-08-15

### Added

- An `iframe` toolbar button — the equivalent of CKEditor's *IFrame* dialog (and of the
  `extraAllowedContent: 'iframe[*]'` a FOSCKEditor config needed to keep the markup) — sitting
  right before `source` in the default toolbar, and optional like every other button.
  - The modal takes the frame URL, an optional width and height (a number of pixels or a
    percentage), an advisory `title` and an *allow fullscreen* checkbox; the embed is stored as a
    plain `<iframe>`.
  - Embeds are a Lexical node of their own (`assets/src/iframe-node.js`), so they survive the
    round-trip through the editor: an `<iframe>` arriving from the `source` modal, a paste or
    already-stored content keeps `src`, `width`, `height`, `title`, `allow`, `sandbox` and
    `allowfullscreen` — enough for a YouTube or Maps embed — while everything else it carried is
    normalised away like any other markup the model cannot represent.
  - Inside the editor an embed renders as a live but inert preview: clicking it selects the block
    (outlined, and `cut`/`copy` apply to it), <kbd>Backspace</kbd> or <kbd>Delete</kbd> removes it,
    and pressing the toolbar button again reopens the dialog to edit it. Insert, edit and delete
    are ordinary undoable steps. The preview carries `loading="lazy"` and, unless the embed brought
    its own `sandbox`, a `sandbox="allow-scripts allow-same-origin"` that keeps the framed page from
    navigating the page hosting the form away; neither attribute is exported.
  - A frame source must resolve to an `http(s)` URL — relative URLs included, `javascript:` and
    `data:` excluded. The rule is fixed (deliberately not `allowed_link_schemes`, which may carry
    `mailto`/`tel`) and, like the link allowlist, enforced by a node transform wherever content
    enters the document, so a disallowed embed is dropped whichever way it arrived.
- The Lucide `globe` icon joins the bundled offline icon set, and the labels (`toolbar.iframe`,
  `dialog.iframe.*`, `error.invalid_embed_url`, `error.invalid_embed_size`) ship translated in
  English, Spanish and Catalan.

### Changed

- **BC break**: added the `LexicalBundle` namespace sublevel so several FlexibleUx bundles can
  coexist without class collisions — the root namespace is now `FlexibleUx\LexicalBundle\` and
  the bundle class `FlexibleUx\LexicalBundle\FlexibleUxLexicalBundle`. Update your
  `config/bundles.php` registration and any `FlexibleUx\Form\Type\LexicalFormType` import to
  `FlexibleUx\LexicalBundle\Form\Type\LexicalFormType`. The `flexible_ux_lexical` config key,
  the service ids and the `FlexibleUxLexical` translation domain are unchanged.

## [0.6.1] - 2026-08-14

### Changed

- Narrowed the runtime requirements to the components the bundle actually uses: the broad
  `symfony/framework-bundle` requirement is replaced by `symfony/config`,
  `symfony/dependency-injection`, `symfony/http-kernel` and `symfony/options-resolver`.
  FrameworkBundle is still booted by the test kernel, so it moved to `require-dev`.
- Raised the PHPUnit constraint to `^11.5 || ^12.0 || ^13.0`: PHPUnit 10 is redundant with the
  PHP 8.2 floor (PHPUnit 11 runs on PHP 8.2), and PHPUnit 12 and 13 are now allowed.

### Removed

- The unused `symfony/phpunit-bridge` dev requirement — nothing registered the bridge, so it
  was inert under a plain `vendor/bin/phpunit` run.

## [0.6.0] - 2026-08-13

### Added

- Seven new toolbar buttons completing the classic CKEditor default layout, every one optional and
  in the default toolbar at its familiar position: `undo` / `redo` as the leading group,
  `cut` / `copy` / `paste` / `paste-word` as the clipboard group, and `remove-format` closing the
  text-format group.
  - `undo` / `redo` dispatch Lexical's history commands and stay disabled while their stack is
    empty (driven by the `CAN_UNDO_COMMAND` / `CAN_REDO_COMMAND` payloads).
  - `cut` / `copy` dispatch `CUT_COMMAND` / `COPY_COMMAND` with a synthesised clipboard event ???
    no Clipboard API permission involved ??? and are disabled while the selection is collapsed.
  - `paste` / `paste-word` read the system clipboard through the asynchronous Clipboard API
    (secure context; the browser may ask the user's permission). When access is denied, a
    translated hint (`error.clipboard_denied`) points at Ctrl+V / ???V, which remains native Lexical
    behaviour. Pasted markup is imported through Lexical's model ??? whatever the model cannot
    represent is normalised away ??? and links whose scheme is not in `allowed_link_schemes` are
    unwrapped, exactly as in the `source` modal.
  - `paste-word` scrubs Word's clipboard HTML before the import: conditional comments and
    Office-namespace elements (`<o:p>`, ???) are dropped, and consecutive `mso-list` paragraphs are
    rebuilt as real bulleted/numbered lists (flat ??? nesting levels are not reconstructed) instead
    of importing as paragraphs with a literal "??" / "1." marker in front.
  - `remove-format` strips the inline text formats and styles from the selection; block structure
    (lists, alignment, indentation) and links are kept, mirroring CKEditor's RemoveFormat scope.
- The matching Lucide icons ??? `undo`, `redo`, `scissors`, `copy`, `clipboard-paste`,
  `clipboard-type` and `remove-formatting` ??? join the bundled offline icon set, and the labels are
  translated in English, Spanish and Catalan.
- `@lexical/clipboard` in the bundle's importmap (`assets/package.json`): with Flex it lands in
  `importmap.php` automatically on install; without Flex it is part of the documented
  `importmap:require` command. It was already a transitive dependency of `@lexical/rich-text`, so
  applications installed through Flex or the documented command need no change.

### Changed

- `DEFAULT_TOOLBAR` now opens with the history and clipboard groups and its text-format group ends
  with `remove-format`, growing the default from six groups (17 buttons) to eight groups
  (24 buttons). Custom `toolbar` options are unaffected; the new buttons are opt-in there like any
  other entry.

### Security

- A native keyboard paste (Ctrl+V / ???V) or a drag-and-drop could smuggle a link with a disallowed
  scheme ??? e.g. `javascript:` ??? into the stored HTML: only the link modal, the `source` modal and
  the toolbar paste buttons enforced the `allowed_link_schemes` allowlist. Enforcement now lives in
  a Lexical node transform on `LinkNode`, the one place every path converges, so any link entering
  the document by any means is unwrapped when its scheme is not allowed (its text stays, the link
  goes). This also covers the initial load: stored content that already carries a disallowed link
  loses that link ??? silently, by design ??? the next time it is edited. The explicit unwrap pass the
  `source` modal and the paste buttons used to run is gone, replaced by the transform.

## [0.5.0] - 2026-08-03

### Changed

- The Lexical packages in the bundle's importmap (`assets/package.json`) are bumped from `^0.48.0`
  to `^0.49.0`. Neither of the two breaking changes in
  [v0.49.0](https://github.com/facebook/lexical/blob/main/CHANGELOG.md) affects the bundle: one
  removes redundant TypeScript generics (the controller is plain JavaScript) and the other ports the
  node classes to the `config()` protocol (the bundle registers the built-in nodes unchanged, and
  editing, alignment, lists and HTML round-tripping were verified against 0.49.0).

### Fixed

- Toggling the `bullet` / `number` toolbar buttons never inserted or removed the list and raised
  Lexical's "Unable to find an active editor state" error: the controller read the current list type
  outside an `editorState.read()` scope. Present since the first release; Lexical 0.49 keeps
  enforcing the scope, so the read is now wrapped properly.

### Added

- Four text-alignment toolbar buttons ??? `align-left`, `align-center`, `align-right` and
  `align-justify` ??? available through the `toolbar` option and enabled by default as a new group
  between the text formats and the lists. They dispatch Lexical's `FORMAT_ELEMENT_COMMAND`, and the
  button matching the current block's alignment lights up radio-style (none while the block keeps
  the default alignment). Lexical stores the result as an inline `text-align` on the block, so the
  alignment survives into the saved HTML with no extra CSS.
  Like every toolbar entry the buttons are optional and individually pickable: a `toolbar` option
  (per field or via the bundle configuration) without `align-*` entries renders no alignment
  buttons ??? existing `text-align` styles in stored content are still preserved when edited.
- The matching Lucide icons ??? `align-left`, `align-center`, `align-right` and `align-justify` ???
  are bundled with the existing offline icon set, and the labels are translated in English, Spanish
  and Catalan.
- A `source` toolbar button (Lucide `file-code-corner` icon, bundled in the offline icon set), in
  the default toolbar as its own trailing group: it opens a modal where the document is edited as
  plain-text HTML. Confirming re-imports the markup through Lexical's model ??? markup the editor
  cannot represent is normalised away ??? as a single undoable history step, and every imported link
  is checked against the same `allowed_link_schemes` allowlist as the link modal (a disallowed
  scheme unwraps the link), so the source path cannot smuggle e.g. `javascript:` hrefs into the
  stored HTML. Labels are translated in English, Spanish and Catalan.

## [0.4.0] - 2026-07-20

### Added

- Bundle configuration, so `toolbar`, `height` and `allowed_link_schemes` can be defaulted once for
  the whole application in `config/packages/flexible_ux_lexical.yaml` instead of being repeated at
  every call site. The values are bound to `flexible_ux_lexical.*` container parameters and injected
  into the form type; per-field options still take precedence. Until now any key under
  `flexible_ux_lexical` failed with `Unrecognized option`.
- `LexicalFormType::SEPARATOR` (`|`) as a toolbar entry, and `LexicalFormType::AVAILABLE_BUTTONS`
  listing every button the form theme can render.

### Changed

- **Toolbar grouping is now decided by the caller.** Separators used to be derived from a hardcoded
  button-to-group map inside the form theme, which meant a custom `toolbar` had no say in them. The
  toolbar now renders entries in exactly the order given and draws a divider wherever a `|` entry
  appears. `DEFAULT_TOOLBAR` embeds the previous four groups, so the out-of-the-box appearance is
  unchanged ??? but **a custom `toolbar` no longer gets automatic separators**: add `'|'` entries where
  you want them.
- Redundant separators (leading, trailing or repeated) are dropped when the option is normalised, so
  a hand-written list cannot render a stray or doubled divider.
- An unknown toolbar entry now raises an `InvalidOptionsException` naming it and listing the valid
  buttons, instead of being silently skipped by the template.
- `allowed_link_schemes` normalisation moved out of `buildView()` into an option normaliser, so the
  resolved option value is already normalised.

## [0.3.0] - 2026-07-20

### Added

- Four new toolbar buttons, all available through the `toolbar` option and enabled by default:
  `subscript` and `superscript` (Lexical text formats, toggling like the other text buttons) and
  `indent` / `outdent` (Lexical's `INDENT_CONTENT_COMMAND` and `OUTDENT_CONTENT_COMMAND`, one-shot
  block actions that never render as "active").
- The matching Lucide icons ??? `subscript`, `superscript`, `indent-increase` and `indent-decrease` ???
  are bundled with the existing offline icon set, and the labels are translated in English, Spanish
  and Catalan.
- A new `indent` toolbar group, so the theme draws a separator between the list and indent buttons
  (text ?? list ?? indent ?? link).

### Changed

- The toolbar's active-state refresh is now derived from the text-format list instead of a
  hardcoded set of flags, so future format toggles light up without extra wiring.

## [0.2.1] - 2026-07-19

### Changed

- The `lexical_widget` form theme now builds its Stimulus wiring with StimulusBundle's
  `stimulus_controller`, `stimulus_target` and `stimulus_action` Twig helpers instead of
  hand-written `data-*` attributes. The rendered markup is byte-for-byte unchanged; the helpers
  take care of the Value API key casing (`invalidUrlMessage` becomes
  `data-lexical-invalid-url-message-value`) and of JSON-encoding array values such as
  `allowedLinkSchemes`.

## [0.2.0] - 2026-07-19

### Added

- `allowed_link_schemes` option on `FlexibleUx\Form\Type\LexicalFormType`, controlling which URL
  schemes the link modal will accept. Entries may be written with or without the trailing colon and
  in any case (`https`, `https:` and `HTTPS:` are equivalent); the normalised list is exposed through
  `buildView()` and handed to the `lexical` Stimulus controller as its `allowedLinkSchemes` value.
  It defaults to `http`, `https`, `mailto` and `tel`, so existing usage is unaffected.

### Changed

- The link-scheme allowlist is no longer hardcoded in the Stimulus controller. The controller keeps
  the previous set as a built-in fallback, so custom form themes that do not pass the value continue
  to work.

## [0.1.0] - 2026-07-19

### Added

- `FlexibleUx\Form\Type\LexicalFormType`: a Symfony form type backed by Meta's
  [Lexical](https://lexical.dev) editor, with `toolbar` and `height` options.
- `lexical` Stimulus controller wiring the editor to a hidden textarea (HTML in / HTML out),
  shipped for AssetMapper via `assets/package.json`.
- `lexical_widget` Twig form theme (toolbar, contenteditable surface, native `<dialog>` link
  modal), auto-registered through the bundle's `prependExtension()`.
- Bundled Lucide SVG icons auto-registered as the `lexical` UX Icons icon set (offline-safe).
- Translations for the `FlexibleUxLexical` domain in English, Spanish and Catalan.
- Core formatting: bold, italic, underline, strikethrough, bulleted list, numbered list,
  link and unlink, with a safe-scheme allowlist (`http`, `https`, `mailto`, `tel`).

[Unreleased]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v0.7.2...HEAD
[0.7.2]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v0.7.1...v0.7.2
[0.7.1]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v0.7.0...v0.7.1
[0.7.0]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v0.6.1...v0.7.0
[0.6.1]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v0.6.0...v0.6.1
[0.6.0]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v0.2.1...v0.3.0
[0.2.1]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/Flexible-User-Experience/lexical-bundle/releases/tag/v0.1.0
