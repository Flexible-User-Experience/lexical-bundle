import '../styles/lexical.css';

import { Controller } from '@hotwired/stimulus';
import {
    createEditor,
    $getRoot,
    $getSelection,
    $setSelection,
    $isRangeSelection,
    $insertNodes,
    $createParagraphNode,
    FORMAT_TEXT_COMMAND,
    SELECTION_CHANGE_COMMAND,
    COMMAND_PRIORITY_LOW,
} from 'lexical';
import { registerRichText, HeadingNode, QuoteNode } from '@lexical/rich-text';
import { $generateHtmlFromNodes, $generateNodesFromDOM } from '@lexical/html';
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
import { mergeRegister, $getNearestNodeOfType, $findMatchingParent } from '@lexical/utils';

// Lexical node → CSS class map. The classes themselves live in
// ../styles/lexical.css, keeping this file behaviour-only.
const THEME = {
    paragraph: 'lexical__p',
    text: {
        bold: 'lexical__bold',
        italic: 'lexical__italic',
        underline: 'lexical__underline',
        strikethrough: 'lexical__strikethrough',
    },
    list: { ul: 'lexical__ul', ol: 'lexical__ol', listitem: 'lexical__li' },
    link: 'lexical__link',
};

// Toolbar commands handled straight by Lexical's FORMAT_TEXT_COMMAND.
const TEXT_FORMATS = new Set(['bold', 'italic', 'underline', 'strikethrough']);

// Fallback for the `allowedLinkSchemes` value, used when a custom form theme does not
// pass the attribute. Mirrors LexicalFormType::DEFAULT_ALLOWED_LINK_SCHEMES. Whatever the
// list, anything outside it — notably `javascript:` and `data:` — is rejected, so stored
// HTML that a frontend renders as raw markup cannot carry an XSS payload.
const DEFAULT_ALLOWED_LINK_SCHEMES = ['http', 'https', 'mailto', 'tel'];

/**
 * Behaviour for FlexibleUx\Form\Type\LexicalFormType. The `lexical_widget` form theme
 * owns the markup — toolbar (icons via `ux_icon`), editable surface, the hidden
 * textarea and the link modal — and this controller wires Meta's Lexical to it: it
 * mounts the editor on the `editable` target and keeps the `input` target (the
 * textarea) in sync with the editor's HTML. Buttons reach it through Stimulus
 * actions/targets.
 */
export default class extends Controller {
    static targets = ['input', 'editable', 'button', 'dialog', 'urlInput', 'newTab'];
    static values = {
        invalidUrlMessage: String,
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
        if (TEXT_FORMATS.has(command)) {
            this.editor.dispatchCommand(FORMAT_TEXT_COMMAND, command);
        } else if ('bullet' === command || 'number' === command) {
            this.#toggleList(command);
        } else if ('link' === command) {
            this.#toggleLink();
        } else if ('unlink' === command) {
            this.#removeLink();
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

    // --- Editor ------------------------------------------------------------

    #createEditor() {
        const editor = createEditor({
            namespace: 'lexical',
            editable: !this.inputTarget.disabled && !this.inputTarget.readOnly,
            nodes: [HeadingNode, QuoteNode, ListNode, ListItemNode, LinkNode],
            theme: THEME,
            onError: (error) => console.error('[lexical]', error),
        });
        this.editor = editor;
        editor.setRootElement(this.editableTarget);

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
            () => {
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
            },
            { tag: 'history-merge', discrete: true },
        );
    }

    #syncOut(editorState) {
        editorState.read(() => {
            const isEmpty = '' === $getRoot().getTextContent().trim();
            this.inputTarget.value = isEmpty ? '' : $generateHtmlFromNodes(this.editor, null);
        });
        this.inputTarget.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // --- Toolbar operations ------------------------------------------------

    #toggleList(type) {
        const active = this.#readListType() === type;
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

    #refreshToolbar(editorState) {
        const state = {
            bold: false,
            italic: false,
            underline: false,
            strikethrough: false,
            listType: null,
            link: false,
        };
        editorState.read(() => {
            const selection = $getSelection();
            if (!$isRangeSelection(selection)) {
                return;
            }
            state.bold = selection.hasFormat('bold');
            state.italic = selection.hasFormat('italic');
            state.underline = selection.hasFormat('underline');
            state.strikethrough = selection.hasFormat('strikethrough');
            state.listType = this.#readListType();
            state.link = null !== this.#linkNode();
        });

        this.buttonTargets.forEach((button) => {
            const command = button.dataset.command;
            let active = false;
            if (TEXT_FORMATS.has(command)) {
                active = state[command];
            } else if ('bullet' === command || 'number' === command) {
                active = state.listType === command;
            } else if ('link' === command) {
                active = state.link;
            } else if ('unlink' === command) {
                // Nothing to unlink unless the caret sits inside a link.
                button.disabled = !state.link;
            }
            button.classList.toggle('is-active', active);
        });
    }

    // Read helpers — must run inside an editorState.read()/update() scope.

    #readListType() {
        const selection = $getSelection();
        if (!$isRangeSelection(selection)) {
            return null;
        }
        const listNode = $getNearestNodeOfType(selection.anchor.getNode(), ListNode);

        return listNode ? listNode.getListType() : null;
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
