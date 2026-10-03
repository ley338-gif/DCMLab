import { mount } from '@vue/test-utils';
import { Terminal } from '@xterm/xterm';
import { beforeAll, describe, expect, it, vi } from 'vitest';
import EngineTerminal from './EngineTerminal.vue';

/**
 * xterm.js rendert per Canvas, das jsdom nicht implementiert (siehe die
 * "Not implemented: HTMLCanvasElement.prototype.getContext"-Konsolenfehler
 * in jedem Testlauf, der diese Komponente auch nur importiert) -- xterm
 * faengt das intern ab und faellt auf seinen DOM-Renderer zurueck, das
 * Tastatur-Handling bleibt funktionsfaehig. Deshalb reicht ein echtes
 * `keydown` auf das von xterm selbst erzeugte `<textarea>`, um `onKey()`
 * wirklich auszuloesen -- kein Stub noetig, anders als in
 * Labs/Show.spec.ts/SandboxPanel.spec.ts, wo nur ihr `onCommand`-Prop
 * interessiert, nie das Tastatur-Handling selbst.
 *
 * xterms `evaluateKeyboardEvent()` (Common/input/Keyboard.ts) schaltet auf
 * dem LEGACY `event.keyCode`, nicht auf `event.key` -- ohne einen
 * passenden `keyCode` im Init-Dict bleibt `result.key` leer und `onKey()`
 * feuert nie (per Quellcode verifiziert, nicht geraten).
 */
beforeAll(() => {
    // jsdom implementiert `matchMedia` nicht -- xterm nutzt es nur fuer die
    // Device-Pixel-Ratio-Erkennung (fuer den DOM-Renderer irrelevant).
    window.matchMedia ??= vi.fn().mockReturnValue({
        matches: false,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        addListener: vi.fn(),
        removeListener: vi.fn(),
    }) as unknown as typeof window.matchMedia;

    // jsdom implementiert ebenfalls keinen ResizeObserver -- die
    // FitAddon-Anbindung (PR #148, Prioritaet 3) braucht ihn nur fuer echte
    // Layout-Aenderungen im Browser, hier reicht ein No-op.
    window.ResizeObserver ??= class {
        observe() {}
        unobserve() {}
        disconnect() {}
    };
});

const ENTER_KEYCODE = 13;

function pressEnter(textarea: HTMLTextAreaElement) {
    textarea.dispatchEvent(
        new KeyboardEvent('keydown', {
            key: 'Enter',
            keyCode: ENTER_KEYCODE,
            bubbles: true,
        } as KeyboardEventInit),
    );
}

function typeText(textarea: HTMLTextAreaElement, text: string) {
    for (const char of text) {
        textarea.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: char,
                keyCode: char.toUpperCase().charCodeAt(0),
                bubbles: true,
            } as KeyboardEventInit),
        );
    }
}

function mountTerminal(
    onCommand: (
        command: string,
    ) => Promise<{ stdout: string; stderr: string; exit_code: number }>,
    extraProps: { showExitCode?: boolean } = {},
) {
    const wrapper = mount(EngineTerminal, {
        props: { onCommand, ...extraProps },
        attachTo: document.body,
    });
    const textarea = wrapper.find('textarea').element as HTMLTextAreaElement;

    return { wrapper, textarea };
}

describe('EngineTerminal busy state', () => {
    it('disables input while a command is running and re-enables it once it resolves', async () => {
        let resolveCommand: (result: {
            stdout: string;
            stderr: string;
            exit_code: number;
        }) => void = () => {};
        const onCommand = vi.fn(
            () =>
                new Promise<{
                    stdout: string;
                    stderr: string;
                    exit_code: number;
                }>((resolve) => {
                    resolveCommand = resolve;
                }),
        );

        const { wrapper, textarea } = mountTerminal(onCommand);

        typeText(textarea, 'echo');
        pressEnter(textarea);
        await wrapper.vm.$nextTick();

        expect(onCommand).toHaveBeenCalledTimes(1);
        expect(onCommand).toHaveBeenCalledWith('echo');

        // Waehrend die erste Ausfuehrung noch laeuft: eine zweite Eingabe
        // darf keine zweite, ueberlappende Ausfuehrung anstossen (Prioritaet
        // 2: "keine zweite parallele Ausfuehrung zulassen").
        typeText(textarea, 'again');
        pressEnter(textarea);
        await wrapper.vm.$nextTick();

        expect(onCommand).toHaveBeenCalledTimes(1);

        resolveCommand({ stdout: 'ok', stderr: '', exit_code: 0 });
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // Nach Abschluss ist die Eingabe wieder aktiv.
        typeText(textarea, 'done');
        pressEnter(textarea);
        await wrapper.vm.$nextTick();

        expect(onCommand).toHaveBeenCalledTimes(2);
        expect(onCommand).toHaveBeenLastCalledWith('done');
    });

    it('re-enables input even when onCommand rejects', async () => {
        const onCommand = vi
            .fn()
            .mockRejectedValueOnce(new Error('boom'))
            .mockResolvedValueOnce({ stdout: 'ok', stderr: '', exit_code: 0 });

        const { textarea } = mountTerminal(onCommand);

        typeText(textarea, 'boom');
        pressEnter(textarea);
        // `submit()`s eigenes `finally` laeuft ueber die rejectete Promise --
        // ein paar Microtask-Umlaeufe abwarten, bis `executing` wieder false
        // ist (kein Fake-Timer noetig, es gibt keinen echten Timer hier).
        await new Promise((resolve) => setTimeout(resolve, 0));

        typeText(textarea, 'again');
        pressEnter(textarea);
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(onCommand).toHaveBeenCalledTimes(2);
    });
});

