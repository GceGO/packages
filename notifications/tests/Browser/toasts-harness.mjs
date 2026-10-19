// Runs the rendered toast script (argv[2]) in a minimal window and prints the outcomes as JSON.
import { readFileSync } from "node:fs";
import vm from "node:vm";

const listeners = {};
const window = {
    location: { origin: "http://localhost" },
    addEventListener: (name, callback) => { (listeners[name] ||= []).push(callback); },
    dispatchEvent: (event) => { (listeners[event.type] || []).forEach((callback) => callback(event)); return true; },
};
const context = vm.createContext({ window, CustomEvent: class { constructor(type, init) { this.type = type; this.detail = init?.detail; } }, setTimeout, clearTimeout, Date, console });
vm.runInContext(readFileSync(process.argv[2], "utf8"), context);

const state = { initial: [{ id: "a", title: "First", type: "success", icon: "status.success", actions: [], duration: 0, assertive: false }], max: 2, typeIcons: { success: "status.success", error: "status.error", warning: "status.warning", info: "status.info", neutral: "status.info" }, actions: true, duration: 50, errorDuration: 80, channel: "gcego.notifications.abc", tenant: "1", panel: "admin", event: "gcego-toasts" };
const results = {};

// Echo subscription happens once per channel, and broadcasts become a window event.
const subscriptions = [];
window.Echo = { private: (channel) => { subscriptions.push(channel); return { notification: (callback) => { window.__deliver = callback; } }; } };

const toasts = context.window.gcegoToasts(state);
toasts.init();
context.window.gcegoToasts(state).init();
results.subscriptions = subscriptions;

toasts.add({ id: "a", title: "Duplicate" });
toasts.add({ id: "b", title: "Second", type: "nonsense", actions: [{ label: "Bad", url: "javascript:alert(1)" }, { label: "Good", url: "/admin/x" }, { label: "Origin", url: "http://localhost/y" }, { label: "Other", url: "http://evil.test/" }], duration: 0 });
results.afterDuplicate = toasts.toasts.map((toast) => toast.title);
results.sanitizedType = toasts.toasts[1].type;
results.links = toasts.toasts[1].actions.map((action) => action.label);

toasts.add({ id: "c", title: "Third", duration: 0 });
results.maxVisible = toasts.toasts.map((toast) => toast.id);

toasts.dismiss("b");
results.afterDismiss = toasts.toasts.map((toast) => toast.id);

toasts.fromAction({ status: "failed", message: "Could not save" });
toasts.fromAction({ status: "validation_failed", message: "Fix the form" });
results.fromAction = toasts.toasts.map((toast) => [toast.title, toast.type]);

const received = [];
window.addEventListener("gcego-notification-received", (event) => received.push(event.detail.id));
window.__deliver({ type: "gcego.notification", id: "n1", title: "Live", notification_type: "error", priority: "critical", tenant: "1" });
window.__deliver({ type: "other.event", id: "n2" });
results.received = received;

toasts.fromBroadcast({ id: "n1", title: "Live", notification_type: "error", priority: "critical", tenant: "1", panel: "admin" });
toasts.fromBroadcast({ id: "n3", title: "Other tenant", notification_type: "info", tenant: "2" });
toasts.fromBroadcast({ id: "n4", title: "Other panel", notification_type: "info", tenant: null, panel: "staff" });
const live = toasts.toasts.find((toast) => toast.id === "n1");
results.broadcast = toasts.toasts.map((toast) => toast.id);
results.critical = { duration: live.duration, assertive: live.assertive };

// Timers: paused while hovered, dismissed after their duration otherwise.
toasts.add({ id: "t", title: "Timed", duration: 30 });
toasts.pause();
await new Promise((resolve) => setTimeout(resolve, 60));
results.pausedKept = toasts.toasts.some((toast) => toast.id === "t");
toasts.resume();
await new Promise((resolve) => setTimeout(resolve, 1100));
results.expired = ! toasts.toasts.some((toast) => toast.id === "t");

// Icons: cloned from server-rendered templates matched by exact name; unknown names show the type's icon.
const template = (name) => ({ getAttribute: () => name, content: { firstElementChild: { cloneNode: () => ({ icon: name }) } } });
toasts.$root = { querySelectorAll: (selector) => { results.iconSelector = selector; return [template("status.success"), template("status.error"), template("status.info"), template("lucide:bell")]; } };
const slot = () => ({ children: [], replaceChildren(...nodes) { this.children = nodes; } });
const rendered = (toast) => { const el = slot(); toasts.renderIcon(el, toast); return el.children[0]?.icon ?? null; };
results.icons = [
    rendered({ icon: "lucide:bell", type: "info" }),
    rendered({ icon: "lucide:unknown", type: "error" }),
    rendered({ icon: "\"] , [data-x=\"", type: "nonsense" }),
];
toasts.toasts = [];
toasts.fromAction({ status: "failed", message: "Boom" });
results.actionIcon = toasts.toasts[0].icon;
toasts.add({ id: "plain", title: "Plain", type: "warning" });
results.defaultIcon = toasts.toasts[1].icon;

process.stdout.write(JSON.stringify(results));
