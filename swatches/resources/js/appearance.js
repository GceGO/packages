/*
 * GCEGO appearance controller (light / dark / system, and the main color). No dependencies, ~2 KB.
 *
 * Inline it in <head> with <x-gcego-swatches::appearance /> so the right mode
 * is applied before first paint. The preference lives only in this browser's
 * localStorage; the server never stores it.
 *
 *     window.GcegoAppearance.get()          // "light" | "dark" | "system"
 *     window.GcegoAppearance.set("dark")    // also dispatches a "gcego-appearance" event
 *     window.GcegoAppearance.accent()       // "red", or null for the theme's own color
 *     window.GcegoAppearance.setAccent("red")  // or null; dispatches "gcego-accent"
 *
 * Alpine.js: <button x-on:click="GcegoAppearance.set('dark')">Dark</button>
 * Without JavaScript of your own: <button data-gcego-appearance-set="dark">Dark</button>
 * (and data-gcego-accent-set="red"); aria-pressed follows the current choice.
 */
(function () {
    "use strict";

    var script = document.currentScript;
    var data = (script && script.dataset) || {};
    var modes = ["light", "dark", "system"];
    var key = data.storageKey || "gcego.appearance";
    var fallback = modes.indexOf(data.default) === -1 ? "system" : data.default;
    var allowSwitching = data.allowSwitching !== "false";
    var root = document.documentElement;
    var accents = (data.accents || "").split(" ").filter(Boolean);
    var accentKey = data.accentKey || "gcego.accent";
    var media = window.matchMedia
        ? window.matchMedia("(prefers-color-scheme: dark)")
        : null;

    function stored() {
        if (!allowSwitching) {
            return fallback;
        }

        try {
            var value = window.localStorage.getItem(key);

            return modes.indexOf(value) === -1 ? fallback : value;
        } catch (error) {
            return fallback;
        }
    }

    var mode = stored();

    function storedAccent() {
        try {
            var value = window.localStorage.getItem(accentKey);

            return accents.indexOf(value) === -1 ? null : value;
        } catch (error) {
            return null;
        }
    }

    var accent = storedAccent();

    function sync() {
        if (!document.querySelectorAll) {
            return;
        }

        var buttons = document.querySelectorAll(
            "[data-gcego-appearance-set],[data-gcego-accent-set]",
        );

        for (var i = 0; i < buttons.length; i++) {
            var wanted = buttons[i].getAttribute("data-gcego-appearance-set");
            var pressed =
                wanted !== null
                    ? wanted === mode
                    : (buttons[i].getAttribute("data-gcego-accent-set") ||
                          null) === accent;

            buttons[i].setAttribute("aria-pressed", pressed ? "true" : "false");
        }
    }

    function apply() {
        var dark =
            mode === "dark" || (mode === "system" && !!media && media.matches);

        root.classList.toggle("dark", dark);
        root.setAttribute("data-gcego-appearance", mode);
        root.style.colorScheme = dark ? "dark" : "light";

        if (document.readyState !== "loading") {
            sync();
        }

        if (accents.length === 0) {
            return;
        }

        if (accent === null) {
            root.removeAttribute("data-gcego-accent");
        } else {
            root.setAttribute("data-gcego-accent", accent);
        }
    }

    apply();

    if (media && media.addEventListener) {
        media.addEventListener("change", function () {
            if (mode === "system") {
                apply();
            }
        });
    }

    // Livewire's wire:navigate swaps the page; keep the mode applied.
    document.addEventListener("livewire:navigated", apply);
    document.addEventListener("DOMContentLoaded", sync);
    document.addEventListener("click", function (event) {
        var target = event.target && event.target.closest
            ? event.target.closest("[data-gcego-appearance-set],[data-gcego-accent-set]")
            : null;

        if (!target || !window.GcegoAppearance) {
            return;
        }

        if (target.hasAttribute("data-gcego-appearance-set")) {
            window.GcegoAppearance.set(target.getAttribute("data-gcego-appearance-set"));
        } else {
            window.GcegoAppearance.setAccent(target.getAttribute("data-gcego-accent-set") || null);
        }
    });

    window.GcegoAppearance = {
        get: function () {
            return mode;
        },
        set: function (next) {
            if (!allowSwitching || modes.indexOf(next) === -1) {
                return false;
            }

            mode = next;

            try {
                window.localStorage.setItem(key, next);
            } catch (error) {
                // Storage may be unavailable (private mode); the choice still applies to this page.
            }

            apply();
            window.dispatchEvent(
                new CustomEvent("gcego-appearance", { detail: { mode: next } }),
            );

            return true;
        },
        accent: function () {
            return accent;
        },
        accents: function () {
            return accents.slice();
        },
        setAccent: function (next) {
            if (next !== null && accents.indexOf(next) === -1) {
                return false;
            }

            accent = next;

            try {
                if (next === null) {
                    window.localStorage.removeItem(accentKey);
                } else {
                    window.localStorage.setItem(accentKey, next);
                }
            } catch (error) {
                // Storage may be unavailable (private mode); the choice still applies to this page.
            }

            apply();
            window.dispatchEvent(
                new CustomEvent("gcego-accent", { detail: { accent: next } }),
            );

            return true;
        },
    };
})();
