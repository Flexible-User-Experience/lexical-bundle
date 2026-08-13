# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Seven new toolbar buttons completing the classic CKEditor default layout, every one optional and
  in the default toolbar at its familiar position: `undo` / `redo` as the leading group,
  `cut` / `copy` / `paste` / `paste-word` as the clipboard group, and `remove-format` closing the
  text-format group.
  - `undo` / `redo` dispatch Lexical's history commands and stay disabled while their stack is
    empty (driven by the `CAN_UNDO_COMMAND` / `CAN_REDO_COMMAND` payloads).
  - `cut` / `copy` dispatch `CUT_COMMAND` / `COPY_COMMAND` with a synthesised clipboard event —
    no Clipboard API permission involved — and are disabled while the selection is collapsed.
  - `paste` / `paste-word` read the system clipboard through the asynchronous Clipboard API
    (secure context; the browser may ask the user's permission). When access is denied, a
    translated hint (`error.clipboard_denied`) points at Ctrl+V / ⌘V, which remains native Lexical
    behaviour. Pasted markup is imported through Lexical's model — whatever the model cannot
    represent is normalised away — and links whose scheme is not in `allowed_link_schemes` are
    unwrapped, exactly as in the `source` modal.
  - `paste-word` scrubs Word's clipboard HTML before the import: conditional comments and
    Office-namespace elements (`<o:p>`, …) are dropped, and consecutive `mso-list` paragraphs are
    rebuilt as real bulleted/numbered lists (flat — nesting levels are not reconstructed) instead
    of importing as paragraphs with a literal "·" / "1." marker in front.
  - `remove-format` strips the inline text formats and styles from the selection; block structure
    (lists, alignment, indentation) and links are kept, mirroring CKEditor's RemoveFormat scope.
- The matching Lucide icons — `undo`, `redo`, `scissors`, `copy`, `clipboard-paste`,
  `clipboard-type` and `remove-formatting` — join the bundled offline icon set, and the labels are
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

- A native keyboard paste (Ctrl+V / ⌘V) or a drag-and-drop could smuggle a link with a disallowed
  scheme — e.g. `javascript:` — into the stored HTML: only the link modal, the `source` modal and
  the toolbar paste buttons enforced the `allowed_link_schemes` allowlist. Enforcement now lives in
  a Lexical node transform on `LinkNode`, the one place every path converges, so any link entering
  the document by any means is unwrapped when its scheme is not allowed (its text stays, the link
  goes). This also covers the initial load: stored content that already carries a disallowed link
  loses that link — silently, by design — the next time it is edited. The explicit unwrap pass the
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

- Four text-alignment toolbar buttons — `align-left`, `align-center`, `align-right` and
  `align-justify` — available through the `toolbar` option and enabled by default as a new group
  between the text formats and the lists. They dispatch Lexical's `FORMAT_ELEMENT_COMMAND`, and the
  button matching the current block's alignment lights up radio-style (none while the block keeps
  the default alignment). Lexical stores the result as an inline `text-align` on the block, so the
  alignment survives into the saved HTML with no extra CSS.
  Like every toolbar entry the buttons are optional and individually pickable: a `toolbar` option
  (per field or via the bundle configuration) without `align-*` entries renders no alignment
  buttons — existing `text-align` styles in stored content are still preserved when edited.
- The matching Lucide icons — `align-left`, `align-center`, `align-right` and `align-justify` —
  are bundled with the existing offline icon set, and the labels are translated in English, Spanish
  and Catalan.
- A `source` toolbar button (Lucide `file-code-corner` icon, bundled in the offline icon set), in
  the default toolbar as its own trailing group: it opens a modal where the document is edited as
  plain-text HTML. Confirming re-imports the markup through Lexical's model — markup the editor
  cannot represent is normalised away — as a single undoable history step, and every imported link
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
  unchanged — but **a custom `toolbar` no longer gets automatic separators**: add `'|'` entries where
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
- The matching Lucide icons — `subscript`, `superscript`, `indent-increase` and `indent-decrease` —
  are bundled with the existing offline icon set, and the labels are translated in English, Spanish
  and Catalan.
- A new `indent` toolbar group, so the theme draws a separator between the list and indent buttons
  (text · list · indent · link).

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

[Unreleased]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v0.5.0...HEAD
[0.5.0]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v0.2.1...v0.3.0
[0.2.1]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/Flexible-User-Experience/lexical-bundle/releases/tag/v0.1.0
