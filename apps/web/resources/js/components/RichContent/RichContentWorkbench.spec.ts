import { mount } from '@vue/test-utils';
import { defineComponent, nextTick } from 'vue';
import { describe, expect, it } from 'vitest';
import type { RichContentDocument } from '@/types/richContent';
import RichContentWorkbench from './RichContentWorkbench.vue';

/**
 * Siehe RichContentEditor.spec.ts::flushEditor() -- dieselbe
 * Notwendigkeit, plus ein zusaetzlicher Umlauf fuer die Chrome-NodeViews.
 */
async function flushEditor(): Promise<void> {
    await nextTick();
    await new Promise((resolve) => setTimeout(resolve, 0));
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

type WorkbenchInstance = {
    currentEditor: () => import('@tiptap/core').Editor | undefined;
    selectedBlock: {
        pos: number;
        node: { type: { name: string } };
        definition?: { label: string };
    } | null;
};

describe('RichContentWorkbench', () => {
    it('renders the toolbox, the editor and the inspector column', async () => {
        const wrapper = mount(RichContentWorkbench, {
            props: { modelValue: paragraphDoc('Hallo') },
        });
        await flushEditor();

        expect(wrapper.find('.rich-content-toolbox').exists()).toBe(true);
        expect(wrapper.find('.ProseMirror').exists()).toBe(true);
        expect(wrapper.find('.rich-content-workbench-inspector').exists()).toBe(
            true,
        );
    });

    it('emits an updated document through update:modelValue when a toolbox item is used', async () => {
        const wrapper = mount(RichContentWorkbench, {
            props: { modelValue: paragraphDoc('') },
        });
        await flushEditor();

        const button = wrapper
            .findAll('.rich-content-toolbox-item')
            .find((node) => node.text().includes('Trennlinie'));
        await button!.trigger('click');
        await flushEditor();

        const emitted = wrapper.emitted('update:modelValue');
        expect(emitted).toBeTruthy();
        const last = emitted![emitted!.length - 1][0] as RichContentDocument;
        expect(
            last.content.some((node) => node.type === 'horizontal_rule'),
        ).toBe(true);
    });

    it('recomputes selectedBlock when the selection moves into a different block', async () => {
        const wrapper = mount(RichContentWorkbench, {
            props: {
                modelValue: {
                    type: 'doc',
                    version: 1,
                    content: [
                        {
                            type: 'paragraph',
                            content: [{ type: 'text', text: 'Eins' }],
                        },
                        {
                            type: 'callout',
                            attrs: { kind: 'info' },
                            content: [
                                {
                                    type: 'paragraph',
                                    content: [{ type: 'text', text: 'Zwei' }],
                                },
                            ],
                        },
                    ],
                },
            },
        });
        await flushEditor();

        const vm = wrapper.vm as unknown as WorkbenchInstance;
        const editor = vm.currentEditor()!;

        editor.commands.setTextSelection(2);
        await nextTick();
        expect(vm.selectedBlock?.node.type.name).toBe('paragraph');

        // Position innerhalb des Callout-Absatzes ("Zwei"): paragraph("Eins")
        // belegt 0..6, danach oeffnet der callout bei 6, dessen paragraph bei
        // 7, "Zwei" beginnt bei 8 -- Position 10 liegt mitten im Wort.
        editor.commands.setTextSelection(10);
        await nextTick();
        expect(vm.selectedBlock?.node.type.name).toBe('callout');
        expect(vm.selectedBlock?.definition?.label).toBe('Info-Box');
    });

    /**
     * Plan §16: Node betreibt schon heute mehrere gleichzeitige
     * Workbench-Instanzen (Briefing/Hints/Write-up) -- dieser Test beweist,
     * dass ein Toolbox-Klick und eine Selektionsaenderung in einer Instanz
     * die jeweils andere nie beeinflussen, weil aller Workbench-eigene
     * Zustand komponenteninstanz-gebunden lebt (nie auf Modulebene).
     */
    it('keeps two Workbench instances on one page fully isolated from each other', async () => {
        const Host = defineComponent({
            components: { RichContentWorkbench },
            props: {
                docA: { type: Object, required: true },
                docB: { type: Object, required: true },
            },
            emits: ['update:docA', 'update:docB'],
            template: `
                <div>
                    <RichContentWorkbench ref="a" :model-value="docA" @update:model-value="$emit('update:docA', $event)" />
                    <RichContentWorkbench ref="b" :model-value="docB" @update:model-value="$emit('update:docB', $event)" />
                </div>
            `,
        });

        const wrapper = mount(Host, {
            props: {
                docA: paragraphDoc('A'),
                docB: paragraphDoc('B'),
            },
        });
        await flushEditor();

        const instanceA = wrapper.vm.$refs.a as unknown as WorkbenchInstance;
        const instanceB = wrapper.vm.$refs.b as unknown as WorkbenchInstance;

        const buttonsA = wrapper
            .findAllComponents(RichContentWorkbench)[0]
            .findAll('.rich-content-toolbox-item');
        const horizontalRuleButtonA = buttonsA.find((node) =>
            node.text().includes('Trennlinie'),
        )!;
        await horizontalRuleButtonA.trigger('click');
        await flushEditor();

        expect(
            instanceA
                .currentEditor()!
                .getJSON()
                .content?.some((node) => node.type === 'horizontalRule'),
        ).toBe(true);
        expect(
            instanceB
                .currentEditor()!
                .getJSON()
                .content?.some((node) => node.type === 'horizontalRule'),
        ).toBe(false);

        instanceA.currentEditor()!.commands.setTextSelection(1);
        await nextTick();
        instanceB.currentEditor()!.commands.setTextSelection(1);
        await nextTick();

        expect(instanceA.selectedBlock?.node.type.name).not.toBeUndefined();
        expect(instanceB.selectedBlock?.node.type.name).toBe('paragraph');
        // B's Dokument wurde nie durch A's Klick veraendert.
        expect(wrapper.emitted('update:docB')).toBeFalsy();
    });
});
