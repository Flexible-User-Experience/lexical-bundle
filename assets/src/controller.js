import '../styles/lexical.css';

import { Controller } from '@hotwired/stimulus';
import {
    createEditor,
    $getRoot,
    $getNodeByKey,
    $getNearestNodeFromDOMNode,
    $getSelection,
    $setSelection,
    $createNodeSelection,
    $isRangeSelection,
    $isNodeSelection,
    $isElementNode,
    $isTextNode,
    $insertNodes,
    $nodesOfType,
    $createParagraphNode,
    CLICK_COMMAND,
    FORMAT_TEXT_COMMAND,
    FORMAT_ELEMENT_COMMAND,
    INDENT_CONTENT_COMMAND,
    OUTDENT_CONTENT_COMMAND,
    UNDO_COMMAND,
    REDO_COMMAND,
    CAN_UNDO_COMMAND,
    CAN_REDO_COMMAND,
    CUT_COMMAND,
    COPY_COMMAND,
    SELECTION_CHANGE_COMMAND,
    COMMAND_PRIORITY_LOW,
} from 'lexical';
import { registerRichText, HeadingNode, QuoteNode } from '@lexical/rich-text';
import { $generateHtmlFromNodes, $generateNodesFromDOM } from '@lexical/html';
import { $insertDataTransferForRichText } from '@lexical/clipboard';
import {
    ListNode,
    ListItemNode,
    INSERT_UNORDERED_LIST_COMMAND,
    INSERT_ORDERED_LIST_COMMAND,
    REMOVE_LIST_COMMAND,
    registerList,
} from '@lexical/list';
import { LinkNode, TOGGLE_LINK_COMMAND, $isLinkNode, $toggleLink } from '@lexical/link';
import { registerHistory, createEmptyHistoryState } from '@lexical/history';
import {
    mergeRegister,
    $getNearestNodeOfType,
    $findMatchingParent,
    $insertNodeToNearestRoot,
} from '@lexical/utils';
import { IframeNode, $createIframeNode, $isIframeNode } from './iframe-node.js';

// Lexical node → CSS class map. The classes themselves live in
// ../styles/lexical.css, keeping this file behaviour-only.
const THEME = {
    paragraph: 'lexical__p',
    text: {
        bold: 'lexical__bold',
        italic: 'lexical__italic',
        underline: 'lexical__underline',
        strikethrough: 'lexical__strikethrough',
        subscript: 'lexical__subscript',
        superscript: 'lexical__superscript',
    },
    list: { ul: 'lexical__ul', ol: 'lexical__ol', listitem: 'lexical__li' },
    link: 'lexical__link',
    // Read by IframeNode.createDOM() rather than by Lexical itself, so the embed's
    // wrapper class is declared here with all the others instead of inside the node.
    iframe: 'lexical__iframe',
};

// Toolbar commands handled straight by Lexical's FORMAT_TEXT_COMMAND. These are the
// toggles whose pressed state the toolbar reflects.
const TEXT_FORMATS = new Set(['bold', 'italic', 'underline', 'strikethrough', 'subscript', 'superscript']);

// Block-indentation commands, handled by rich text. Unlike TEXT_FORMATS these are
// one-shot actions, so they never light up as "active".
const INDENT_COMMANDS = {
    indent: INDENT_CONTENT_COMMAND,
    outdent: OUTDENT_CONTENT_COMMAND,
};

// One-shot history commands, handled by the registered history plugin. The buttons are
// enabled/disabled from the CAN_UNDO/CAN_REDO payloads rather than an active state.
const HISTORY_COMMANDS = {
    undo: UNDO_COMMAND,
    redo: REDO_COMMAND,
};

// Clipboard-out commands, handled by rich text. Dispatched with a null payload, which
// makes the handler synthesise the clipboard event itself — no Clipboard API
// permission is involved, unlike the paste buttons.
const CLIPBOARD_COMMANDS = {
    cut: CUT_COMMAND,
    copy: COPY_COMMAND,
};

// Toolbar command → Lexical element format. Dispatched through FORMAT_ELEMENT_COMMAND
// (handled by rich text) and reflected radio-style: the button matching the current
// block's format lights up, none when the block still has the default ('') format.
const ALIGN_FORMATS = {
    'align-left': 'left',
    'align-center': 'center',
    'align-right': 'right',
    'align-justify': 'justify',
};

