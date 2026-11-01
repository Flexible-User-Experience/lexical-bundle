# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Extension points in the Symfony UX style. The controller dispatches `lexical:pre-connect`
  (`detail.config`, the extension config the editor is built from: push nodes and Lexical
  extensions, add theme classes, swap an HTML export), `lexical:connect` (`detail.editor`, once
  the stored document is loaded) and `lexical:disconnect` (`detail.editor`, before disposal) on
  the field's wrapper, bubbling, so an application controller or a listener on `document` extends
  an editor without replacing the bundle's controller. Every detail carries `element`, the
  wrapper; `lexical:disconnect` goes to `document` directly when the wrapper has already left the
  page, where a bubbling event would reach nobody. The theme handed to a listener is a copy, so a
  change applies to that editor only.
- Theming through CSS custom properties. Every colour, radius and font of `lexical.css` and
  `lexical-content.css` is a `--lexical-*` variable with its default declared on `:root` (border,
  background, text, muted, toolbar and hover backgrounds, accent and its dark and contrast
  shades, error colour and background, radius, dialog shadow and backdrop, monospace stack;
  content spacing, link colour, quote border and colour, code and mark backgrounds). An
  application restyles the editor and the rendered content at once by redefining them, on
  `:root`, on an ancestor or inside a `prefers-color-scheme: dark` media query. Rendered output
  is unchanged with the defaults. Rules keep their class specificity on purpose, so a global
  reset cannot undo them.
- Pasting from Word, Outlook or Google Docs just works. Every HTML paste and drop now passes
  through a clipboard import middleware (`@lexical/clipboard`'s `ClipboardImportExtension`, the
  `$next(data)` form Lexical 0.53.0 introduced) before Lexical imports it; markup carrying
  Office's fingerprints is scrubbed on its way in, and the Office lists are rebuilt with their
  nesting (`mso-list: ... level2 ...` goes inside the level-1 item before it) instead of flat.
  Other HTML passes through untouched. The `paste-word` button stays as a way to force the
  clean-up for one paste; it no longer pre-processes the clipboard itself.
- Relative links. The link modal and the import allowlist accept a root-relative path
  (`/about`), a fragment (`#top`) or a query (`?page=2`) besides the allowed schemes, so the
  site's own pages can finally be linked; a protocol-relative `//host` is still rejected. The
  new `allow_relative_links` form option and bundle configuration key (default `true`) turn it
  off, and the sanitizer follows the same switch. The URL field of the link modal is a text
  input now (`type="url"` rejected relative URLs on its own), and the `error.invalid_url`
  message mentions paths in every locale.
- Rendering and sanitising stored content. The `lexical_content` Twig filter sanitises a field's
  HTML and wraps it in `<div class="lexical-content">`; `lexical_sanitize` sanitises without the
  wrapper. Behind them is the new `flexible_ux_lexical.html_sanitizer` service (autowirable as
  `HtmlSanitizerInterface $lexicalHtmlSanitizer`): Symfony's HtmlSanitizer configured by
  `LexicalHtmlSanitizerConfigFactory` to mirror exactly what the editor produces - its elements
  and attributes, the union of the presets' `allowed_link_schemes` for links, `http(s)` or a
  relative URL for frame sources, inline styles cut down to `text-align` / `padding-inline-start`
  on blocks and text styles on spans by `StyleAttributeSanitizer`, the common wrappers of other
  editors (`div`, `section`, tables and their cells, `font`, ...) blocked (tag dropped, text kept),
  everything else dropped with its content, no input length limit. The HTML the editor exports
  passes the sanitizer with nothing removed (its one spelling change: the boolean
  `allowfullscreen=""` comes out bare). `symfony/html-sanitizer` is a
  new requirement of the bundle.
- The `sanitize` form option and bundle configuration key (default `true`): a changed submission
  is sanitised with the same allowlist on the way in, the server-side counterpart of the
  editor's checks, so a client without JavaScript cannot store what the editor would never
  produce. A submission equal to the string the field was rendered with is left alone - it is
  the application's own data and may legitimately hold markup from elsewhere, so an untouched
  field never loses anything.
- `assets/styles/lexical-content.css`: the stylesheet of the stored content (paragraphs, lists,
  links, quotes, code, embeds), scoped to `.lexical-content`. The editor's surface carries that
  class and the controller imports the file, so the editor shows what the page will show; a page
  rendering content includes the same file once.

### Changed

