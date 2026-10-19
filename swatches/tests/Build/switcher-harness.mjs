// Runs resources/js/appearance.js with three [data-gcego-appearance-set] buttons and clicks "dark".
import { readFile } from "node:fs/promises";
import vm from "node:vm";

const classes = new Set();
const listeners = {};
const button = (mode) => {
    const attributes = { "data-gcego-appearance-set": mode, "aria-pressed": "false" };

    return {
        attributes,
        getAttribute: (name) => attributes[name] ?? null,
        setAttribute: (name, value) => (attributes[name] = String(value)),
        hasAttribute: (name) => name in attributes,
        closest: function (selector) { return selector.includes("data-gcego-appearance-set") ? this : null; },
    };
};
const buttons = ["light", "dark", "system"].map(button);

const context = {
    document: {
        readyState: "complete",
        currentScript: { dataset: { default: "system", allowSwitching: "true", storageKey: "gcego.appearance" } },
        documentElement: {
            classList: { toggle: (name, on) => (on ? classes.add(name) : classes.delete(name)) },
            setAttribute: () => {},
            removeAttribute: () => {},
            style: {},
        },
        querySelectorAll: () => buttons,
        addEventListener: (name, callback) => ((listeners[name] ??= []).push(callback)),
    },
    window: {
        localStorage: { getItem: () => null, setItem: () => {} },
        matchMedia: () => ({ matches: false, addEventListener: () => {} }),
        dispatchEvent: () => {},
    },
    CustomEvent: class { constructor(type, init) { this.type = type; this.detail = init.detail; } },
};
context.window.document = context.document;

vm.runInNewContext(await readFile(new URL("../../resources/js/appearance.js", import.meta.url), "utf8"), context);

const pressed = () => Object.fromEntries(buttons.map((b) => [b.attributes["data-gcego-appearance-set"], b.attributes["aria-pressed"]]));
const initial = pressed();

for (const listener of listeners.click ?? []) {
    listener({ target: buttons[1] });
}

process.stdout.write(JSON.stringify({ initial, afterClick: pressed(), mode: context.window.GcegoAppearance.get(), dark: classes.has("dark") }));