// Fallback for the `allowedLinkSchemes` value, used when a custom form theme does not
// pass the attribute. Mirrors LexicalFormType::DEFAULT_ALLOWED_LINK_SCHEMES. Whatever the
// list, anything outside it — notably `javascript:` and `data:` — is rejected, so stored
// HTML that a frontend renders as raw markup cannot carry an XSS payload.
const DEFAULT_ALLOWED_LINK_SCHEMES = ['http', 'https', 'mailto', 'tel'];

// Schemes an `<iframe>` may be pointed at. Deliberately fixed and narrower than
// `allowed_link_schemes`: that list legitimately carries `mailto`/`tel` (and can be
// widened per field), none of which means anything as a frame source — while a frame
// *runs* what it loads, so `javascript:` and `data:` must never reach one.
const EMBEDDABLE_SCHEMES = ['http:', 'https:'];

// What the iframe dialog accepts as a width or height: a number of pixels or a
// percentage, the two values the HTML dimension attributes take. Empty means "unset".
const EMBED_SIZE_PATTERN = /^\d+(\.\d+)?%?$/;

/**
 * Behaviour for FlexibleUx\Form\Type\LexicalFormType. The `lexical_widget` form theme
 * owns the markup — toolbar (icons via `ux_icon`), editable surface, the hidden
 * textarea and the link, source and iframe modals — and this controller wires Meta's
 * Lexical to it: it mounts the editor on the `editable` target and keeps the `input`
 * target (the textarea) in sync with the editor's HTML. Buttons reach it through Stimulus
 * actions/targets.
 */
export default class extends Controller {
    static targets = [
        'input', 'editable', 'button',
        'dialog', 'urlInput', 'newTab',
        'sourceDialog', 'sourceInput',
        'iframeDialog', 'iframeUrlInput', 'iframeWidthInput', 'iframeHeightInput', 'iframeTitleInput',
        'iframeFullscreen',
    ];
    static values = {
        invalidUrlMessage: String,
        invalidEmbedUrlMessage: String,
        invalidEmbedSizeMessage: String,
        clipboardDeniedMessage: String,
        allowedLinkSchemes: { type: Array, default: DEFAULT_ALLOWED_LINK_SCHEMES },
    };

    connect() {
        this.#createEditor();
        this.element.classList.add('lexical--ready');
    }

    disconnect() {
        this.element.classList.remove('lexical--ready');
        if (this.teardown) {
            this.teardown();
            this.teardown = null;
        }
        if (this.editor) {
            this.editor.setRootElement(null);
            this.editor = null;
        }
    }

    // --- Toolbar actions (bound in the form theme via data-action) ---------

    // Keep the editor selection alive while a toolbar button is pressed.
    preventBlur(event) {
        event.preventDefault();
    }

    command(event) {
        const command = event.currentTarget.dataset.command;
        if (command in HISTORY_COMMANDS) {
            this.editor.dispatchCommand(HISTORY_COMMANDS[command], undefined);
        } else if (command in CLIPBOARD_COMMANDS) {
            this.editor.dispatchCommand(CLIPBOARD_COMMANDS[command], null);
        } else if ('paste' === command || 'paste-word' === command) {
            this.#paste('paste-word' === command);
        } else if ('remove-format' === command) {
            this.#removeFormat();
        } else if (TEXT_FORMATS.has(command)) {
            this.editor.dispatchCommand(FORMAT_TEXT_COMMAND, command);
        } else if (command in ALIGN_FORMATS) {
            this.editor.dispatchCommand(FORMAT_ELEMENT_COMMAND, ALIGN_FORMATS[command]);
        } else if (command in INDENT_COMMANDS) {
            this.editor.dispatchCommand(INDENT_COMMANDS[command], undefined);
        } else if ('bullet' === command || 'number' === command) {
            this.#toggleList(command);
        } else if ('link' === command) {
            this.#toggleLink();
        } else if ('unlink' === command) {
            this.#removeLink();
        } else if ('iframe' === command) {
            this.#openIframe();
        } else if ('source' === command) {
            this.#openSource();
        }
    }

