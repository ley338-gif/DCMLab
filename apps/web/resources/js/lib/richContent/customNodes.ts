import { Node } from '@tiptap/core';

/**
 * Vier DCMLab-eigene TipTap-Knoten (ADR 0114, CMS-7c) -- keiner davon ist
 * in irgendeiner Standard-TipTap-Extension enthalten, weil `callout`/
 * `dicom_tag_table`/`self_check` DCMLab-Domainbegriffe sind (siehe
 * `RichContentValidator`), kein generisches ProseMirror-Konzept.
 *
 * Bewusst ohne eigene Vue-NodeViews fuer interaktives Zellen-/Attribut-
 * Bearbeiten (z. B. Tag-Zeilen per Formular editieren, `self_check.summary`
 * inline umbenennen) -- das ist UX-Politur fuer einen spaeteren Schritt,
 * nicht Teil der CMS-7c-Acceptance-Criteria (Rundtrip, Renderer, Adapter,
 * "nichts verschwindet still").
 */

export const Callout = Node.create({
    name: 'callout',
    group: 'block',
    content: 'block+',
    defining: true,

    addAttributes() {
        return {
            kind: { default: 'info' },
            title: { default: null },
        };
    },

    parseHTML() {
        return [{ tag: 'div[data-callout-kind]' }];
    },

    renderHTML({ node, HTMLAttributes }) {
        return [
            'div',
            {
                ...HTMLAttributes,
                'data-callout-kind': node.attrs.kind,
                class: `lesson-callout lesson-callout-${node.attrs.kind}`,
            },
            0,
        ];
    },
});

export const SelfCheck = Node.create({
    name: 'selfCheck',
    group: 'block',
    content: 'block+',
    defining: true,

    addAttributes() {
        return {
            summary: { default: 'Antwort anzeigen' },
        };
    },

    parseHTML() {
        return [{ tag: 'details[data-self-check]' }];
    },

    renderHTML({ node, HTMLAttributes }) {
        return [
            'details',
            { ...HTMLAttributes, 'data-self-check': '' },
            ['summary', {}, node.attrs.summary],
            ['div', { class: 'self-check-body' }, 0],
        ];
    },
});

/**
 * Eine Tag-Zeile ist ein Atom (kein editierbarer Rich-Text-Inhalt, nur
 * vier Attribute) -- passend zum DCMLab-Schema, in dem eine Zeile bewusst
 * kein eigenes `type` und keinen `content` traegt, sondern ein reines
 * Datenobjekt ist (siehe `RichContentValidator::validateDicomTagTable()`).
 */
export const DicomTagRow = Node.create({
    name: 'dicomTagRow',
    atom: true,
    selectable: false,

    addAttributes() {
        return {
            tag: { default: '' },
            keyword: { default: '' },
            vr: { default: '' },
            value: { default: '' },
        };
    },

    parseHTML() {
        return [{ tag: 'tr[data-dicom-tag-row]' }];
    },

    renderHTML({ node }) {
        return [
            'tr',
            { 'data-dicom-tag-row': '' },
            ['td', {}, node.attrs.tag],
            ['td', {}, node.attrs.keyword],
            ['td', {}, node.attrs.vr],
            ['td', {}, node.attrs.value],
        ];
    },
});

export const DicomTagTable = Node.create({
    name: 'dicomTagTable',
    group: 'block',
    content: 'dicomTagRow+',
    isolating: true,

    parseHTML() {
        return [{ tag: 'table[data-dicom-tag-table]' }];
    },

    renderHTML() {
        return [
            'table',
            { 'data-dicom-tag-table': '', class: 'dicom-tag-table' },
            ['tbody', {}, 0],
        ];
    },
});

/**
 * DCMLabs `glossary_term` (ADR 0111) war in CMS-7b bewusst noch nicht im
 * Editor-Schema (kein Einfuege-Weg dafuer) -- CMS-7c macht ihn ueber das
 * Slash-Menue einfuegbar (`slashCommands.ts`), deshalb jetzt als echter
 * Inline-Knoten. Ein Atom wie `dicomTagRow`: der Begriff selbst ist nicht
 * frei editierbar, nur ersetzbar/loeschbar.
 */
export const GlossaryTerm = Node.create({
    name: 'glossaryTerm',
    group: 'inline',
    inline: true,
    atom: true,
    selectable: false,

    addAttributes() {
        return {
            slug: { default: '' },
        };
    },

    parseHTML() {
        return [{ tag: 'span[data-glossary-term]' }];
    },

    renderHTML({ node }) {
        return [
            'span',
            { 'data-glossary-term': node.attrs.slug, class: 'glossary-term' },
            node.attrs.slug,
        ];
    },
});
