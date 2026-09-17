import { Extension } from '@tiptap/core';
import Suggestion from '@tiptap/suggestion';
import { blockDefinitions } from './blockDefinitions';
import { insertBlockAtAnchor } from './insertionAnchor';
import type { Editor, Range } from '@tiptap/core';
import type {
    SuggestionKeyDownProps,
    SuggestionProps,
} from '@tiptap/suggestion';
import type { RichContentBlockDefinition } from './blockDefinitions';

export type GlossaryTermOption = { slug: string; term: string };

export type SlashCommandItem = {
    id: string;
    title: string;
    command: (editor: Editor, range: Range) => void;
};

/**
 * Loescht zuerst den "/query"-Bereich (unveraendert wie zuvor), fuegt den
 * neuen Block dann ueber den geteilten Anker-Helfer relativ zur JETZT
 * aktuellen Selektion ein -- Slash, Toolbox und beide "+"-Varianten teilen
 * sich ab hier dieselbe Einfuege-Logik (siehe insertionAnchor.ts).
 */
function toSlashCommandItem(
    definition: RichContentBlockDefinition,
): SlashCommandItem {
    return {
        id: definition.id,
        title: definition.label,
        command: (editor, range) => {
            editor.chain().focus().deleteRange(range).run();
            insertBlockAtAnchor(editor, definition.createNode(), {
                kind: 'selection',
            });
        },
    };
}

function matchesQuery(
    definition: RichContentBlockDefinition,
    query: string,
): boolean {
    const normalized = query.trim().toLowerCase();

    if (normalized === '') {
        return true;
    }

    return (
        definition.label.toLowerCase().includes(normalized) ||
        definition.keywords.some((keyword) => keyword.includes(normalized))
    );
}

/**
 * Baut die Slash-Menue-Eintraege fuer eine Anfrage -- reine Funktion ohne
 * TipTap/DOM-Abhaengigkeit, damit sie ohne Editor-Mock testbar ist.
 * "/glossary" (mit optionalem Suchbegriff dahinter, z. B. "/glossary
 * dicom") schaltet auf eine Glossar-Suche um, statt als ein einzelner
 * Eintrag in der normalen Befehlsliste zu erscheinen -- die Betreiber-
 * Vorgabe "glossary_term kann gesucht/eingefuegt werden" ist damit ein
 * echter Such-Flow, kein einzelner Klick auf einen Platzhalter.
 */
export function resolveSlashCommandItems(
    query: string,
    glossaryTerms: GlossaryTermOption[],
): SlashCommandItem[] {
    const glossaryMatch = /^glossary\s*(.*)$/i.exec(query.trim());

    if (glossaryMatch) {
        const search = glossaryMatch[1].trim().toLowerCase();

        return glossaryTerms
            .filter(
                (entry) =>
                    search === '' ||
                    entry.term.toLowerCase().includes(search) ||
                    entry.slug.toLowerCase().includes(search),
            )
            .map((entry): SlashCommandItem => ({
                id: `glossary:${entry.slug}`,
                title: entry.term,
                command: (editor, range) =>
                    editor
                        .chain()
                        .focus()
                        .deleteRange(range)
                        .insertContent({
                            type: 'glossaryTerm',
                            attrs: { slug: entry.slug },
                        })
                        .run(),
            }));
    }

    return blockDefinitions
        .filter((definition) => matchesQuery(definition, query))
        .map(toSlashCommandItem);
}

/**
 * Bewusst ein schlichtes, selbstgebautes DOM-Popup statt einer Vue-
 * NodeView/ReactRenderer-Integration -- fuer eine Befehlsliste mit
 * Tastaturnavigation reicht das, und `SuggestionProps.mount()` uebernimmt
 * die Positionierung (inkl. Scroll-/Resize-Nachfuehrung) bereits vollstaendig.
 */
function createSlashMenuRenderer() {
    let popupEl: HTMLDivElement | null = null;
    let unmount: (() => void) | null = null;
    let currentItems: SlashCommandItem[] = [];
    let selectedIndex = 0;
    let selectItem: ((item: SlashCommandItem) => void) | null = null;

    function renderItems(): void {
        if (!popupEl) {
            return;
        }

        popupEl.innerHTML = '';

        if (currentItems.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'slash-command-empty';
            empty.textContent = 'Keine Treffer';
            popupEl.appendChild(empty);

            return;
        }

        currentItems.forEach((item, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className =
                'slash-command-item' +
                (index === selectedIndex ? ' is-selected' : '');
            button.textContent = item.title;
            button.addEventListener('mousedown', (event) => {
                event.preventDefault();
                selectItem?.(item);
            });
            popupEl?.appendChild(button);
        });
    }

    return {
        onStart: (props: SuggestionProps<SlashCommandItem>) => {
            currentItems = props.items;
            selectedIndex = 0;
            selectItem = (item) => props.command(item);

            popupEl = document.createElement('div');
            popupEl.className = 'slash-command-menu';
            renderItems();

            unmount = props.mount(popupEl);
        },
        onUpdate: (props: SuggestionProps<SlashCommandItem>) => {
            currentItems = props.items;
            selectedIndex = 0;
            selectItem = (item) => props.command(item);
            renderItems();
        },
        onKeyDown: ({ event }: SuggestionKeyDownProps): boolean => {
            if (currentItems.length === 0) {
                return false;
            }

            if (event.key === 'ArrowDown') {
                selectedIndex = (selectedIndex + 1) % currentItems.length;
                renderItems();

                return true;
            }

            if (event.key === 'ArrowUp') {
                selectedIndex =
                    (selectedIndex - 1 + currentItems.length) %
                    currentItems.length;
                renderItems();

                return true;
            }

            if (event.key === 'Enter') {
                selectItem?.(currentItems[selectedIndex]);

                return true;
            }

            return false;
        },
        onExit: () => {
            unmount?.();
            unmount = null;
            popupEl = null;
        },
    };
}

export interface SlashCommandOptions {
    glossaryTerms: GlossaryTermOption[];
}

/**
 * Das Slash-Menue selbst (ADR 0114, CMS-7c) -- "/" oeffnet eine
 * filterbare Liste aller Editor-Bausteine statt einer wachsenden Toolbar.
 */
export const SlashCommand = Extension.create<SlashCommandOptions>({
    name: 'slashCommand',

    addOptions() {
        return { glossaryTerms: [] };
    },

    addProseMirrorPlugins() {
        const options = this.options;

        return [
            Suggestion<SlashCommandItem>({
                editor: this.editor,
                char: '/',
                items: ({ query }) =>
                    resolveSlashCommandItems(query, options.glossaryTerms),
                command: ({ editor, range, props }) => {
                    props.command(editor, range);
                },
                render: createSlashMenuRenderer,
            }),
        ];
    },
});
