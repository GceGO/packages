// Runs resources/js/appearance.js against a minimal fake DOM and reports what it did with the main color.
// Usage: node tests/Build/accent-harness.mjs <stored-accent|none> <data-accents, space separated>
import { readFile } from "node:fs/promises";
import vm from "node:vm";

const [stored, offered] = process.argv.slice(2);
const attributes = {};
const storage = stored === "none" ? {} : { "gcego.accent": stored };
const events = [];

const context = {
    document: {
        currentScript: { dataset: { default: "light", allowSwitching: "true", storageKey: "gcego.appearance", accents: offered, accentKey: "gcego.accent" } },
        documentElement: {
            classList: { toggle: () => {} },
            setAttribute: (name, value) => (attributes[name] = value),
            removeAttribute: (name) => delete attributes[name],
            style: {},
        },
        addEventListener: () => {},
    },
    window: {
        localStorage: {
            getItem: (key) => storage[key] ?? null,
            setItem: (key, value) => (storage[key] = value),
            removeItem: (key) => delete storage[key],
        },
        matchMedia: () => ({ matches: false, addEventListener: () => {} }),
        dispatchEvent: (event) => events.push(event.type + ":" + String(event.detail.accent)),
    },
    CustomEvent: class { constructor(type, init) { this.type = type; this.detail = init.detail; } },
};
context.window.document = context.document;

vm.runInNewContext(await readFile(new URL("../../resources/js/appearance.js", import.meta.url), "utf8"), context);

const appearance = context.window.GcegoAppearance;
const initial = { accent: appearance.accent(), attribute: attributes["data-gcego-accent"] ?? null };
const red = appearance.setAccent("red");
const afterRed = { accent: appearance.accent(), attribute: attributes["data-gcego-accent"] ?? null, stored: storage["gcego.accent"] ?? null };
const unknown = appearance.setAccent("pink");
const reset = appearance.setAccent(null);
const afterReset = { accent: appearance.accent(), attribute: attributes["data-gcego-accent"] ?? null, stored: storage["gcego.accent"] ?? null };

process.stdout.write(JSON.stringify({ initial, red, afterRed, unknown, reset, afterReset, events }));
