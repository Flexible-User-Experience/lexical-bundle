# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-07-19

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

[Unreleased]: https://github.com/flexible-ux/lexical-bundle/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/flexible-ux/lexical-bundle/releases/tag/v1.0.0
