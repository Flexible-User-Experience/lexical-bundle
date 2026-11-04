import { ParagraphNode, TextNode } from 'lexical';
import { HeadingNode, QuoteNode } from '@lexical/rich-text';
import { ListItemNode, ListNode } from '@lexical/list';
import { LinkNode } from '@lexical/link';

// Inline text formats and the semantic tag each one exports as, innermost first. The
// order only decides the nesting of a run that carries several formats; Lexical's import
// reads any nesting back into the same set of formats.
const TEXT_FORMAT_TAGS = [
    ['bold', 'strong'],
    ['italic', 'em'],
    ['underline', 'u'],
    ['strikethrough', 's'],
    ['subscript', 'sub'],
    ['superscript', 'sup'],
    ['code', 'code'],
    ['highlight', 'mark'],
];

// Case formats have no element of their own; they travel as an inline style, as Lexical
// itself exports them.
const TEXT_TRANSFORM_FORMATS = ['lowercase', 'uppercase', 'capitalize'];

/**
 * The HTML export overrides the controller hands to the editor (`html.export`), so that
 * the field stores plain, semantic HTML.
 *
 * Lexical's own export spells a document the way the editor renders it: a theme class on
 * every block (`<p class="lexical__p">`), every run of text in a `<span>` carrying
 * `style="white-space: pre-wrap"`, and formats doubled up for the sake of old mail clients
 * (`<b><strong>`). That markup is the editor's business, not the application's — the
 * classes mean nothing outside the editor and the frontend that renders the field has its
 * own stylesheet. What is stored instead:
 *
 *  - blocks (`<p>`, `<h1>`-`<h6>`, `<blockquote>`, `<ul>`, `<ol>`, `<li>`, `<a>`) keep
 *    everything Lexical puts on them except the theme class and its `data-lexical-indent`
 *    bookkeeping — alignment and indentation stay as the inline styles Lexical writes
 *    (`text-align`, `padding-inline-start`, which its import reads back), links keep
 *    `href`, `target`, `rel` and `title`;
 *  - a run of text is a bare text node, wrapped in `<strong>`, `<em>`, `<u>`, `<s>`,
 *    `<sub>`, `<sup>`, `<code>` or `<mark>` for each format it carries, and in a
 *    `<span style>` only when it carries an inline style of its own.
 *
 * Everything the field stores is also what it reads back: these are the elements and
 * attributes Lexical's import maps onto the same nodes, so a stored document re-opens as
 * the document it was.
 */
export function createHtmlExport() {
    return new Map([
        [TextNode, exportText],
        [ParagraphNode, exportWithoutThemeClass],
        [HeadingNode, exportWithoutThemeClass],
        [QuoteNode, exportWithoutThemeClass],
        [ListNode, exportWithoutThemeClass],
        [ListItemNode, exportWithoutThemeClass],
        [LinkNode, exportWithoutThemeClass],
    ]);
}

// The node's own export, minus what only the editor cares about: the class its theme put on
// the element and the `data-lexical-indent` attribute Lexical adds beside the
// `padding-inline-start` it writes for an indented block (its import prefers the attribute
// but reads the padding back when it is not there). Lexical hands the element over before
// the children are appended, so this touches nothing but attributes.
function exportWithoutThemeClass(editor, node) {
    const output = node.exportDOM(editor);
    if (output.element instanceof HTMLElement) {
        output.element.removeAttribute('class');
        output.element.removeAttribute('data-lexical-indent');
    }

    return output;
}

function exportText(editor, node) {
    let element = document.createTextNode(node.getTextContent());
    TEXT_FORMAT_TAGS.forEach(([format, tag]) => {
        if (node.hasFormat(format)) {
            element = wrap(element, tag);
        }
    });

    const style = node.getStyle();
    const transform = TEXT_TRANSFORM_FORMATS.find((format) => node.hasFormat(format));
    if ('' !== style || undefined !== transform) {
        const span = wrap(element, 'span');
        if ('' !== style) {
            span.setAttribute('style', style);
        }
        if (undefined !== transform) {
            span.style.textTransform = transform;
        }
        element = span;
    }

    return { element };
}

function wrap(element, tag) {
    const wrapper = document.createElement(tag);
    wrapper.appendChild(element);

    return wrapper;
}