    // Let a host "unsaved changes" guard notice edits.
    markChanged() {
        this.inputTarget.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // Apply the URL from the link modal to the stashed selection. The modal uses plain
    // buttons (never a nested <form>), so this is a click handler, not a submit.
    confirmLink() {
        const url = this.urlInputTarget.value.trim();
        this.urlInputTarget.setCustomValidity('' !== url && !this.#isSafeUrl(url) ? this.invalidUrlMessageValue : '');
        if (!this.urlInputTarget.reportValidity()) {
            return;
        }
        const newTab = this.newTabTarget.checked;
        this.dialogTarget.close();
        this.dialogClosed();
        if ('' !== url) {
            this.editor.update(() => {
                if (this.linkSelection) {
                    $setSelection(this.linkSelection.clone());
                }
            });
            this.editor.dispatchCommand(TOGGLE_LINK_COMMAND, {
                url,
                target: newTab ? '_blank' : null,
                rel: newTab ? 'noopener noreferrer' : null,
            });
        }
        this.linkSelection = null;
    }

    // A URL is safe when it parses and its scheme is in the configured allowlist (the
    // `allowed_link_schemes` form option). The URL constructor normalises the scheme (so
    // HTTPS:// passes) and throws on malformed input, so this also rejects
    // javascript:/data: and anything that is not a real absolute URL.
    #isSafeUrl(value) {
        let protocol;
        try {
            protocol = new URL(value).protocol.toLowerCase();
        } catch {
            return false;
        }

        // Entries may be written with or without the trailing colon.
        return this.allowedLinkSchemesValue.some(
            (scheme) => `${String(scheme).trim().toLowerCase().replace(/:$/, '')}:` === protocol,
        );
    }

    // Enter in the URL field confirms (there is no form to submit).
    dialogKeydown(event) {
        if ('Enter' === event.key) {
            event.preventDefault();
            this.confirmLink();
        }
    }

    // Dismiss the link modal without changing the document (Cancel button).
    closeDialog() {
        this.dialogTarget.close();
        this.dialogClosed();
        this.linkSelection = null;
    }

    // The url field has no name and can sit inside the surrounding <form>, so a leftover
    // invalid value would make that form unsubmittable while the dialog is closed
    // (display:none): "An invalid form control with name='' is not focusable". Clearing
    // it on every close keeps the hidden field valid; the next open repopulates it from
    // the link. Called from the button handlers and wired to the dialog's `close` event
    // as a fallback for Escape.
    dialogClosed() {
        this.urlInputTarget.setCustomValidity('');
        this.urlInputTarget.value = '';
    }

    // Apply the HTML from the source modal as the new document. A plain update (unlike
    // the initial load, which merges into history) so the whole swap lands as a single
    // undoable step. The re-import round-trips the text through Lexical's model, so
    // whatever the model cannot represent simply does not survive into the document —
    // and the LinkNode transform unwraps any imported link with a disallowed scheme.
    confirmSource() {
        const html = this.sourceInputTarget.value.trim();
        this.sourceDialogTarget.close();
        this.editor.update(() => {
            this.#replaceContent(html);
        });
        this.editor.focus();
        this.markChanged();
    }

    // Dismiss the source modal without touching the document (Cancel button).
    closeSourceDialog() {
        this.sourceDialogTarget.close();
    }

    // Insert the embed described by the iframe modal, or update the one being edited.
    // Editing replaces the node rather than mutating it, carrying over the attributes the
    // dialog does not expose (`allow`, `sandbox`), so a pasted embed keeps them.
    confirmIframe() {
        const props = {
            src: this.iframeUrlInputTarget.value.trim(),
            width: this.iframeWidthInputTarget.value.trim(),
            height: this.iframeHeightInputTarget.value.trim(),
            title: this.iframeTitleInputTarget.value.trim(),
            allowFullscreen: this.iframeFullscreenTarget.checked,
        };
        this.iframeUrlInputTarget.setCustomValidity(
            this.#isEmbeddableUrl(props.src) ? '' : this.invalidEmbedUrlMessageValue,
        );
        [
            [this.iframeWidthInputTarget, props.width],
            [this.iframeHeightInputTarget, props.height],
        ].forEach(([field, value]) => {
            field.setCustomValidity(
                '' === value || EMBED_SIZE_PATTERN.test(value) ? '' : this.invalidEmbedSizeMessageValue,
            );
        });
        const invalid = [
            this.iframeUrlInputTarget,
            this.iframeWidthInputTarget,
            this.iframeHeightInputTarget,
        ].find((field) => !field.checkValidity());
        if (undefined !== invalid) {
            invalid.reportValidity();

            return;
        }

        const key = this.iframeKey;
        this.iframeDialogTarget.close();
        this.iframeDialogClosed();
        this.editor.update(() => {
            const edited = null === key ? null : $getNodeByKey(key);
            if ($isIframeNode(edited)) {
                edited.replace($createIframeNode({ ...edited.getProps(), ...props }));

                return;
            }
            // Showing the modal dropped the selection, so restore the stashed clone: the
            // embed lands where the caret was, not at the end of the document.
            if (this.iframeSelection) {
                $setSelection(this.iframeSelection.clone());
            }
            $insertNodeToNearestRoot($createIframeNode(props));
        });
        this.iframeKey = null;
        this.iframeSelection = null;
        this.editor.focus();
        this.markChanged();
    }

    // Dismiss the iframe modal without touching the document (Cancel button).
    closeIframeDialog() {
        this.iframeDialogTarget.close();
        this.iframeDialogClosed();
        this.iframeKey = null;
        this.iframeSelection = null;
    }

    // Enter in any of the text fields confirms (there is no form to submit).
    iframeDialogKeydown(event) {
        if ('Enter' === event.key) {
            event.preventDefault();
            this.confirmIframe();
        }
    }

    // Same housekeeping as the link modal: these fields have no name but do sit inside the
    // surrounding <form>, so a leftover invalid value would make that form unsubmittable
    // while the dialog is hidden. Wired to the dialog's `close` event as well, to cover
    // Escape. The next open repopulates them from the node.
    iframeDialogClosed() {
        [
            this.iframeUrlInputTarget,
            this.iframeWidthInputTarget,
            this.iframeHeightInputTarget,
            this.iframeTitleInputTarget,
        ].forEach((field) => {
            field.setCustomValidity('');
            field.value = '';
        });
        this.iframeFullscreenTarget.checked = false;
    }

    // --- Editor ------------------------------------------------------------

    #createEditor() {
        const editor = createEditor({
            namespace: 'lexical',
            editable: !this.inputTarget.disabled && !this.inputTarget.readOnly,
            nodes: [HeadingNode, QuoteNode, ListNode, ListItemNode, LinkNode, IframeNode],
            theme: THEME,
            onError: (error) => console.error('[lexical]', error),
        });
        this.editor = editor;
        editor.setRootElement(this.editableTarget);

        // History availability, kept current by the CAN_UNDO/CAN_REDO handlers below.
        this.canUndo = false;
        this.canRedo = false;

        this.teardown = mergeRegister(
            registerRichText(editor),
            registerList(editor),
            registerHistory(editor, createEmptyHistoryState(), 300),
            // Vanilla Lexical needs the link toggle wired manually (no React plugin).
            editor.registerCommand(
                TOGGLE_LINK_COMMAND,
                (payload) => {
                    // Payload is a URL string / null (from unlink), or an
                    // { url, target, rel } object (from the link modal).
                    if (null === payload || 'string' === typeof payload) {
                        $toggleLink(payload);
                    } else {
                        const { url, target, rel, title } = payload;
                        $toggleLink(url, { target, rel, title });
                    }

                    return true;
                },
                COMMAND_PRIORITY_LOW,
            ),
            // The scheme allowlist, enforced where every path into the document
            // converges: whichever way a link arrives — the link modal, the source
            // modal, the toolbar paste buttons, a native Ctrl+V paste, drag-and-drop,
            // or the initial load of stored content — a LinkNode whose URL fails the
            // allowlist is unwrapped on the spot (its text stays, the link goes). The
            // link modal validates before dispatching, so its inserts are never
            // touched, and the unwrap joins whatever update created the link, adding
            // no history entry of its own.
            editor.registerNodeTransform(LinkNode, (linkNode) => {
                if (this.#isSafeUrl(linkNode.getURL())) {
                    return;
                }
                linkNode.getChildren().forEach((child) => linkNode.insertBefore(child));
                linkNode.remove();
            }),
            // The same treatment for embeds, at the same single point of convergence: an
            // <iframe> whose src is not an http(s) URL is dropped whichever way it entered
            // the document. There is no text to keep, so the whole node goes.
            editor.registerNodeTransform(IframeNode, (iframeNode) => {
                if (!this.#isEmbeddableUrl(iframeNode.getSrc())) {
                    iframeNode.remove();
                }
            }),
            // An embed's preview is inert (pointer events off), so a click lands on its
            // wrapper: turn that into a NodeSelection, which is what makes the block
            // highlight, Backspace/Delete remove it and the toolbar button edit it.
            editor.registerCommand(
                CLICK_COMMAND,
                (event) => {
                    const wrapper = event.target instanceof Element
                        ? event.target.closest(`.${THEME.iframe}`)
                        : null;
                    if (null === wrapper) {
                        return false;
                    }
                    const node = $getNearestNodeFromDOMNode(wrapper);
                    if (!$isIframeNode(node)) {
                        return false;
                    }
                    const selection = $createNodeSelection();
                    selection.add(node.getKey());
                    $setSelection(selection);

                    return true;
                },
                COMMAND_PRIORITY_LOW,
            ),
            // The undo/redo buttons mirror the history stacks, whose availability only
            // ever arrives through these two payloads — refresh as soon as one does.
            editor.registerCommand(
                CAN_UNDO_COMMAND,
                (canUndo) => {
                    this.canUndo = canUndo;
                    this.#refreshToolbar(editor.getEditorState());

                    return false;
                },
                COMMAND_PRIORITY_LOW,
            ),
            editor.registerCommand(
                CAN_REDO_COMMAND,
                (canRedo) => {
                    this.canRedo = canRedo;
                    this.#refreshToolbar(editor.getEditorState());

                    return false;
                },
                COMMAND_PRIORITY_LOW,
            ),
            editor.registerUpdateListener(({ editorState }) => {
                this.#syncOut(editorState);
                this.#refreshToolbar(editorState);
            }),
            editor.registerCommand(
                SELECTION_CHANGE_COMMAND,
                () => {
                    this.#refreshToolbar(editor.getEditorState());

                    return false;
                },
                COMMAND_PRIORITY_LOW,
            ),
        );

        this.#loadInitialHtml();
    }

