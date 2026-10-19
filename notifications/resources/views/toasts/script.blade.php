{{-- gcego-notifications::toasts.script — the Alpine component behind toasts, defined once per page (no build step).
     With broadcasting, it subscribes once to the user's private channel through Laravel Echo when the page has it,
     and asks notification centers to refresh after a reconnection. --}}
<script data-gcego-toasts>
(function () {
    "use strict";

    if (window.gcegoToasts) {
        return;
    }

    var labels = @js([
        'success' => __('gcego-notifications::messages.types.success'),
        'error' => __('gcego-notifications::messages.types.error'),
        'warning' => __('gcego-notifications::messages.types.warning'),
        'info' => __('gcego-notifications::messages.types.info'),
        'neutral' => __('gcego-notifications::messages.types.neutral'),
    ]);
    var iconClasses = @js(array_map(static fn (array $classes): string => implode(' ', $classes), $presets::TypeIcon));
    var actionTypes = { success: "success", failed: "error", conflict: "error", denied: "warning", not_found: "warning" };
    var counter = 0;

    function subscribe(channel) {
        window.__gcegoNotificationChannels = window.__gcegoNotificationChannels || {};

        if (!channel || !window.Echo || window.__gcegoNotificationChannels[channel]) {
            return;
        }

        window.__gcegoNotificationChannels[channel] = true;
        window.Echo.private(channel).notification(function (notification) {
            if (notification && notification.type === "gcego.notification") {
                window.dispatchEvent(new CustomEvent("gcego-notification-received", { detail: notification }));
            }
        });

        var connection = window.Echo.connector && window.Echo.connector.pusher && window.Echo.connector.pusher.connection;

        if (connection && connection.bind) {
            var connectedOnce = false;
            connection.bind("connected", function () {
                if (connectedOnce) {
                    window.dispatchEvent(new CustomEvent("gcego-notifications-refresh"));
                }
                connectedOnce = true;
            });
        }
    }

    window.gcegoToasts = function (state) {
        return {
            toasts: [],
            seen: {},
            timers: {},
            paused: false,
            typeIcons: state.typeIcons || {},
            labels: labels,
            init: function () {
                var self = this;
                (state.initial || []).forEach(function (toast) { self.add(toast); });
                subscribe(state.channel);
            },
            add: function (toast) {
                if (!toast || !toast.id || this.seen[toast.id]) {
                    return;
                }
                this.seen[toast.id] = true;
                this.toasts.push({
                    id: String(toast.id),
                    title: String(toast.title || ""),
                    body: toast.body ? String(toast.body) : "",
                    type: labels[toast.type] ? toast.type : "info",
                    icon: String(toast.icon || this.typeIcons[labels[toast.type] ? toast.type : "info"] || ""),
                    actions: Array.isArray(toast.actions) ? toast.actions.filter(function (a) { return a && typeof a.url === "string" && (/^(\/(?!\/)|https:\/\/)/.test(a.url) || a.url.indexOf(window.location.origin + "/") === 0); }) : [],
                    duration: Number(toast.duration) || 0,
                    assertive: toast.assertive === true,
                });
                while (this.toasts.length > state.max) {
                    this.dismiss(this.toasts[0].id);
                }
                this.schedule(this.toasts[this.toasts.length - 1]);
            },
            schedule: function (toast) {
                var self = this;
                if (!toast || toast.duration <= 0) {
                    return;
                }
                toast.remaining = toast.remaining || toast.duration;
                toast.started = Date.now();
                if (!this.paused) {
                    this.timers[toast.id] = setTimeout(function () { self.dismiss(toast.id); }, toast.remaining);
                }
            },
            pause: function () {
                var self = this;
                this.paused = true;
                this.toasts.forEach(function (toast) {
                    if (self.timers[toast.id]) {
                        clearTimeout(self.timers[toast.id]);
                        delete self.timers[toast.id];
                        toast.remaining = Math.max(1000, toast.remaining - (Date.now() - toast.started));
                    }
                });
            },
            resume: function () {
                var self = this;
                this.paused = false;
                this.toasts.forEach(function (toast) { self.schedule(toast); });
            },
            dismiss: function (id) {
                clearTimeout(this.timers[id]);
                delete this.timers[id];
                this.toasts = this.toasts.filter(function (toast) { return toast.id !== id; });
            },
            iconClass: function (type) {
                return iconClasses[type] || iconClasses.info;
            },
            template: function (name) {
                var templates = this.$root.querySelectorAll("template[data-gcego-toast-icon]");
                for (var i = 0; i < templates.length; i++) {
                    if (templates[i].getAttribute("data-gcego-toast-icon") === name) {
                        return templates[i];
                    }
                }
                return null;
            },
            renderIcon: function (el, toast) {
                var template = this.template(toast.icon) || this.template(this.typeIcons[toast.type]) || this.template(this.typeIcons.info);
                var icon = template && template.content.firstElementChild;
                el.replaceChildren(icon ? icon.cloneNode(true) : document.createTextNode(""));
            },
            fromAction: function (result) {
                var type = result && actionTypes[result.status];
                if (!state.actions || !type || !result.message) {
                    return;
                }
                this.add({ id: "action-" + (++counter) + "-" + Date.now(), title: result.message, type: type, icon: this.typeIcons[type], actions: [], duration: type === "error" ? state.errorDuration : state.duration, assertive: false });
            },
            fromBroadcast: function (notification) {
                if (!notification || (notification.tenant && notification.tenant !== state.tenant)) {
                    return;
                }
                if (state.panel && notification.panel && notification.panel !== state.panel) {
                    return;
                }
                this.add({ id: notification.id, title: notification.title, body: notification.body, type: notification.notification_type, icon: notification.icon, actions: notification.actions || [], duration: notification.priority === "critical" ? 0 : (notification.notification_type === "error" ? state.errorDuration : state.duration), assertive: notification.notification_type === "error" && (notification.priority === "high" || notification.priority === "critical") });
            },
        };
    };
})();
</script>
