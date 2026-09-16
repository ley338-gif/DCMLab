import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { describe, expect, it } from 'vitest';
import type { RichContentDocument } from '@/types/richContent';
import RichContentEditor from './RichContentEditor.vue';

/**
 * TipTaps `EditorContent` haengt die ProseMirror-View erst nach mehr als
 * einem Vue-`nextTick()` in den DOM ein (ein einzelner `nextTick()` ist in
 * jsdom manchmal genug, manchmal nicht -- flackernd statt zuverlaessig
 * falsch). Ein zusaetzlicher Makrotask-Umlauf macht das deterministisch.
 */
async function flushEditor(): Promise<void> {
    await nextTick();
    await new Promise((resolve) => setTimeout(resolve, 0));
    await nextTick();
}

function paragraphDoc(text: string): RichContentDocument {
    return {
        type: 'doc',
        version: 1,
        content: [
            {
                type: 'paragraph',
                content: text === '' ? [] : [{ type: 'text', text }],
            },
        ],
    };
}

describe('RichContentEditor', () => {
    it('renders without throwing for a supported document', async () => {
        const wrapper = mount(RichContentEditor, {
            props: { modelValue: paragraphDoc('Hallo.') },
        });
        await flushEditor();

        expect(wrapper.find('.ProseMirror').exists()).toBe(true);
    });

    /**
     * Die architektonisch wichtige Regel (Betreiber-Vorgabe): niemand
     * ausserhalb der Komponente bekommt je TipTap-JSON zu sehen. `version:
     * 1` gibt es in TipTaps eigenem JSON nicht -- sein Vorhandensein im
     * emittierten Wert beweist, dass der Adapter gelaufen ist.
     */
    it('emits DCMLab-v1 JSON, never raw TipTap JSON, when the content changes', async () => {
        const wrapper = mount(RichContentEditor, {
            props: { modelValue: paragraphDoc('') },
        });

        const editor = (
            wrapper.vm as unknown as {
                editor: { commands: { insertContent: (c: string) => void } };
            }
        ).editor;
        editor.commands.insertContent('Neuer Text');
        await wrapper.vm.$nextTick();

        const emitted = wrapper.emitted('update:modelValue');
        expect(emitted).toBeTruthy();

        const lastEmitted = emitted![
            emitted!.length - 1
        ][0] as RichContentDocument;
        expect(lastEmitted.type).toBe('doc');
        expect(lastEmitted.version).toBe(1);
        expect(lastEmitted.content[0]).toEqual({
            type: 'paragraph',
            content: [{ type: 'text', text: 'Neuer Text' }],
        });
        // TipTap-Knotennamen (camelCase) duerfen im emittierten Dokument
        // nirgends auftauchen.
        expect(JSON.stringify(lastEmitted)).not.toContain('"bulletList"');
        expect(JSON.stringify(lastEmitted)).not.toContain('"codeBlock"');
    });

    /**
     * `table` bleibt ausserhalb des CMS-7c-Editor-Scopes (generische
     * Tabellen sind kein Ziel, siehe ADR 0114) -- damit weiterhin ein
     * echtes Beispiel fuer einen unbekannten Knoten (anders als
     * `self_check`/`glossary_term`, die CMS-7c editorfaehig gemacht hat).
     */
    it('surfaces an unsupported node instead of silently dropping it', () => {
        const docWithTable: RichContentDocument = {
            type: 'doc',
            version: 1,
            content: [
                {
                    type: 'table',
                    content: [
                        {
                            type: 'table_row',
                            content: [
                                {
                                    type: 'table_cell',
                                    attrs: { header: true },
                                    content: [
                                        {
                                            type: 'paragraph',
                                            content: [
                                                { type: 'text', text: 'Tag' },
                                            ],
                                        },
                                    ],
                                },
                            ],
                        },
                    ],
                },
            ],
        };

        const wrapper = mount(RichContentEditor, {
            props: { modelValue: docWithTable },
        });

        expect(wrapper.emitted('unsupported-node')).toBeTruthy();
        const [error] = wrapper.emitted('unsupported-node')![0] as [
            { nodeType: string },
        ];
        expect(error.nodeType).toBe('table');
        expect(wrapper.text()).toContain('table');
        expect(wrapper.find('.ProseMirror').exists()).toBe(false);
    });

    it('supports undo/redo through the exposed editor', async () => {
        const wrapper = mount(RichContentEditor, {
            props: { modelValue: paragraphDoc('') },
        });

        const editor = (
            wrapper.vm as unknown as {
                editor: {
                    commands: {
                        insertContent: (c: string) => void;
                        undo: () => void;
                        redo: () => void;
                    };
                    getText: () => string;
                };
            }
        ).editor;

        editor.commands.insertContent('Text');
        await wrapper.vm.$nextTick();
        expect(editor.getText()).toBe('Text');

        editor.commands.undo();
        await wrapper.vm.$nextTick();
        expect(editor.getText()).toBe('');

        editor.commands.redo();
        await wrapper.vm.$nextTick();
        expect(editor.getText()).toBe('Text');
    });

    it('never re-derives content from its own update loop (no infinite loop, no cursor reset)', async () => {
        const modelValue = paragraphDoc('Start');
        const wrapper = mount(RichContentEditor, {
            props: { modelValue },
        });

        await wrapper.setProps({ modelValue: { ...modelValue } });
        await wrapper.vm.$nextTick();

        // Kein update:modelValue, weil sich am Dokument inhaltlich nichts
        // geaendert hat -- nur eine neue, aber gleichwertige Objektreferenz.
        expect(wrapper.emitted('update:modelValue')).toBeFalsy();
    });

    /**
     * Beweist, dass `glossaryTerms` tatsaechlich bis zur Slash-Command-
     * Extension durchgereicht wird (ADR 0114) -- nicht nur, dass das Modul
     * `slashCommand.ts` fuer sich isoliert mit einer selbst uebergebenen
     * Liste funktioniert.
     */
    it('passes the glossaryTerms prop through to the slash command extension', () => {
        const glossaryTerms = [{ slug: 'dicom', term: 'DICOM' }];
        const wrapper = mount(RichContentEditor, {
            props: { modelValue: paragraphDoc(''), glossaryTerms },
        });

        const editor = (
            wrapper.vm as unknown as {
                editor: {
                    extensionManager: {
                        extensions: {
                            name: string;
                            options: { glossaryTerms?: unknown };
                        }[];
                    };
                };
            }
        ).editor;

        const slashCommandExtension = editor.extensionManager.extensions.find(
            (extension) => extension.name === 'slashCommand',
        );

        expect(slashCommandExtension?.options.glossaryTerms).toEqual(
            glossaryTerms,
        );
    });

    it('defaults glossaryTerms to an empty list', () => {
        const wrapper = mount(RichContentEditor, {
            props: { modelValue: paragraphDoc('') },
        });

        const editor = (
            wrapper.vm as unknown as {
                editor: {
                    extensionManager: {
                        extensions: {
                            name: string;
                            options: { glossaryTerms?: unknown };
                        }[];
                    };
                };
            }
        ).editor;

        const slashCommandExtension = editor.extensionManager.extensions.find(
            (extension) => extension.name === 'slashCommand',
        );

        expect(slashCommandExtension?.options.glossaryTerms).toEqual([]);
    });
});