describe('EngineTerminal exit code', () => {
    /** Alles, was die Komponente seit dem Mount ins Terminal geschrieben hat. */
    function written(write: { mock: { calls: unknown[][] } }): string {
        return write.mock.calls.map((call) => String(call[0])).join('');
    }

    it('shows the exit code after the output when showExitCode is set', async () => {
        const write = vi.spyOn(Terminal.prototype, 'write');
        const onCommand = vi.fn().mockResolvedValue({
            stdout: '',
            stderr: 'E: Association Rejected',
            exit_code: 1,
        });

        const { textarea } = mountTerminal(onCommand, { showExitCode: true });
        typeText(textarea, 'echoscu');
        pressEnter(textarea);
        await new Promise((resolve) => setTimeout(resolve, 0));

        const output = written(write);
        expect(output).toContain('[Exitcode 1]');
        expect(output.indexOf('E: Association Rejected')).toBeLessThan(
            output.indexOf('[Exitcode 1]'),
        );
        write.mockRestore();
    });

    it('also shows exit code 0 -- a silent success is a finding too', async () => {
        const write = vi.spyOn(Terminal.prototype, 'write');
        const onCommand = vi
            .fn()
            .mockResolvedValue({ stdout: '', stderr: '', exit_code: 0 });

        const { textarea } = mountTerminal(onCommand, { showExitCode: true });
        typeText(textarea, 'storescu');
        pressEnter(textarea);
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(written(write)).toContain('[Exitcode 0]');
        write.mockRestore();
    });

    it('does not show the exit code by default', async () => {
        const write = vi.spyOn(Terminal.prototype, 'write');
        const onCommand = vi
            .fn()
            .mockResolvedValue({ stdout: 'ok', stderr: '', exit_code: 1 });

        const { textarea } = mountTerminal(onCommand);
        typeText(textarea, 'ls');
        pressEnter(textarea);
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(written(write)).not.toContain('Exitcode');
        write.mockRestore();
    });
});

describe('EngineTerminal paste', () => {
    /** Ein echtes `paste`-Ereignis auf xterms eigenem `<textarea>` -- xterm
     * liest `clipboardData.getData('text/plain')` und reicht den Text ueber
     * `onData` weiter (jsdom kennt kein ClipboardEvent, daher von Hand). */
    function paste(textarea: HTMLTextAreaElement, text: string) {
        const event = new Event('paste', { bubbles: true, cancelable: true });
        Object.defineProperty(event, 'clipboardData', {
            value: { getData: () => text },
        });
        textarea.dispatchEvent(event);
    }

    it('puts pasted text into the input line without running it', async () => {
        const onCommand = vi
            .fn()
            .mockResolvedValue({ stdout: '', stderr: '', exit_code: 0 });
        const { textarea } = mountTerminal(onCommand);

        paste(textarea, 'storescu -v -aec ORTHANC 127.0.0.1 4242 a.dcm');
        await new Promise((resolve) => setTimeout(resolve, 0));
        expect(onCommand).not.toHaveBeenCalled();

        pressEnter(textarea);
        await new Promise((resolve) => setTimeout(resolve, 0));
        expect(onCommand).toHaveBeenCalledWith(
            'storescu -v -aec ORTHANC 127.0.0.1 4242 a.dcm',
        );
    });

    it('turns line breaks into spaces and drops a trailing one', async () => {
        const onCommand = vi
            .fn()
            .mockResolvedValue({ stdout: '', stderr: '', exit_code: 0 });
        const { textarea } = mountTerminal(onCommand);

        paste(textarea, 'dcmdump +P PatientID\ndaten/a.dcm\n');
        pressEnter(textarea);
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(onCommand).toHaveBeenCalledWith(
            'dcmdump +P PatientID daten/a.dcm',
        );
    });

    it('can be combined with typing', async () => {
        const onCommand = vi
            .fn()
            .mockResolvedValue({ stdout: '', stderr: '', exit_code: 0 });
        const { textarea } = mountTerminal(onCommand);

        typeText(textarea, 'x');
        paste(textarea, 'yz');
        pressEnter(textarea);
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(onCommand).toHaveBeenCalledWith('xyz');
    });
});
