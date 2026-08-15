import { $applyNodeReplacement, DecoratorNode } from 'lexical';

// Attributes the preview frame gets on top of the exported ones. The framed page is
// third-party content loading inside the editing session, so it may script itself (a
// video player has to) but must not navigate the page hosting the form away — which is
// what a sandbox without `allow-top-navigation` buys. An embed that arrived with its own
// `sandbox` keeps that one instead: overriding it in the preview would show the user
// something laxer than what they are about to store.
const PREVIEW_SANDBOX = 'allow-scripts allow-same-origin';

// Set an attribute only when there is a value, so an untouched dialog field leaves no
// empty `width=""`/`title=""` behind in the stored markup.
function setIfPresent(element, name, value) {
    if ('' !== value) {
        element.setAttribute(name, value);
    }
}

// The attributes shared by the preview and the exported markup. `sandbox` is not among
// them — see PREVIEW_SANDBOX — and neither is anything else the model does not keep.
function applyEmbedAttributes(element, props) {
    element.setAttribute('src', props.src);
    setIfPresent(element, 'width', props.width);
    setIfPresent(element, 'height', props.height);
    setIfPresent(element, 'title', props.title);
    setIfPresent(element, 'allow', props.allow);
    if (props.allowFullscreen) {
        element.setAttribute('allowfullscreen', '');
    }
}

/**
 * A block-level `<iframe>` embed — the node behind the toolbar's `iframe` button.
 *
 * Lexical keeps only what a registered node class describes, so an `<iframe>` needs one
 * of its own to survive the round-trip through the editor (the same reason the CKEditor
 * setup this replaces needed `extraAllowedContent: 'iframe[*]'`). This node carries the
 * attributes an embed actually needs — `src`, `width`, `height`, `title`, `allow`,
 * `sandbox` and `allowfullscreen` — renders a live but inert preview inside the editable,
 * and exports the plain `<iframe>` a frontend renders. Everything else an imported
 * `<iframe>` may carry (`frameborder`, `referrerpolicy`, …) is normalised away like any
 * other markup the model cannot represent.
 *
 * It is a {@see DecoratorNode}: content Lexical stores but does not edit. The reconciler
 * marks its DOM `contenteditable="false"`, and `decorate()` stays at its inherited `null`
 * because the preview is built in `createDOM()` — no framework binding (React et al.) is
 * involved, matching the rest of this vanilla-Lexical controller.
 */
export class IframeNode extends DecoratorNode {
    static getType() {
        return 'iframe';
    }

    // Reads the raw fields rather than getProps(): clone() must copy the exact version it
    // was handed, not whatever the latest one happens to be.
    static clone(node) {
        return new IframeNode(node.#rawProps(), node.__key);
    }

    /**
     * Registers `<iframe>` with Lexical's HTML import, so an embed reaches the document
     * whichever way its markup arrives: the source modal, a paste, or the initial load of
     * stored content. `allow` and `sandbox` are not offered in the dialog but are read
     * here, so an embed pasted as markup (YouTube ships `allow`) round-trips unchanged.
     */
    static importDOM() {
        return {
            iframe: () => ({
                conversion: (element) => ({
                    node: $createIframeNode({
                        src: element.getAttribute('src') ?? '',
                        width: element.getAttribute('width') ?? '',
                        height: element.getAttribute('height') ?? '',
                        title: element.getAttribute('title') ?? '',
                        allow: element.getAttribute('allow') ?? '',
                        sandbox: element.getAttribute('sandbox') ?? '',
                        allowFullscreen: element.hasAttribute('allowfullscreen'),
                    }),
                }),
                priority: 0,
            }),
        };
    }

    static importJSON(serializedNode) {
        return $createIframeNode(serializedNode).updateFromJSON(serializedNode);
    }

    constructor(props = {}, key) {
        super(key);
        this.__src = props.src ?? '';
        this.__width = props.width ?? '';
        this.__height = props.height ?? '';
        this.__title = props.title ?? '';
        this.__allow = props.allow ?? '';
        this.__sandbox = props.sandbox ?? '';
        this.__allowFullscreen = props.allowFullscreen ?? false;
    }

    // A whole block of its own, like a paragraph — never part of a line of text.
    isInline() {
        return false;
    }

    /**
     * The node's data as a plain object: the shape `$createIframeNode()` takes, the
     * toolbar dialog fills in, and `exportJSON()` serialises.
     */
    getProps() {
        return this.getLatest().#rawProps();
    }

    getSrc() {
        return this.getLatest().__src;
    }

    createDOM(config) {
        const wrapper = document.createElement('div');
        wrapper.className = config.theme.iframe ?? '';
        wrapper.append(this.#createPreview(this.#rawProps()));

        return wrapper;
    }

    // The dialog replaces the node instead of mutating it, so in practice this never
    // fires; it stays honest anyway — any in-place change rebuilds the preview frame.
    updateDOM(prevNode) {
        const previous = prevNode.#rawProps();

        return Object.entries(this.#rawProps()).some(([name, value]) => previous[name] !== value);
    }

    exportDOM() {
        const element = document.createElement('iframe');
        const props = this.getProps();
        applyEmbedAttributes(element, props);
        setIfPresent(element, 'sandbox', props.sandbox);

        return { element };
    }

    exportJSON() {
        return { ...super.exportJSON(), ...this.getProps() };
    }

    #rawProps() {
        return {
            src: this.__src,
            width: this.__width,
            height: this.__height,
            title: this.__title,
            allow: this.__allow,
            sandbox: this.__sandbox,
            allowFullscreen: this.__allowFullscreen,
        };
    }

    // The real embed, so editing stays WYSIWYG, but inert: the stylesheet turns pointer
    // events off, which is what lets a click select the block instead of disappearing
    // into the framed page, and the frame loads lazily.
    #createPreview(props) {
        const frame = document.createElement('iframe');
        applyEmbedAttributes(frame, props);
        frame.setAttribute('sandbox', '' === props.sandbox ? PREVIEW_SANDBOX : props.sandbox);
        frame.setAttribute('loading', 'lazy');

        return frame;
    }
}

export function $createIframeNode(props) {
    return $applyNodeReplacement(new IframeNode(props));
}

export function $isIframeNode(node) {
    return node instanceof IframeNode;
}