- The block rules of `assets/styles/lexical.css` (`.lexical__p`, `.lexical__ul`, `.lexical__ol`,
  `.lexical__li`, `.lexical__link`) moved to `lexical-content.css` as element rules under
  `.lexical-content`; the theme classes themselves stay on the editor's DOM. A stylesheet that
  overrode those class rules should target `.lexical-content p` (and friends) instead, which
  now also styles the rendered page.
- The HTML the field stores is plain and semantic. Lexical's own export spells a document the
  way the editor renders it - a theme class on every block (`<p class="lexical__p">`), every run
  of text in a `<span style="white-space: pre-wrap;">`, formats doubled up as `<b><strong>` - and
  that is what reached the textarea as soon as a document was edited. The controller now hands
  Lexical an `html.export` override (`assets/src/html-export.js`): blocks keep everything but the
  theme class and the `data-lexical-indent` bookkeeping (alignment and indentation stay as the
  `text-align` / `padding-inline-start` inline styles Lexical reads back), and a run of text is a
  bare text node wrapped in `<strong>`, `<em>`, `<u>`, `<s>`, `<sub>`, `<sup>`, `<code>` or
  `<mark>` for each format it carries (a `<span style>` only when it has an inline style of its
  own). `<p>Hello <strong>bold</strong> and <em>italic</em> text.</p>` is now stored as exactly
  that. Content stored by earlier versions re-opens unchanged - the classes and spans were always
  ignored on import - and is rewritten in the new form the next time it is edited and saved.
  Consecutive spaces, which the dropped `white-space: pre-wrap` kept apart when the stored HTML
  was rendered, now collapse as in any HTML; the editor never showed them anyway. Verified in
  the browser: the exported document re-imports and re-exports byte for byte, embeds, links,
  alignment and indentation included.

- The default toolbar is leaner. `LexicalFormType::DEFAULT_TOOLBAR` now lists `undo`, `redo` /
  `bold`, `italic`, `underline`, `strikethrough`, `remove-format` / `bullet`, `number` / `indent`,
  `outdent` / `link`, `unlink` / `iframe` (14 buttons in six groups); the clipboard buttons,
  alignment, sub/superscript and the source editor moved to the new `full` preset. Nothing is
  gone: a field that wants the previous toolbar sets `'preset' => 'full'`, an application sets
  `flexible_ux_lexical.toolbar` (or `presets`) once. The `AVAILABLE_BUTTONS` list and every button
  name are unchanged. Applications that never configured `toolbar` get the lean default after
  updating, which is the point: what a text field needs, nothing more, Rails style.

### Added

- Presets. The bundle configuration gains `presets`, a map of named option sets (`toolbar`,
  `height`, `allowed_link_schemes`) a field selects with the new `preset` form option; a key a
  preset leaves out falls back to the `default` preset, which is what the top-level keys have
  always configured. `full` (every button, otherwise the defaults) is built in and may be
  redefined. A preset toolbar with an unknown entry fails at the first cache warm-up, naming the
  preset, and an unknown `preset` option fails like any other invalid option. The form type now
  takes the presets as its single constructor argument (`flexible_ux_lexical.presets` parameter);
  the `.toolbar`, `.height` and `.allowed_link_schemes` parameters are gone. The widget carries
  `data-preset="<name>"` for stylesheets.

### Fixed

- An empty required field no longer blocks the form silently. The textarea keeps Symfony's
  `required` attribute but is `display: none` once the editor is up, so the browser's validation
  stopped the submission without any message ("An invalid form control with name='...' is not
  focusable" in the console was all there was). The controller now listens to the textarea's
  `invalid` event, shows the browser's own message in a `role="alert"` slot under the editor
  (`lexical__error`), marks the surface `aria-invalid` and focuses the editor when it is the first
  invalid control of the form; the first change to the document clears the message.

### Added

- A `placeholder` passed through the field's `attr` option is shown in the editor while the document
  is empty, the way the textarea would show it (`lexical--empty` on the wrapper, a `::before`
  pseudo-element in the stylesheet).
- The editing surface now has an accessible name: the field's label is linked to it with
  `aria-labelledby` (the label gets an id when it has none) and a click on the label focuses the
  editor; `aria-label` and `aria-describedby` of the textarea are mirrored, `aria-required`
  follows the `required` option and the toolbar's toggle buttons (text formats, alignment, lists,
  link, iframe) expose `aria-pressed`.
