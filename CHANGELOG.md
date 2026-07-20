# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v0.4.0...HEAD
[0.4.0]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v0.2.1...v0.3.0
[0.2.1]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/Flexible-User-Experience/lexical-bundle/releases/tag/v0.1.0
