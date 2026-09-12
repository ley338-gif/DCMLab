<script setup lang="ts">
import { Terminal } from '@xterm/xterm';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import '@xterm/xterm/css/xterm.css';

/**
 * Terminal fuer Node-Simulation und (spaeter) Spielwiese (Abschnitt 6):
 * dieselbe Komponente, nur das Transport-Backend unterscheidet sich -- hier
 * ein einzelner Request/Response-Zyklus pro Befehl gegen die Engine (HTTP),
 * bei der Spielwiese wird es ein Container-Attach ueber WebSocket sein.
 */
const props = defineProps<{
    onCommand: (
        command: string,
    ) => Promise<{ stdout: string; stderr: string; exit_code: number }>;
    /** Bekannte Platzhalter aus environment.placeholders (Abschnitt 5.1) --
     * damit findet die Markierung genau diese Worte, statt irgendeinen
     * Grossbuchstaben-Lauf zu raten (der auch mitten in "StudyInstanceUID"
     * anschlagen wuerde). */
    placeholders?: string[];
}>();

const container = ref<HTMLDivElement | null>(null);
let term: Terminal | null = null;
let currentLine = '';
let cursor = 0;
let selection: { start: number; end: number } | null = null;
const prompt = '$ ';

function redraw() {
    if (!term) return;

    term.write('\r\x1b[K' + prompt);

    if (selection) {
        term.write(currentLine.slice(0, selection.start));
        term.write(
            '\x1b[7m' +
                currentLine.slice(selection.start, selection.end) +
                '\x1b[0m',
        );
        term.write(currentLine.slice(selection.end));
    } else {
        term.write(currentLine);
    }

    // Xterm's eigener Cursor steht nach all dem Schreiben am Zeilenende --
    // zurueck an die tatsaechliche Einfuegeposition bewegen.
    const trailing = currentLine.length - cursor;
    if (trailing > 0) term.write(`\x1b[${trailing}D`);
}

async function submit() {
    const command = currentLine;
    currentLine = '';
    cursor = 0;
    selection = null;
    term?.write('\r\n');

    if (command.trim() === '') {
        redraw();
        return;
    }

    const result = await props.onCommand(command);
    const lines = [result.stdout, result.stderr]
        .filter((s) => s.length > 0)
        .join('\r\n');
    if (lines.length > 0) {
        term?.write(lines.replaceAll('\n', '\r\n') + '\r\n');
    }
    redraw();
}

/** Findet den fruehesten bekannten Platzhalter in `line` ab `from` (mit
 * Umlauf), falls einer noch uebrig ist. */
function findPlaceholder(
    line: string,
    from = 0,
): { start: number; end: number } | null {
    let best: { start: number; end: number } | null = null;

    for (const placeholder of props.placeholders ?? []) {
        let index = line.indexOf(placeholder, from);
        if (index === -1) index = line.indexOf(placeholder);
        if (index !== -1 && (best === null || index < best.start)) {
            best = { start: index, end: index + placeholder.length };
        }
    }

    return best;
}

/** Tab springt zum naechsten noch offenen Platzhalter -- ohne das haette man
 * bei zwei Platzhaltern in einer Zeile (z. B. find_series) keine Moeglichkeit,
 * den zweiten zu erreichen, ohne die Zeile per Hand neu zu positionieren. */
function jumpToNextPlaceholder() {
    selection = findPlaceholder(currentLine, cursor);
    if (selection) cursor = selection.start;
    redraw();
}

function typeChar(char: string) {
    if (selection) {
        currentLine =
            currentLine.slice(0, selection.start) +
            char +
            currentLine.slice(selection.end);
        cursor = selection.start + char.length;
        selection = null;
    } else {
        currentLine =
            currentLine.slice(0, cursor) + char + currentLine.slice(cursor);
        cursor += char.length;
    }
    redraw();
}

function backspace() {
    if (selection) {
        currentLine =
            currentLine.slice(0, selection.start) +
            currentLine.slice(selection.end);
        cursor = selection.start;
        selection = null;
    } else if (cursor > 0) {
        currentLine =
            currentLine.slice(0, cursor - 1) + currentLine.slice(cursor);
        cursor -= 1;
    }
    redraw();
}

function moveCursor(delta: number) {
    selection = null;
    cursor = Math.max(0, Math.min(currentLine.length, cursor + delta));
    redraw();
}

onMounted(() => {
    term = new Terminal({
        convertEol: true,
        fontSize: 14,
        cursorBlink: true,
        disableStdin: false,
        theme: {
            background: '#0a0e14',
            foreground: '#e5e7eb',
            cursor: '#e5e7eb',
        },
    });
    term.open(container.value!);
    term.write(prompt);

    term.onKey(({ key, domEvent }) => {
        if (domEvent.key === 'Enter') {
            void submit();
        } else if (domEvent.key === 'Backspace') {
            backspace();
        } else if (domEvent.key === 'Tab') {
            domEvent.preventDefault();
            jumpToNextPlaceholder();
        } else if (domEvent.key === 'ArrowLeft') {
            moveCursor(-1);
        } else if (domEvent.key === 'ArrowRight') {
            moveCursor(1);
        } else if (
            !domEvent.ctrlKey &&
            !domEvent.metaKey &&
            !domEvent.altKey &&
            key.length === 1
        ) {
            typeChar(key);
        }
    });
});

onBeforeUnmount(() => {
    term?.dispose();
});

/** Schreibt eine vollstaendige Befehlsvorlage in die Eingabezeile, statt sie
 * auszufuehren (Abschnitt 5.4) -- ein GROSSBUCHSTABEN-Platzhalter darin ist
 * markiert (Reverse Video) und wird von der naechsten Eingabe ueberschrieben. */
function insertTemplate(command: string) {
    currentLine = command;
    selection = findPlaceholder(command);
    cursor = selection ? selection.start : command.length;
    redraw();
    term?.focus();
}

defineExpose({ insertTemplate });
</script>

<template>
    <div ref="container" class="engine-terminal" />
</template>

<style scoped>
.engine-terminal {
    height: 22rem;
    border-radius: 0.5rem;
    overflow: hidden;
    background: #0a0e14;
    padding: 0.5rem;
}
</style>