- A Symfony Flex recipe, kept in `recipe/` in the layout symfony/recipes-contrib expects under
  `flexible-ux/lexical-bundle/1.0/`: `manifest.json` registers the bundle for all environments and
  declares the `lexical` / `lexical-bundle` aliases, `config/packages/flexible_ux_lexical.yaml` is
  copied into the application with every application-wide default commented out, and
  `post-install.txt` tells `composer require` what to print. Verified against Flex 2.11 through a
  local recipe endpoint: the bundle lands in `config/bundles.php`, the configuration file is
  created and `symfony.lock` records the recipe. The directory is `export-ignore`d, so it never
  reaches a consuming application. `recipe/README.md` explains how to submit it and how to keep
  the two copies in sync.

### Changed

- The installation instructions (README and `docs/index.md`) no longer tell Flex users to register
  the bundle in `config/bundles.php` by hand. Flex has always done that on its own for packages of
  type `symfony-bundle` - it synthesises an "auto-generated recipe" from the PSR-4 namespace and
  the bundle class, as a `composer require` against an endpoint without the recipe confirmed - so
  with Flex the whole installation is `composer require flexible-ux/lexical-bundle`; the manual
  steps are now documented for applications without Flex only.
- The Lexical packages in the bundle's importmap (`assets/package.json`) are bumped from `^0.52.0`
  to `^0.53.0`, and the documented `importmap:require` command (README and `docs/index.md`) now
  carries `^0.53.0`. None of the four
  [v0.53.0](https://github.com/facebook/lexical/releases/tag/v0.53.0) breaking changes reaches
  the bundle:
  - *The extensions deprecated in v0.52.0 and earlier are removed* (`LinkImportExtension`,
    `ListImportExtension`, `RichTextImportExtension`, `HorizontalRuleImportExtension` and the
    code, table and mdast ones). The controller takes only `HistoryExtension` as an extension;
    rich text, lists and links are still wired through `registerRichText()`, `registerList()`
    and the `TOGGLE_LINK_COMMAND` handler, none of which is deprecated.
  - *`ElementNode.reconcileObservedMutation` restores the committed children's DOM* - the
    internal method behind the new mouse-drag selection across inline decorators. The bundle
    never calls it, and its one decorator, `IframeNode`, is a block (`isInline()` is false), so
    the new pointer handling does not apply to it. Verified with a real click on an embed: it
    still yields a `NodeSelection`, the toolbar button lights up, Backspace removes it and
    `undo` brings it back.
  - *`@lexical/mdast` stores table alignment as the cell paragraphs' format.* The bundle does not
    use `@lexical/mdast`.
  - *The unchecked generic type arguments of `getParent()`, `$getNodeByKey()` and friends are
    removed.* A TypeScript-only change: the controller is plain JavaScript and already calls
    `$getNodeByKey(key)` without a type argument.
  Of the behaviour changes, the two in `@lexical/link` concern `ClickableLinkExtension` /
  `registerClickableLink`, which the bundle does not register (links are edited through the
  toolbar dialog, not followed), and the link dialog already sets `rel="noopener noreferrer"`
  on new-tab links; `$patchStyleText` and the Markdown, code and React changes are not used.
  One fix does reach the bundle: `$toggleLink(null)`, which the `unlink` button dispatches, no
  longer fails when the selection only touches a link's edge (facebook/lexical#9412). On
  0.52.0 a selection anchored at the first character of a link and extended backwards out of it
  left the button enabled but turned the click into a no-op, with a `Cannot read properties of
  undefined (reading 'insertAfter')` error in the console; on 0.53.0 the same selection returns
  quietly. Verified against 0.53.0 through AssetMapper's `importmap:require` and
  StimulusBundle's loader like the previous bumps: the editor mounts, a form that was only
  opened still submits its stored string, the allowlists still unwrap a `javascript:` link and
  drop a `data:` frame on load, the toolbar follows real clicks and arrow keys, bold pressed on
  a caret applies to the text typed next, and the link, iframe and source modals, lists,
  indentation, alignment, `remove-format`, `undo` / `redo`, `cut` / `copy` of an embed and its
  paste back, `paste-word` list rebuilding, the clipboard-denied hint, both `toJSON()` forms and
  a Stimulus disconnect / reconnect cycle behave as before.
- A consuming application's `importmap.php` gains two entries after the update,
  `@lexical/extension/BlockIdExtension` and `@lexical/extension/NodeStructureExtension`: the
  two new opt-in extensions are exported from `@lexical/extension`'s main entry, so AssetMapper
  maps their modules like the other `@lexical/extension/*` ones, and the nine packages now pull
  in 44 entries instead of 42 (the README says so). `@lexical/html/BlockIdHTMLExtension` has an
  entry point of its own and is not pulled in. The bundle registers neither extension, and
  Lexical documents that editors which do not register them are unchanged. Re-running the
  documented `importmap:require` command over a 0.52.0 map re-pins every entry at 0.53.0 and
  leaves nothing behind; `@preact/signals-core` moves from 1.14.4 to 1.14.5, within the
  `^1.14.4` range `@lexical/extension` requires.

## [1.0.4] - 2026-10-05

### Fixed

- A form that was only opened no longer counts as modified. On load the controller wrote the
  editor's HTML back into the textarea, which is seldom the string the field was rendered with —
  the editor puts a class on every paragraph, wraps every run of text in a span and decodes
  entities — so a guard that compares the form with its initial state (Sonata Admin's
  `confirm_exit`, for one) asked to confirm leaving a form nobody had touched, and saving it
  rewrote the stored markup. The textarea now keeps the string it was rendered with until the
  document changes, and gets it back when an edit is undone to the loaded document. The exception
  is content that loses a link or an embed to the allowlists while loading: that still reaches the
  textarea at once, so "opening and saving strips it" holds as documented.

### Changed

- The textarea's `input` event is dispatched when its value changes, no longer on every editor
  commit: a caret move and the initial load say nothing.
- The `source` modal of a document that has not changed shows the stored markup rather than the
  editor's own rendering of it — still the exact string the field would submit. Confirming it as
  it is leaves the field alone; confirming it edited hands the field the editor's HTML, as before.

## [1.0.3] - 2026-09-30

### Changed

- The Lexical packages in the bundle's importmap (`assets/package.json`) are bumped from `^0.51.0`
  to `^0.52.0`, and the documented `importmap:require` command (README and `docs/index.md`) now
  carries `^0.52.0`. Of the three
  [v0.52.0](https://github.com/facebook/lexical/releases/tag/v0.52.0) breaking changes, one
  touched the controller and the other two do not reach the bundle:
  - *`SELECTION_CHANGE_COMMAND` now runs before DOM reconciliation, inside the pending update.*
    The controller refreshed the toolbar from a handler of that command as well as from its
    update listener. Under the new timing the handler would read the committed (previous) state
    and look for embed wrappers the DOM does not hold yet, and it was redundant anyway: every
    selection change commits an update, so the update listener already refreshes the toolbar
    after each one. The handler is gone; the toolbar is driven by the update listener alone.
    Verified with real clicks and arrow keys on bold text, a list item, a link and an embed: each
    produces exactly one commit and the matching button state. No behaviour change.
  - *Default HTML export honours `DOMRenderExtension` `$createDOM` overrides.* The bundle registers
    no such extension and `IframeNode.exportDOM()` builds its own element, so the exported HTML is
    byte-for-byte what 0.51 produced.
  - *`LexicalNode.getCommonAncestor` is removed.* The bundle never called it.
  The rest of the release (mobile input fixes, caret scrolling, drag-and-drop, the `@lexical/mdast`
  and `@lexical/headless` work) does not affect the bundle. The importmap keeps its 44 entries, all
  at 0.52.0, and `@preact/signals-core` stays at 1.14.4, which already satisfies the `^1.14.4`
  `@lexical/extension` now requires. Verified against 0.52.0 through AssetMapper's
  `importmap:require` and StimulusBundle's loader like the 1.0.1 bump: the editor mounts, the HTML
  round-trip, the toolbar, `undo` / `redo`, the link and embed allowlists, the three modals and an
  embed's JSON paths (clipboard, legacy and compact `toJSON()`) behave as before.
- The npm-side version in `assets/package.json`, left at 1.0.1 through the 1.0.2 tag, tracks the
  bundle version again.

## [1.0.2] - 2026-09-18

### Added

- German, French, Italian and Portuguese translations of every label in the `FlexibleUxLexical`
  domain (`translations/FlexibleUxLexical.{de,fr,it,pt}.xlf`), alongside the English, Spanish and
  Catalan catalogues shipped so far. The `pt` catalogue follows European Portuguese conventions.

### Changed

- The `lexical_widget` form theme reads its fallbacks for the editable height and the toolbar
  separator through Twig's `constant()` (`LexicalFormType::DEFAULT_HEIGHT` and `::SEPARATOR`)
  instead of repeating the literal values, so the form type stays the single source of both.
  Rendered markup is unchanged; an integration test covers the fallback branch.
- The npm-side version in `assets/package.json`, left at 0.7.2 through the 1.0.0 and 1.0.1 tags,
  tracks the bundle version again.

## [1.0.1] - 2026-09-18

### Changed

- The Lexical packages in the bundle's importmap (`assets/package.json`) are bumped from `^0.50.0`
  to `^0.51.0`. None of the three
  [v0.51.0](https://github.com/facebook/lexical/releases/tag/v0.51.0) breaking changes reaches
  the bundle:
  - *npm packages are ESM only.* The importmap has always loaded jsDelivr's ESM build, and no
    CommonJS is involved anywhere between AssetMapper and the browser, so nothing changes for a
    consuming application.
  - *`exportJSON` may serialize the instance as-is.* This only concerns nodes that declare their
    properties with the new `withField` schema API. The bundle's `IframeNode` keeps its
    hand-written `exportJSON()`, which reads through `getLatest()`, and Lexical's own nodes are
    exported from the node map exactly as before.
  - *`@lexical/code` drops its Prism re-exports.* The bundle does not use `@lexical/code`.
  The new declarative serialization schema (`$config().json`) is not adopted: `IframeNode` stays on
  `getType()` / `clone()` / `importJSON()` / `exportJSON()`, none of which 0.51 deprecates, and its
  JSON output is unchanged. Verified against 0.51.0 through AssetMapper's `importmap:require` and
  StimulusBundle's loader: the editor mounts, the HTML round-trip, the toolbar (formats, alignment,
  lists, `undo` / `redo` from the history signals), the link and embed allowlists and the three
  modals behave as before, and an embed survives both JSON paths — the clipboard one behind
  `cut` / `copy` / `paste` of a selected embed, and `editorState.toJSON()` in its legacy and new
  compact (`toJSON(true)`) forms, which parse back to the same document.
- `@lexical/extension` is now published as one entry per extension, so a consuming application's
  `importmap.php` gains 30 entries after the update: the 29 `@lexical/extension/*` modules the
  other packages import directly, plus `@preact/signals-core`, which reached the browser inside
  `@lexical/extension` until now and becomes an entry of its own. It is the same code split into
  more files, and AssetMapper resolves it on its own — with Flex, `composer update` notices that
  the pinned 0.50.0 no longer satisfies `^0.51.0` and re-runs `importmap:require` for the nine
  packages; without Flex, run the documented `importmap:require` command again, which re-pins the
  nine packages and everything they pull in. That command (README and `docs/index.md`) now carries
  the `^0.51.0` constraint the bundle declares, so a non-Flex install gets the version the bundle
  was verified against, as Flex does from `assets/package.json`; the bundle's own imports are the
  same nine packages.

## [1.0.0] - 2026-09-05

### Changed

- The Lexical packages in the bundle's importmap (`assets/package.json`) are bumped from `^0.49.0`
  to `^0.50.0`. None of the [v0.50.0](https://github.com/facebook/lexical/blob/main/CHANGELOG.md)
  breaking changes reaches the bundle; the toolbar, the three modals, the scheme allowlists and the
  HTML round-trip were verified against it.
- A paragraph ending in a line break (<kbd>Shift</kbd>+<kbd>Enter</kbd> with nothing typed after
  it) is now stored as `<br><br data-lexical-managed-linebreak="true">` instead of a lone `<br>`.
  The marker is what makes that break survive a reload — through 0.49 it was saved and then dropped
  on the next load — so rendered output gains one blank line in that case. Stable across repeated
  saves, and content stored by earlier versions is unaffected.
- `undo` / `redo` availability now comes from `HistoryExtension`'s `canUndo` / `canRedo` signals
  instead of `CAN_UNDO_COMMAND` / `CAN_REDO_COMMAND`, deprecated by Lexical in 0.49. Reaching them
  means building the editor with `buildEditorFromExtensions()` rather than `createEditor()`, so
  `disconnect()` now disposes it. History is the only extension adopted — rich text, lists and
  links stay plain `register*()` calls. No behaviour change.

### Added

- `@lexical/extension` in the bundle's importmap (`assets/package.json`), required by the
  `HistoryExtension` migration above. With Flex it lands in `importmap.php` automatically on
  install; without Flex it is part of the documented `importmap:require` command, which the README
  and `docs/index.md` now include it in. It was already a transitive dependency of
  `@lexical/history`, so nothing new is downloaded.

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

[Unreleased]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v1.0.4...HEAD
[1.0.4]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v1.0.3...v1.0.4
[1.0.3]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/Flexible-User-Experience/lexical-bundle/compare/v0.7.2...v1.0.0
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
