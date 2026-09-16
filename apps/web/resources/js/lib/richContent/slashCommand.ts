import { Extension } from '@tiptap/core';
import Suggestion from '@tiptap/suggestion';
import type { Editor, Range } from '@tiptap/core';
import type {
    SuggestionKeyDownProps,
    SuggestionProps,
} from '@tiptap/suggestion';

export type GlossaryTermOption = { slug: string; term: string };

export type SlashCommandItem = {
    id: string;
    title: string;
    command: (editor: Editor, range: Range) => void;
};

type BaseCommandDefinition = SlashCommandItem & { keywords: string[] };

/**
 * Die feste Befehlsliste (Betreiber-Wireframe, CMS-7c): fuer die
 * bestehenden 7b-Bausteine die eingebauten Toggle-Kommandos (wirken auf
 * den aktuellen Block), fuer alle DCMLab-eigenen Bloecke (`callout`,
 * `self_check`, `dicom_tag_table`, `code_block`-Varianten)
 * `insertContent` -- ein neuer Block statt eine Umwandlung des aktuellen.
 */
function baseCommandDefinitions(): BaseCommandDefinition[] {
    return [
        {
            id: 'paragraph',
            title: 'Text',
            keywords: ['paragraph', 'text', 'absatz'],
            command: (editor, range) =>
                editor.chain().focus().deleteRange(range).setParagraph().run(),
        },
        {
            id: 'heading2',
            title: 'Überschrift 2',
            keywords: ['heading', 'h2', 'überschrift'],
            command: (editor, range) =>
                editor
                    .chain()
                    .focus()
                    .deleteRange(range)
                    .setHeading({ level: 2 })
                    .run(),
        },
        {
            id: 'heading3',
            title: 'Überschrift 3',
            keywords: ['heading', 'h3', 'überschrift'],
            command: (editor, range) =>
                editor
                    .chain()
                    .focus()
                    .deleteRange(range)
                    .setHeading({ level: 3 })
                    .run(),
        },
        {
            id: 'heading4',
            title: 'Überschrift 4',
            keywords: ['heading', 'h4', 'überschrift'],
            command: (editor, range) =>
                editor
                    .chain()
                    .focus()
                    .deleteRange(range)
                    .setHeading({ level: 4 })
                    .run(),
        },
        {
            id: 'bulletList',
            title: 'Aufzählung',
            keywords: ['bullet', 'liste', 'ul'],
            command: (editor, range) =>
                editor
                    .chain()
                    .focus()
                    .deleteRange(range)
                    .toggleBulletList()
                    .run(),
        },
        {
            id: 'orderedList',
            title: 'Nummerierte Liste',
            keywords: ['ordered', 'liste', 'ol', 'nummeriert'],
            command: (editor, range) =>
                editor
                    .chain()
                    .focus()
                    .deleteRange(range)
                    .toggleOrderedList()
                    .run(),
        },
        {
            id: 'blockquote',
            title: 'Zitat',
            keywords: ['blockquote', 'zitat', 'quote'],
            command: (editor, range) =>
                editor
                    .chain()
                    .focus()
                    .deleteRange(range)
                    .toggleBlockquote()
                    .run(),
        },
        {
            id: 'code',
            title: 'Code',
            keywords: ['code'],
            command: (editor, range) =>
                editor
                    .chain()
                    .focus()
                    .deleteRange(range)
                    .insertContent({
                        type: 'codeBlock',
                        attrs: { variant: 'code' },
                    })
                    .run(),
        },
        {
            id: 'terminal',
            title: 'Terminal einfügen',
            keywords: ['terminal', 'ausgabe', 'output'],
            command: (editor, range) =>
                editor
                    .chain()
                    .focus()
                    .deleteRange(range)
                    .insertContent({
                        type: 'codeBlock',
                        attrs: { variant: 'terminal' },
                    })
                    .run(),
        },
        {
            id: 'console',
            title: 'Befehl einfügen',
            keywords: ['console', 'konsole', 'befehl', 'command'],
            command: (editor, range) =>
                editor
                    .chain()
                    .focus()
                    .deleteRange(range)
                    .insertContent({
                        type: 'codeBlock',
                        attrs: { variant: 'console' },
                    })
                    .run(),
        },
        {
            id: 'dicomDump',
            title: 'DICOM Dump',
            keywords: ['dicom', 'dump', 'dcmdump'],
            command: (editor, range) =>
                editor
                    .chain()
                    .focus()
                    .deleteRange(range)
                    .insertContent({
                        type: 'codeBlock',
                        attrs: { variant: 'dicom_dump' },
                    })
                    .run(),
        },
        {
            id: 'dicomTagTable',
            title: 'DICOM Tag Table',
            keywords: ['dicom', 'tag', 'table', 'tabelle'],
            command: (editor, range) =>
                editor
                    .chain()
                    .focus()
                    .deleteRange(range)
                    .insertContent({
                        type: 'dicomTagTable',
                        content: [
                            {
                                type: 'dicomTagRow',
                                attrs: {
                                    tag: '',
                                    keyword: '',
                                    vr: '',
                                    value: '',
                                },
                            },
                        ],
                    })
                    .run(),
        },
        {
            id: 'calloutInfo',
            title: 'Info-Box',
            keywords: ['info', 'callout', 'hinweis'],
            command: (editor, range) =>
                editor
                    .chain()
                    .focus()
                    .deleteRange(range)
                    .insertContent({
                        type: 'callout',
                        attrs: { kind: 'info' },
                        content: [{ type: 'paragraph' }],
                    })
                    .run(),
        },
        {
            id: 'calloutWarning',
            title: 'Warnung',
            keywords: ['warning', 'callout', 'achtung', 'warnung'],
            command: (editor, range) =>
                editor
                    .chain()
                    .focus()
                    .deleteRange(range)
                    .insertContent({
                        type: 'callout',
                        attrs: { kind: 'warning' },
                        content: [{ type: 'paragraph' }],
                    })
                    .run(),
        },
        {
            id: 'selfCheck',
            title: 'Selbstcheck',
            keywords: ['self', 'check', 'selbstcheck', 'quiz', 'antwort'],
            command: (editor, range) =>
                editor
                    .chain()
                    .focus()
                    .deleteRange(range)
                    .insertContent({
                        type: 'selfCheck',
                        attrs: { summary: 'Antwort anzeigen' },
                        content: [{ type: 'paragraph' }],
                    })
                    .run(),
        },
    ];
}

function matchesQuery(item: BaseCommandDefinition, query: string): boolean {
    const normalized = query.trim().toLowerCase();

    if (normalized === '') {
        return true;
    }

    return (
        item.title.toLowerCase().includes(normalized) ||
        item.keywords.some((keyword) => keyword.includes(normalized))
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

    return baseCommandDefinitions().filter((item) => matchesQuery(item, query));
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
