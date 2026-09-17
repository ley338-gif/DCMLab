import {
    AlignLeft,
    Code2,
    Heading2,
    Info,
    List,
    Minus,
    Quote,
    SquareStack,
    SquareTerminal,
    Table2,
    TerminalSquare,
    TriangleAlert,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import type { Editor, JSONContent } from '@tiptap/core';

type MatchableNode = { type: string; attrs?: Record<string, unknown> };

/**
 * Einzige Quelle der Wahrheit fuer "was ist ein Block" -- getrennt in
 * IDENTITAET (`matches`) und ERZEUGUNG (`createNode`), bewusst OHNE jede
 * Positions-/Einfuege-Logik (siehe insertionAnchor.ts). Toolbox, Slash-Menue
 * und Block-Chrome loesen darueber alle dieselbe Definition auf; keiner
 * dieser drei Orte kennt ProseMirror-Positionen.
 *
 * `matches()` prueft Typ UND Attribute, weil mehrere DCMLab-Bloecke EIN
 * TipTap-Knotentyp mit Varianten-Attribut sind (`code_block.variant`,
 * `callout.kind`) -- ein reiner `node.type`-Vergleich koennte sie nicht
 * unterscheiden.
 */
export type RichContentBlockDefinition = {
    id: string;
    group: 'grundelemente' | 'dcmlab' | 'struktur';
    label: string;
    description: string;
    icon: LucideIcon;
    keywords: string[];
    matches(node: MatchableNode): boolean;
    createNode(): JSONContent;
    isAvailable?(editor: Editor): boolean;
    /** false = nur ueber Slash/Import erreichbar, kein Toolbox-Button
     *  (z. B. Diagram/Mermaid-Codebloecke, siehe Plan §13). */
    showInToolbox?: boolean;
};

function codeBlockDefinition(
    variant: string,
    options: {
        id: string;
        label: string;
        description: string;
        keywords: string[];
        icon: LucideIcon;
        showInToolbox?: boolean;
    },
): RichContentBlockDefinition {
    return {
        id: options.id,
        group: 'dcmlab',
        label: options.label,
        description: options.description,
        icon: options.icon,
        keywords: options.keywords,
        showInToolbox: options.showInToolbox ?? true,
        matches: (node) =>
            node.type === 'codeBlock' &&
            ((node.attrs?.variant as string | undefined) ?? 'terminal') ===
                variant,
        createNode: () => ({ type: 'codeBlock', attrs: { variant } }),
    };
}

function calloutDefinition(
    kind: 'info' | 'warning',
    options: {
        id: string;
        label: string;
        description: string;
        keywords: string[];
        icon: LucideIcon;
    },
): RichContentBlockDefinition {
    return {
        id: options.id,
        group: 'dcmlab',
        label: options.label,
        description: options.description,
        icon: options.icon,
        keywords: options.keywords,
        matches: (node) =>
            node.type === 'callout' &&
            ((node.attrs?.kind as string | undefined) ?? 'info') === kind,
        createNode: () => ({
            type: 'callout',
            attrs: { kind, title: null },
            content: [{ type: 'paragraph' }],
        }),
    };
}

function headingDefinition(
    level: 2 | 3 | 4,
    options: { showInToolbox?: boolean },
): RichContentBlockDefinition {
    return {
        id: `heading-${level}`,
        group: 'grundelemente',
        label: level === 2 ? 'Überschrift' : `Überschrift ${level}`,
        description: `Überschrift der Ebene ${level}.`,
        icon: Heading2,
        keywords: [
            `h${level}`,
            'ueberschrift',
            'überschrift',
            'heading',
            'titel',
        ],
        showInToolbox: options.showInToolbox ?? true,
        matches: (node) =>
            node.type === 'heading' && (node.attrs?.level ?? 2) === level,
        createNode: () => ({ type: 'heading', attrs: { level } }),
    };
}

/**
 * Die 14 Toolbox-Eintraege (Betreiber-Vorgabe: GRUNDELEMENTE / DCMLAB /
 * STRUKTUR) plus zusaetzliche, nur per Slash erreichbare Definitionen, die
 * bereits heute bestehende Faehigkeiten sind (Ueberschrift 3/4, Nummerierte
 * Liste, Diagram-/Mermaid-Codebloecke) -- keine davon wird entfernt, nur
 * nicht zusaetzlich als Toolbox-Button dupliziert, wenn die Vorgabe sie dort
 * nicht auffuehrt. Siehe Plan §9/§13 fuer die Begruendung je Abweichung.
 */
export const blockDefinitions: RichContentBlockDefinition[] = [
    {
        id: 'paragraph',
        group: 'grundelemente',
        label: 'Text',
        description: 'Ein normaler Textabsatz.',
        icon: AlignLeft,
        keywords: ['text', 'absatz', 'paragraph'],
        matches: (node) => node.type === 'paragraph',
        createNode: () => ({ type: 'paragraph' }),
    },
    headingDefinition(2, { showInToolbox: true }),
    headingDefinition(3, { showInToolbox: false }),
    headingDefinition(4, { showInToolbox: false }),
    {
        id: 'bullet-list',
        group: 'grundelemente',
        label: 'Aufzählung',
        description: 'Liste mit Aufzählungszeichen.',
        icon: List,
        keywords: ['liste', 'aufzaehlung', 'bullet', 'list', 'ul'],
        matches: (node) => node.type === 'bulletList',
        createNode: () => ({
            type: 'bulletList',
            content: [{ type: 'listItem', content: [{ type: 'paragraph' }] }],
        }),
    },
    {
        id: 'ordered-list',
        group: 'grundelemente',
        label: 'Nummerierte Liste',
        description: 'Liste mit fortlaufender Nummerierung.',
        icon: List,
        keywords: ['liste', 'nummeriert', 'ordered', 'numbered', 'ol'],
        showInToolbox: false,
        matches: (node) => node.type === 'orderedList',
        createNode: () => ({
            type: 'orderedList',
            content: [{ type: 'listItem', content: [{ type: 'paragraph' }] }],
        }),
    },
    {
        id: 'blockquote',
        group: 'grundelemente',
        label: 'Zitat',
        description: 'Hervorgehobener Zitatblock.',
        icon: Quote,
        keywords: ['zitat', 'quote', 'blockquote'],
        matches: (node) => node.type === 'blockquote',
        createNode: () => ({
            type: 'blockquote',
            content: [{ type: 'paragraph' }],
        }),
    },
    calloutDefinition('info', {
        id: 'callout-info',
        label: 'Info-Box',
        description: 'Hinweis mit neutraler Einfärbung.',
        keywords: ['info', 'hinweis', 'callout', 'box'],
        icon: Info,
    }),
    calloutDefinition('warning', {
        id: 'callout-warning',
        label: 'Warnbox',
        description: 'Hinweis mit Warn-Einfärbung.',
        keywords: ['warnung', 'warning', 'achtung', 'callout'],
        icon: TriangleAlert,
    }),
    codeBlockDefinition('code', {
        id: 'code-code',
        label: 'Code',
        description: 'Quellcode mit optionaler Sprachangabe.',
        keywords: ['code', 'quellcode', 'source'],
        icon: Code2,
    }),
    codeBlockDefinition('terminal', {
        id: 'code-terminal',
        label: 'Terminal',
        description: 'Terminal-/Shell-Ausgabe.',
        keywords: ['terminal', 'shell', 'bash', 'ausgabe', 'output'],
        icon: SquareTerminal,
    }),
    codeBlockDefinition('console', {
        id: 'code-console',
        label: 'Konsole',
        description: 'Konsolen-/Log-Ausgabe.',
        keywords: ['konsole', 'console', 'log', 'befehl', 'command'],
        icon: TerminalSquare,
    }),
    codeBlockDefinition('dicom_dump', {
        id: 'code-dicom-dump',
        label: 'DICOM Dump',
        description: 'Roher DICOM-Dump (z. B. dcmdump-Ausgabe).',
        keywords: ['dicom', 'dump', 'dcmdump'],
        icon: SquareStack,
    }),
    codeBlockDefinition('diagram', {
        id: 'code-diagram',
        label: 'Diagramm',
        description: 'Diagramm-Quelltext.',
        keywords: ['diagram', 'diagramm'],
        icon: Code2,
        showInToolbox: false,
    }),
    codeBlockDefinition('mermaid', {
        id: 'code-mermaid',
        label: 'Mermaid',
        description: 'Mermaid-Diagrammquelltext.',
        keywords: ['mermaid', 'diagram', 'diagramm'],
        icon: Code2,
        showInToolbox: false,
    }),
    {
        id: 'dicom-tag-table',
        group: 'dcmlab',
        label: 'DICOM Tag-Tabelle',
        description: 'Tabelle aus DICOM-Tags (Tag/Keyword/VR/Wert).',
        icon: Table2,
        keywords: ['dicom', 'tag', 'tabelle', 'table'],
        matches: (node) => node.type === 'dicomTagTable',
        createNode: () => ({
            type: 'dicomTagTable',
            content: [
                {
                    type: 'dicomTagRow',
                    attrs: { tag: '', keyword: '', vr: '', value: '' },
                },
            ],
        }),
    },
    {
        id: 'self-check',
        group: 'dcmlab',
        label: 'Selbstcheck',
        description: 'Aufklappbarer Selbstcheck mit Antwort.',
        icon: SquareStack,
        keywords: ['selbstcheck', 'self', 'check', 'antwort', 'quiz'],
        matches: (node) => node.type === 'selfCheck',
        createNode: () => ({
            type: 'selfCheck',
            attrs: { summary: 'Antwort anzeigen' },
            content: [{ type: 'paragraph' }],
        }),
    },
    {
        id: 'table',
        group: 'struktur',
        label: 'Tabelle',
        description: 'Einfache Tabelle mit Kopfzeile.',
        icon: Table2,
        keywords: ['tabelle', 'table'],
        matches: (node) => node.type === 'table',
        createNode: () => ({
            type: 'table',
            content: [
                {
                    type: 'tableRow',
                    content: [
                        {
                            type: 'tableHeader',
                            content: [{ type: 'paragraph' }],
                        },
                        {
                            type: 'tableHeader',
                            content: [{ type: 'paragraph' }],
                        },
                    ],
                },
                {
                    type: 'tableRow',
                    content: [
                        { type: 'tableCell', content: [{ type: 'paragraph' }] },
                        { type: 'tableCell', content: [{ type: 'paragraph' }] },
                    ],
                },
            ],
        }),
    },
    {
        id: 'horizontal-rule',
        group: 'struktur',
        label: 'Trennlinie',
        description: 'Horizontale Trennlinie.',
        icon: Minus,
        keywords: [
            'trennlinie',
            'trenner',
            'horizontal',
            'rule',
            'linie',
            'divider',
            'hr',
        ],
        matches: (node) => node.type === 'horizontalRule',
        createNode: () => ({ type: 'horizontalRule' }),
    },
];

/** Reine Identitaets-Aufloesung -- fuer Chrome-Label, Inspector-Dispatch
 *  (Variantenauswahl) und Slash-Filterung gemeinsam genutzt. */
export function resolveBlockDefinition(
    node: MatchableNode,
): RichContentBlockDefinition | undefined {
    return blockDefinitions.find((definition) => definition.matches(node));
}

/** Nur die Definitionen, die tatsaechlich als Toolbox-Button erscheinen --
 *  siehe je Definition `showInToolbox` fuer die Begruendung von Ausnahmen. */
export function toolboxBlockDefinitions(): RichContentBlockDefinition[] {
    return blockDefinitions.filter(
        (definition) => definition.showInToolbox !== false,
    );
}

export const BLOCK_GROUP_LABELS: Record<
    RichContentBlockDefinition['group'],
    string
> = {
    grundelemente: 'Grundelemente',
    dcmlab: 'DCMLab',
    struktur: 'Struktur',
};
