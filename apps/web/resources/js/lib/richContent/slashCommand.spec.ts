import { Editor } from '@tiptap/core';
import { describe, expect, it } from 'vitest';
import { fromTipTap } from './RichContentEditorAdapter';
import { resolveSlashCommandItems } from './slashCommand';
import { richContentExtensions } from './tiptapExtensions';
import type { GlossaryTermOption } from './slashCommand';

const GLOSSARY: GlossaryTermOption[] = [
    { slug: 'dicom', term: 'DICOM' },
    { slug: 'scu', term: 'Service Class User' },
    { slug: 'scp', term: 'Service Class Provider' },
];

describe('resolveSlashCommandItems', () => {
    it('returns every base command for an empty query', () => {
        const items = resolveSlashCommandItems('', []);

        expect(items.length).toBeGreaterThan(0);
        expect(items.some((item) => item.id === 'callout-info')).toBe(true);
        expect(items.some((item) => item.id === 'dicom-tag-table')).toBe(true);
        expect(items.some((item) => item.id === 'code-dicom-dump')).toBe(true);
        expect(items.some((item) => item.id === 'self-check')).toBe(true);
    });

    it('filters base commands by title and by keyword', () => {
        expect(
            resolveSlashCommandItems('warn', []).map((item) => item.id),
        ).toEqual(['callout-warning']);
        expect(
            resolveSlashCommandItems('dump', []).map((item) => item.id),
        ).toEqual(['code-dicom-dump']);
    });

    it('finds the horizontal_rule command by its German keyword "trenner"', () => {
        expect(
            resolveSlashCommandItems('trenner', []).map((item) => item.id),
        ).toEqual(['horizontal-rule']);
    });

    it('returns no base commands for a query that matches nothing', () => {
        expect(resolveSlashCommandItems('xyzxyz', [])).toEqual([]);
    });

    /**
     * "glossary_term kann gesucht/eingefuegt werden" (Betreiber-Vorgabe,
     * ADR 0114): "/glossary" schaltet auf eine Glossar-Suche um statt ein
     * einzelner Menuepunkt zu sein.
     */
    it('switches to a glossary search when the query starts with "glossary"', () => {
        const items = resolveSlashCommandItems('glossary', GLOSSARY);

        expect(items.map((item) => item.title).sort()).toEqual(
            ['DICOM', 'Service Class Provider', 'Service Class User'].sort(),
        );
    });

    it('filters glossary results by the text after "glossary"', () => {
        const items = resolveSlashCommandItems('glossary scu', GLOSSARY);

        expect(items).toHaveLength(1);
        expect(items[0].title).toBe('Service Class User');
    });

    it('matches a glossary search against the slug as well as the term', () => {
        const items = resolveSlashCommandItems('glossary dicom', GLOSSARY);

        expect(items.map((item) => item.title)).toEqual(['DICOM']);
    });

    it('returns no glossary results for a search that matches nothing', () => {
        expect(resolveSlashCommandItems('glossary zzz', GLOSSARY)).toEqual([]);
    });
});

/**
 * Beweist, dass die Befehle tatsaechlich das richtige Dokument erzeugen --
 * gegen einen echten Editor (derselbe Extension-Satz wie im Produkt),
 * nicht nur gegen die Objektstruktur der Befehlsliste selbst.
 */
describe('slash command execution against a real editor', () => {
    function editorWithEmptyParagraph(): Editor {
        return new Editor({
            extensions: richContentExtensions(GLOSSARY),
            content: {
                type: 'doc',
                content: [{ type: 'paragraph', content: [] }],
            },
        });
    }

    function runCommand(
        id: string,
        glossaryTerms: GlossaryTermOption[] = GLOSSARY,
    ) {
        const editor = editorWithEmptyParagraph();
        const range = { from: 0, to: editor.state.doc.content.size };
        const item = resolveSlashCommandItems('', glossaryTerms).find(
            (candidate) => candidate.id === id,
        );

        if (!item) {
            throw new Error(`no such command: ${id}`);
        }

        item.command(editor, range);

        return fromTipTap(editor.getJSON());
    }

    it('inserts a callout with an empty paragraph', () => {
        const doc = runCommand('callout-info');

        expect(doc.content[0]).toMatchObject({
            type: 'callout',
            attrs: { kind: 'info' },
        });
    });

    it('inserts a warning callout', () => {
        const doc = runCommand('callout-warning');

        expect(doc.content[0]).toMatchObject({
            type: 'callout',
            attrs: { kind: 'warning' },
        });
    });

    it('inserts a self_check with a default summary', () => {
        const doc = runCommand('self-check');

        expect(doc.content[0]).toMatchObject({
            type: 'self_check',
            attrs: { summary: 'Antwort anzeigen' },
        });
    });

    it('inserts a dicom_tag_table with one empty row', () => {
        const doc = runCommand('dicom-tag-table');

        expect(doc.content[0]).toEqual({
            type: 'dicom_tag_table',
            content: [{ tag: '', keyword: '', vr: '', value: '' }],
        });
    });

    it('inserts a horizontal_rule', () => {
        const doc = runCommand('horizontal-rule');

        expect(
            doc.content.some((node) => node.type === 'horizontal_rule'),
        ).toBe(true);
    });

    it('inserts a table with a header row', () => {
        const doc = runCommand('table');
        const table = doc.content.find((node) => node.type === 'table');

        expect(table).toBeDefined();
        // 2x2 wie konfiguriert (insertTable({ rows: 2, cols: 2,
        // withHeaderRow: true })): eine Kopfzeile, eine Datenzeile.
        expect(table?.content).toHaveLength(2);
        expect(table?.content[0].content[0]).toMatchObject({
            type: 'table_cell',
            attrs: { header: true },
        });
        expect(table?.content[1].content[0]).toMatchObject({
            type: 'table_cell',
            attrs: { header: false },
        });
    });

    it('inserts a dicom_dump code_block', () => {
        const doc = runCommand('code-dicom-dump');

        expect(doc.content[0]).toMatchObject({
            type: 'code_block',
            attrs: { variant: 'dicom_dump' },
        });
    });

    it('inserts a glossary_term chosen from the glossary search', () => {
        const editor = editorWithEmptyParagraph();
        const range = { from: 0, to: editor.state.doc.content.size };
        const item = resolveSlashCommandItems('glossary scu', GLOSSARY)[0];

        item.command(editor, range);

        const doc = fromTipTap(editor.getJSON());
        expect(doc.content[0]).toMatchObject({
            type: 'paragraph',
            content: [{ type: 'glossary_term', attrs: { slug: 'scu' } }],
        });
    });
});