    #loadInitialHtml() {
        const html = (this.inputTarget.value || '').trim();
        this.editor.update(
            () => this.#replaceContent(html),
            { tag: 'history-merge', discrete: true },
        );
    }

    // Swap the whole document for the given HTML, falling back to one empty paragraph.
    // Must run inside an editor.update() scope.
    #replaceContent(html) {
        const root = $getRoot();
        root.clear();
        if ('' !== html) {
            const dom = new DOMParser().parseFromString(html, 'text/html');
            const nodes = $generateNodesFromDOM(this.editor, dom);
            root.select();
            $insertNodes(nodes);
        }
        if (0 === $getRoot().getChildrenSize()) {
            $getRoot().append($createParagraphNode());
        }
    }

    #syncOut(editorState) {
        editorState.read(() => {
            // Embeds carry no text, so a document holding nothing but one is empty by the
            // text-content measure — and would be saved as an empty string.
            const isEmpty = '' === $getRoot().getTextContent().trim() && 0 === $nodesOfType(IframeNode).length;
            this.inputTarget.value = isEmpty ? '' : $generateHtmlFromNodes(this.editor, null);
        });
        this.inputTarget.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // --- Toolbar operations ------------------------------------------------

    #toggleList(type) {
        // #readListType is a read helper, so give it the read scope it requires —
        // calling it bare throws "Unable to find an active editor state".
        const active = this.editor.getEditorState().read(() => this.#readListType()) === type;
        if (active) {
            this.editor.dispatchCommand(REMOVE_LIST_COMMAND, undefined);
        } else {
            const command = 'bullet' === type ? INSERT_UNORDERED_LIST_COMMAND : INSERT_ORDERED_LIST_COMMAND;
            this.editor.dispatchCommand(command, undefined);
        }
    }

    // Open the link modal, pre-filled with any existing URL. Showing the dialog moves
    // focus out of the editable and drops Lexical's selection, so stash a clone now and
    // restore it in `confirmLink` before the link is applied.
    #toggleLink() {
        let currentUrl = null;
        let currentTarget = null;
        this.linkSelection = this.editor.getEditorState().read(() => {
            const linkNode = this.#linkNode();
            currentUrl = linkNode ? linkNode.getURL() : null;
            currentTarget = linkNode ? linkNode.getTarget() : null;
            const selection = $getSelection();

            return $isRangeSelection(selection) ? selection.clone() : null;
        });
        this.urlInputTarget.value = currentUrl || '';
        this.newTabTarget.checked = '_blank' === currentTarget;
        this.dialogTarget.showModal();
        this.urlInputTarget.select();
    }

    // Strip the link around the current selection — TOGGLE_LINK_COMMAND with a null
    // payload unwraps it. The button is disabled unless the caret is inside a link, so
    // this only fires when there is something to remove.
    #removeLink() {
        this.editor.dispatchCommand(TOGGLE_LINK_COMMAND, null);
    }

    // Open the iframe modal. With an embed selected it edits that one (fields pre-filled,
    // the button rendering as active); otherwise it inserts a new one at the caret — and,
    // as in #toggleLink, the modal takes the focus and with it Lexical's selection, so
    // stash a clone for `confirmIframe` to restore.
    #openIframe() {
        let props = null;
        this.iframeKey = null;
        this.iframeSelection = this.editor.getEditorState().read(() => {
            const node = this.#selectedIframeNode();
            if (null !== node) {
                this.iframeKey = node.getKey();
                props = node.getProps();
            }
            const selection = $getSelection();

            return $isRangeSelection(selection) ? selection.clone() : null;
        });
        this.iframeUrlInputTarget.value = props?.src ?? '';
        this.iframeWidthInputTarget.value = props?.width ?? '';
        this.iframeHeightInputTarget.value = props?.height ?? '';
        this.iframeTitleInputTarget.value = props?.title ?? '';
        this.iframeFullscreenTarget.checked = props?.allowFullscreen ?? false;
        this.iframeDialogTarget.showModal();
        this.iframeUrlInputTarget.select();
    }

    // An iframe src is accepted when it resolves to an http(s) URL. Relative URLs are
    // fine — they resolve against the page, which is where the stored HTML is rendered —
    // while `javascript:`, `data:` and malformed input are not. Also used by the node
    // transform, so this is the single rule every embed in the document has passed.
    #isEmbeddableUrl(value) {
        const src = String(value).trim();
        if ('' === src) {
            return false;
        }
        try {
            return EMBEDDABLE_SCHEMES.includes(new URL(src, document.baseURI).protocol);
        } catch {
            return false;
        }
    }

    // Open the source modal pre-filled with the editor's current HTML — the exact
    // string the hidden textarea would submit, since #syncOut keeps them identical.
    #openSource() {
        this.sourceInputTarget.value = this.inputTarget.value;
        this.sourceDialogTarget.showModal();
        this.sourceInputTarget.focus();
    }

    // Strip the inline text formats and styles from the selection, leaving the block
    // structure — lists, headings, alignment, indentation — and links alone (CKEditor's
    // RemoveFormat scope). extract() splits the boundary text nodes, so only the
    // selected slice is cleared.
    #removeFormat() {
        this.editor.update(() => {
            const selection = $getSelection();
            if (!$isRangeSelection(selection) || selection.isCollapsed()) {
                return;
            }
            selection.extract().forEach((node) => {
                if (!$isTextNode(node)) {
                    return;
                }
                if (0 !== node.getFormat()) {
                    node.setFormat(0);
                }
                if ('' !== node.getStyle()) {
                    node.setStyle('');
                }
            });
        });
    }

    // Paste the system clipboard at the caret; `fromWord` scrubs Word's markup first.
    // Either way the content goes through Lexical's model like any paste (markup the
    // model cannot represent is dropped) and, like every path into the document, links
    // whose scheme is not allowed are unwrapped by the LinkNode transform.
    async #paste(fromWord) {
        const dataTransfer = await this.#readClipboard();
        if (null === dataTransfer) {
            alert(this.clipboardDeniedMessageValue);

            return;
        }
        if (fromWord) {
            const html = dataTransfer.getData('text/html');
            if ('' !== html) {
                dataTransfer.setData('text/html', this.#cleanWordHtml(html));
            }
        }
        // The permission prompt some browsers show for clipboard.read() may have taken
        // the focus (and with it the selection) away; restore it before inserting.
        this.editor.focus();
        this.editor.update(() => {
            const selection = $getSelection();
            if ($isRangeSelection(selection)) {
                $insertDataTransferForRichText(dataTransfer, selection, this.editor);
            }
        });
    }

    // Read the system clipboard into a DataTransfer carrying the html and plain-text
    // flavours. A toolbar button can only get at the clipboard through the asynchronous
    // Clipboard API (execCommand('paste') is blocked by every modern browser), which
    // needs a secure context and the user's permission — when even the text-only
    // fallback is denied this resolves to null and the caller shows the Ctrl+V hint.
    // Keyboard pasting is native Lexical behaviour and involves none of this.
    async #readClipboard() {
        const dataTransfer = new DataTransfer();
        try {
            for (const item of await navigator.clipboard.read()) {
                for (const type of ['text/html', 'text/plain']) {
                    if (item.types.includes(type)) {
                        dataTransfer.setData(type, await (await item.getType(type)).text());
                    }
                }
            }
        } catch {
            // Denied, insecure context, or an older Firefox without read() — a
            // plain-text paste still beats none.
            try {
                dataTransfer.setData('text/plain', await navigator.clipboard.readText());
            } catch {
                return null;
            }
        }

        return dataTransfer;
    }

    // Scrub the markup Word puts on the clipboard down to what survives the model
    // import anyway, plus the one thing that would otherwise import wrong: the lists.
    // Conditional comments and Office-namespace elements (<o:p>, <v:shape>, …) are
    // dropped; Mso classes and mso-* styles need no handling because the model import
    // ignores them.
    #cleanWordHtml(html) {
        const doc = new DOMParser().parseFromString(html, 'text/html');

        // Word interleaves its markup with conditional comments; collect first, a
        // TreeWalker cannot survive its current node being removed.
        const comments = [];
        const walker = doc.createTreeWalker(doc.body, NodeFilter.SHOW_COMMENT);
        while (walker.nextNode()) {
            comments.push(walker.currentNode);
        }
        comments.forEach((comment) => comment.remove());

        doc.body.querySelectorAll('*').forEach((element) => {
            if (element.localName.includes(':')) {
                element.remove();
            }
        });

        this.#convertWordLists(doc);

        return doc.body.innerHTML;
    }

    // Word encodes a list item as a paragraph whose style carries `mso-list` metadata
    // and whose visible marker ("·", "1.", …) sits in a span styled `mso-list:Ignore`.
    // Without this, a pasted list imports as paragraphs with a literal bullet glyph in
    // front. Each consecutive run of such paragraphs belonging to the same list — Word
    // names the instance with the style's `lfoN` token, so two adjacent but distinct
    // lists stay distinct — becomes one flat <ul>/<ol> (ordered when its first marker
    // looks like "1." / "a)"), and the markers go away with the paragraphs. Nesting
    // levels are not reconstructed.
    #convertWordLists(doc) {
        const isItem = (node) => null !== node && 'p' === node.localName
            && (/mso-list/i.test(node.getAttribute('style') || '') || node.className.includes('MsoListParagraph'));
        const listIdOf = (paragraph) => ((paragraph.getAttribute('style') || '').match(/lfo\d+/i) ?? ['?'])[0];
        const markerOf = (paragraph) => [...paragraph.querySelectorAll('span')].find(
            (span) => /mso-list\s*:\s*ignore/i.test(span.getAttribute('style') || ''),
        ) ?? null;

        [...doc.body.querySelectorAll('p')].forEach((paragraph) => {
            // Only the first paragraph of a run builds the list; the ones it pulls in
            // below are skipped here (once removed they are no longer connected).
            if (!paragraph.isConnected || !isItem(paragraph)) {
                return;
            }
            const id = listIdOf(paragraph);
            const previous = paragraph.previousElementSibling;
            if (null !== previous && isItem(previous) && listIdOf(previous) === id) {
                return;
            }
            const marker = markerOf(paragraph);
            const ordered = null !== marker && /^[0-9a-z]{1,4}[.)]/i.test(marker.textContent.trim());
            const list = doc.createElement(ordered ? 'ol' : 'ul');
            paragraph.before(list);
            for (let item = paragraph; isItem(item) && listIdOf(item) === id; ) {
                const next = item.nextElementSibling;
                markerOf(item)?.remove();
                const entry = doc.createElement('li');
                entry.append(...item.childNodes);
                list.append(entry);
                item.remove();
                item = next;
            }
        });
    }

    #refreshToolbar(editorState) {
        const state = {
            formats: {}, align: null, listType: null, link: false, collapsed: true, iframeKeys: new Set(),
        };
        editorState.read(() => {
            const selection = $getSelection();
            // A click on an embed selects the node itself rather than a range of text.
            if ($isNodeSelection(selection)) {
                selection.getNodes().forEach((node) => {
                    if ($isIframeNode(node)) {
                        state.iframeKeys.add(node.getKey());
                    }
                });
                // Not a caret: there is a selected node to cut or copy.
                state.collapsed = false;

                return;
            }
            if (!$isRangeSelection(selection)) {
                return;
            }
            // Derived from TEXT_FORMATS so new toggles light up without extra wiring.
            TEXT_FORMATS.forEach((format) => {
                state.formats[format] = selection.hasFormat(format);
            });
            state.align = this.#readAlignment();
            state.listType = this.#readListType();
            state.link = null !== this.#linkNode();
            state.collapsed = selection.isCollapsed();
        });

        this.buttonTargets.forEach((button) => {
            const command = button.dataset.command;
            let active = false;
            if (TEXT_FORMATS.has(command)) {
                active = state.formats[command] ?? false;
            } else if (command in ALIGN_FORMATS) {
                active = state.align === ALIGN_FORMATS[command];
            } else if ('bullet' === command || 'number' === command) {
                active = state.listType === command;
            } else if ('link' === command) {
                active = state.link;
            } else if ('iframe' === command) {
                // Lit while an embed is selected: pressing the button then edits that one.
                active = 0 !== state.iframeKeys.size;
            } else if ('unlink' === command) {
                // Nothing to unlink unless the caret sits inside a link.
                button.disabled = !state.link;
            } else if ('undo' === command) {
                button.disabled = !this.canUndo;
            } else if ('redo' === command) {
                button.disabled = !this.canRedo;
            } else if ('cut' === command || 'copy' === command) {
                // Nothing to cut or copy while nothing is selected.
                button.disabled = state.collapsed;
            }
            button.classList.toggle('is-active', active);
        });

        this.#refreshEmbedSelection(state.iframeKeys);
    }

    // Lexical paints no selection of its own on a decorator, so mirror the current
    // NodeSelection onto the embed wrappers — without it, clicking an embed looks like
    // nothing happened, even though Backspace would now remove it.
    #refreshEmbedSelection(keys) {
        const selected = new Set([...keys].map((key) => this.editor.getElementByKey(key)));
        this.editableTarget.querySelectorAll(`.${THEME.iframe}`).forEach((wrapper) => {
            wrapper.classList.toggle('is-selected', selected.has(wrapper));
        });
    }

    // Read helpers — must run inside an editorState.read()/update() scope.

    // Alignment of the block at the caret — the same nearest non-inline element that
    // FORMAT_ELEMENT_COMMAND targets. Returns null for the default ('') format, so no
    // alignment button claims to be active until one is applied.
    #readAlignment() {
        const selection = $getSelection();
        if (!$isRangeSelection(selection)) {
            return null;
        }
        const block = $findMatchingParent(
            selection.anchor.getNode(),
            (node) => $isElementNode(node) && !node.isInline(),
        );

        return (block && block.getFormatType()) || null;
    }

    #readListType() {
        const selection = $getSelection();
        if (!$isRangeSelection(selection)) {
            return null;
        }
        const listNode = $getNearestNodeOfType(selection.anchor.getNode(), ListNode);

        return listNode ? listNode.getListType() : null;
    }

    // The selected embed, or null when the selection is not a node selection holding one.
    #selectedIframeNode() {
        const selection = $getSelection();

        return $isNodeSelection(selection) ? (selection.getNodes().find($isIframeNode) ?? null) : null;
    }

    // The LinkNode at the caret, or null when the selection is not in a link.
    #linkNode() {
        const selection = $getSelection();
        if (!$isRangeSelection(selection)) {
            return null;
        }
        const node = selection.anchor.getNode();

        return $isLinkNode(node) ? node : $findMatchingParent(node, $isLinkNode);
    }
}
