// Runs resources/js/appearance.js against a minimal fake DOM and reports what it did.
// Usage: node tests/Build/appearance-harness.mjs <stored-mode|none> <system-dark: 0|1> <data-default> <allow-switching>
import { readFile } from "node:fs/promises";
import vm from "node:vm";

const [stored, systemDark, fallback, allow] = process.argv.slice(2);
const classes = new Set();
const attributes = {};
const storage = stored === "none" ? {} : { "gcego.appearance": stored };
const events = [];
const listeners = {};

const context = {
    document: {
        currentScript: { dataset: { default: fallback, allowSwitching: allow, storageKey: "gcego.appearance" } },
        documentElement: {
            classList: { toggle: (name, on) => (on ? classes.add(name) : classes.delete(name)) },
            setAttribute: (name, value) => (attributes[name] = value),
            style: {},
        },
        addEventListener: (name, callback) => (listeners[name] = callback),
    },
    window: {
        localStorage: { getItem: (key) => storage[key] ?? null, setItem: (key, value) => (storage[key] = value) },
        matchMedia: () => ({ matches: systemDark === "1", addEventListener: () => {} }),
        dispatchEvent: (event) => events.push(event.type + ":" + event.detail.mode),
    },
    CustomEvent: class { constructor(type, init) { this.type = type; this.detail = init.detail; } },
};
context.window.document = context.document;

vm.runInNewContext(await readFile(new URL("../../resources/js/appearance.js", import.meta.url), "utf8"), context);

const initial = { dark: classes.has("dark"), mode: attributes["data-gcego-appearance"], scheme: context.document.documentElement.style.colorScheme };
const accepted = context.window.GcegoAppearance.set("dark");
const rejected = context.window.GcegoAppearance.set("purple");

process.stdout.write(JSON.stringify({
    initial,
    afterSet: { dark: classes.has("dark"), mode: context.window.GcegoAppearance.get(), stored: storage["gcego.appearance"] ?? null },
    accepted,
    rejected,
    events,
    listensForNavigation: typeof listeners["livewire:navigated"] === "function",
}));
