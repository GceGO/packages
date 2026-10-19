/*
 * GCEGO form controls (Alpine.js), shared by gcego/tables and gcego/forms:
 *   gcegoListbox    - an accessible listbox (single or multiple choice, optional search) in place of a native select;
 *   gcegoDatePicker - a calendar / clock popover for dates, date ranges, date-times and times.
 *
 * Registered once per page by the x-gcego-chroma::controls component (ChromaManager::controlsTag()).
 * No dependencies. Values stay in their wire formats: "YYYY-MM-DD", "YYYY-MM-DDTHH:MM", "HH:MM".
 *
 * Writing: with a Livewire binding, $wire.$set(binding, value, live) (live false defers the update
 * to the next request, like wire:model); without one, hidden inputs named config.name carry the
 * value for a plain form submit. Reading back: $wire.$watch(binding) keeps the control in sync when
 * the server changes the value (reset, validation, another field).
 */
(function () {
    "use strict";

    function pad(number) {
        return String(number).padStart(2, "0");
    }

    function toIso(date) {
        return date.getFullYear() + "-" + pad(date.getMonth() + 1) + "-" + pad(date.getDate());
    }

    function fromIso(value) {
        if (typeof value !== "string" || !/^\d{4}-\d{2}-\d{2}/.test(value)) {
            return null;
        }

        var parts = value.slice(0, 10).split("-").map(Number);
        var date = new Date(parts[0], parts[1] - 1, parts[2]);

        return isNaN(date.getTime()) ? null : date;
    }

    function parseTime(value) {
        var match = typeof value === "string" ? value.match(/(?:T|^)(\d{2}):(\d{2})/) : null;

        return match ? { hour: Number(match[1]), minute: Number(match[2]) } : null;
    }

    function addDays(date, days) {
        var next = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        next.setDate(next.getDate() + days);

        return next;
    }

    function readPath(object, path) {
        return path.reduce(function (current, key) {
            return current !== null && current !== undefined ? current[key] : undefined;
        }, object);
    }

    function watchBinding(component, binding, callback) {
        if (!binding || !component.$wire || typeof component.$wire.$watch !== "function") {
            return;
        }

        var segments = binding.split(".");
        var root = segments.shift();

        component.$wire.$watch(root, function (value) {
            callback(segments.length ? readPath(value, segments) : value);
        });
    }

    /*
     * A control with x-ref="native" (a visually hidden native select or hidden input) writes there and fires
     * the event wire:model listens to, so the field keeps its own binding: deferred, live, debounced, or a plain
     * name="" for non-Livewire forms. Otherwise it writes straight to Livewire.
     */
    function writeNative(element, value) {
        if (element.tagName === "SELECT") {
            var wanted = (Array.isArray(value) ? value : [value]).map(function (item) {
                return item === null || item === undefined ? "" : String(item);
            });

            Array.prototype.forEach.call(element.options, function (option) {
                option.selected = wanted.includes(option.value);
            });
            element.dispatchEvent(new Event("change", { bubbles: true }));

            return;
        }

        element.value = value === null || value === undefined ? "" : String(value);
        element.dispatchEvent(new Event("input", { bubbles: true }));
    }

    function write(component, config, value) {
        if (component.$refs.native) {
            writeNative(component.$refs.native, value);
        } else if (config.binding && component.$wire) {
            component.$wire.$set(config.binding, value, config.live !== false);
        }

        component.$dispatch("gcego-control-change", { id: config.id, value: value });
    }

    function register(Alpine) {
        if (window.__gcegoControls) {
            return;
        }

        window.__gcegoControls = true;

        Alpine.data("gcegoListbox", function (config) {
            return {
                open: false,
                active: -1,
                typed: "",
                typedAt: 0,
                search: "",
                options: config.options || [],
                multiple: config.multiple === true,
                values: [],

                init: function () {
                    this.values = this.normalize(config.value);
                    watchBinding(this, config.binding, (value) => {
                        this.values = this.normalize(value);
                    });
                },

                normalize: function (value) {
                    if (Array.isArray(value)) {
                        return value.map(String);
                    }

                    if (value === null || value === undefined || value === "" || (config.emptyValue !== undefined && String(value) === String(config.emptyValue))) {
                        return config.fallback !== undefined ? [String(config.fallback)] : [];
                    }

                    return [String(value)];
                },

                get visible() {
                    var query = this.search.trim().toLowerCase();

                    return query === "" ? this.options : this.options.filter((option) => option.label.toLowerCase().includes(query));
                },

                get summary() {
                    var labels = this.options.filter((option) => this.values.includes(option.value)).map((option) => option.label);

                    if (labels.length === 0) {
                        return config.placeholder || "";
                    }

                    if (labels.length <= 2) {
                        return labels.join(", ");
                    }

                    return (config.many || ":count selected").replace(":count", labels.length);
                },

                get empty() {
                    return this.values.length === 0;
                },

                isSelected: function (value) {
                    return this.values.includes(value);
                },

                optionId: function (index) {
                    return config.id + "-option-" + index;
                },

                show: function () {
                    if (this.open || config.disabled) {
                        return;
                    }

                    this.open = true;
                    this.search = "";
                    var selected = this.visible.findIndex((option) => this.values.includes(option.value));
                    this.active = selected >= 0 ? selected : this.firstEnabled(0, 1);
                    this.$nextTick(() => {
                        var target = this.$refs.search || this.$refs.listbox;
                        target && target.focus({ preventScroll: true });
                        this.reveal();
                    });
                },

                hide: function (focusTrigger) {
                    if (!this.open) {
                        return;
                    }

                    this.open = false;
                    focusTrigger && this.$refs.trigger && this.$refs.trigger.focus();
                },

                toggle: function () {
                    this.open ? this.hide(false) : this.show();
                },

                choose: function (index) {
                    var option = this.visible[index];

                    if (!option || option.disabled) {
                        return;
                    }

                    if (this.multiple) {
                        this.values = this.values.includes(option.value) ? this.values.filter((value) => value !== option.value) : this.values.concat([option.value]);
                    } else {
                        this.values = [option.value];
                        this.hide(true);
                    }

                    this.commit();
                },

                clear: function () {
                    this.values = [];
                    this.commit();
                },

                commit: function () {
                    var value = this.multiple ? this.values : this.values.length ? this.values[0] : config.emptyValue !== undefined ? config.emptyValue : "";

                    if (config.cast === "number" && !this.multiple) {
                        value = Number(value);
                    }

                    write(this, config, value);
                },

                firstEnabled: function (start, direction) {
                    var list = this.visible;

                    for (var step = 0; step < list.length; step++) {
                        var index = (start + step * direction + list.length * 2) % list.length;

                        if (!list[index].disabled) {
                            return index;
                        }
                    }

                    return -1;
                },

                move: function (offset) {
                    if (this.visible.length === 0) {
                        return;
                    }

                    this.active = this.firstEnabled(this.active + offset, offset);
                    this.reveal();
                },

                reveal: function () {
                    var element = document.getElementById(this.optionId(this.active));
                    element && element.scrollIntoView({ block: "nearest" });
                },

                onTriggerKey: function (event) {
                    if (["ArrowDown", "ArrowUp", "Enter", " "].includes(event.key)) {
                        event.preventDefault();
                        this.show();
                    }
                },

                onListKey: function (event) {
                    var inSearch = event.target === this.$refs.search;

                    switch (event.key) {
                        case "ArrowDown":
                            event.preventDefault();
                            this.move(1);
                            break;
                        case "ArrowUp":
                            event.preventDefault();
                            this.move(-1);
                            break;
                        case "Home":
                            if (inSearch) {
                                return;
                            }

                            event.preventDefault();
                            this.active = this.firstEnabled(0, 1);
                            this.reveal();
                            break;
                        case "End":
                            if (inSearch) {
                                return;
                            }

                            event.preventDefault();
                            this.active = this.firstEnabled(this.visible.length - 1, -1);
                            this.reveal();
                            break;
                        case "Enter":
                            event.preventDefault();
                            this.choose(this.active);
                            break;
                        case " ":
                            if (inSearch) {
                                return;
                            }

                            event.preventDefault();
                            this.choose(this.active);
                            break;
                        case "Escape":
                            event.preventDefault();
                            event.stopPropagation();
                            this.hide(true);
                            break;
                        case "Tab":
                            this.hide(false);
                            break;
                        default:
                            if (!inSearch && event.key.length === 1 && /\S/.test(event.key)) {
                                var now = Date.now();
                                this.typed = (now - this.typedAt > 700 ? "" : this.typed) + event.key.toLowerCase();
                                this.typedAt = now;
                                var match = this.visible.findIndex((option) => !option.disabled && option.label.toLowerCase().startsWith(this.typed));

                                if (match >= 0) {
                                    this.active = match;
                                    this.reveal();
                                }
                            }
                    }
                },
            };
        });

        Alpine.data("gcegoDatePicker", function (config) {
            var locale = config.locale || document.documentElement.lang || "en";
            var mode = config.mode || "date";
            var dayFormat = new Intl.DateTimeFormat(locale, { day: "numeric", month: "short", year: "numeric" });
            var dateTimeFormat = new Intl.DateTimeFormat(locale, { day: "numeric", month: "short", year: "numeric", hour: "numeric", minute: "2-digit" });
            var timeFormat = new Intl.DateTimeFormat(locale, { hour: "numeric", minute: "2-digit" });
            var longFormat = new Intl.DateTimeFormat(locale, { weekday: "long", day: "numeric", month: "long", year: "numeric" });
            var monthFormat = new Intl.DateTimeFormat(locale, { month: "long", year: "numeric" });
            var weekdayFormat = new Intl.DateTimeFormat(locale, { weekday: "short" });
            var shortMonthFormat = new Intl.DateTimeFormat(locale, { month: "short" });
            var step = Math.max(1, Math.min(30, Number(config.minuteStep) || 5));
            var minDate = fromIso(config.min);
            var maxDate = fromIso(config.max);

            return {
                open: false,
                mode: mode,
                range: config.range === true && mode === "date",
                from: null,
                to: null,
                hour: null,
                minute: null,
                hover: null,
                month: null,
                focused: null,
                view: "days",

                init: function () {
                    this.apply(config.value);
                    watchBinding(this, config.binding, (value) => this.apply(value));
                },

                apply: function (value) {
                    if (this.range) {
                        this.from = value && typeof value === "object" ? fromIso(value.from) : null;
                        this.to = value && typeof value === "object" ? fromIso(value.to) : null;
                    } else {
                        this.from = mode === "time" ? null : fromIso(value);
                        var time = mode === "date" ? null : parseTime(value);
                        this.hour = time ? time.hour : null;
                        this.minute = time ? time.minute : null;
                    }

                    var anchor = this.from || this.to || new Date();
                    this.month = new Date(anchor.getFullYear(), anchor.getMonth(), 1);
                    this.focused = new Date(anchor.getFullYear(), anchor.getMonth(), anchor.getDate());
                },

                get value() {
                    if (this.range) {
                        return this.from || this.to ? { from: this.from ? toIso(this.from) : null, to: this.to ? toIso(this.to) : null } : null;
                    }

                    var time = this.hour !== null ? pad(this.hour) + ":" + pad(this.minute || 0) : null;

                    if (mode === "time") {
                        return time;
                    }

                    if (mode === "datetime") {
                        return this.from ? toIso(this.from) + "T" + (time || "00:00") : null;
                    }

                    return this.from ? toIso(this.from) : null;
                },

                get summary() {
                    var placeholder = config.labels.placeholder || "";

                    if (this.range) {
                        if (this.from && this.to) {
                            return dayFormat.formatRange ? dayFormat.formatRange(this.from, this.to) : dayFormat.format(this.from) + " – " + dayFormat.format(this.to);
                        }

                        if (this.from) {
                            return (config.labels.since || "From :date").replace(":date", dayFormat.format(this.from));
                        }

                        if (this.to) {
                            return (config.labels.until || "Until :date").replace(":date", dayFormat.format(this.to));
                        }

                        return placeholder;
                    }

                    if (mode === "time") {
                        return this.hour !== null ? timeFormat.format(new Date(2000, 0, 1, this.hour, this.minute || 0)) : placeholder;
                    }

                    if (mode === "datetime") {
                        if (!this.from) {
                            return placeholder;
                        }

                        var at = new Date(this.from.getFullYear(), this.from.getMonth(), this.from.getDate(), this.hour || 0, this.minute || 0);

                        return dateTimeFormat.format(at);
                    }

                    return this.from ? dayFormat.format(this.from) : placeholder;
                },

                get empty() {
                    return mode === "time" ? this.hour === null : !this.from && !this.to;
                },

                get title() {
                    return this.month ? monthFormat.format(this.month) : "";
                },

                get heading() {
                    if (!this.month) {
                        return "";
                    }

                    if (this.view === "months") {
                        return String(this.month.getFullYear());
                    }

                    if (this.view === "years") {
                        return this.yearStart + " – " + (this.yearStart + 11);
                    }

                    return this.title;
                },

                get yearStart() {
                    return Math.floor(this.month.getFullYear() / 12) * 12;
                },

                get monthNames() {
                    var current = this.month;
                    var picked = this.from;

                    return Array.from({ length: 12 }, function (_, index) {
                        return {
                            index: index,
                            label: shortMonthFormat.format(new Date(2024, index, 1)),
                            selected: picked !== null && picked.getFullYear() === current.getFullYear() && picked.getMonth() === index,
                            current: index === current.getMonth(),
                        };
                    });
                },

                get years() {
                    var start = this.yearStart;
                    var shown = this.month.getFullYear();
                    var thisYear = new Date().getFullYear();
                    var picked = this.from ? this.from.getFullYear() : null;

                    return Array.from({ length: 12 }, function (_, index) {
                        var year = start + index;

                        return { year: year, selected: year === picked, current: year === shown, thisYear: year === thisYear };
                    });
                },

                cycleView: function () {
                    this.view = this.view === "days" ? "months" : this.view === "months" ? "years" : "days";
                },

                step: function (offset) {
                    if (this.view === "days") {
                        this.shiftMonth(offset);

                        return;
                    }

                    var years = this.view === "years" ? offset * 12 : offset;
                    this.month = new Date(this.month.getFullYear() + years, this.month.getMonth(), 1);
                },

                chooseMonth: function (index) {
                    this.month = new Date(this.month.getFullYear(), index, 1);
                    var day = Math.min(this.focused ? this.focused.getDate() : 1, new Date(this.month.getFullYear(), index + 1, 0).getDate());
                    this.focused = new Date(this.month.getFullYear(), index, day);
                    this.view = "days";
                    this.$nextTick(() => this.focusDay());
                },

                chooseYear: function (year) {
                    this.month = new Date(year, this.month.getMonth(), 1);
                    this.view = "months";
                },

                get weekdays() {
                    var monday = new Date(2024, 0, 1);

                    return Array.from({ length: 7 }, function (_, index) {
                        return weekdayFormat.format(addDays(monday, index)).slice(0, 2);
                    });
                },

                get hours() {
                    return Array.from({ length: 24 }, function (_, index) {
                        return index;
                    });
                },

                get minutes() {
                    var list = [];

                    for (var minute = 0; minute < 60; minute += step) {
                        list.push(minute);
                    }

                    if (this.minute !== null && !list.includes(this.minute)) {
                        list.push(this.minute);
                        list.sort(function (a, b) { return a - b; });
                    }

                    return list;
                },

                get days() {
                    var first = new Date(this.month.getFullYear(), this.month.getMonth(), 1);
                    var start = addDays(first, -((first.getDay() + 6) % 7));
                    var today = toIso(new Date());
                    var result = [];

                    for (var index = 0; index < 42; index++) {
                        var date = addDays(start, index);
                        var iso = toIso(date);
                        result.push({
                            iso: iso,
                            day: date.getDate(),
                            outside: date.getMonth() !== this.month.getMonth(),
                            today: iso === today,
                            label: longFormat.format(date),
                            selected: this.isEdge(date),
                            between: this.isBetween(date),
                            focused: this.focused && iso === toIso(this.focused),
                            disabled: (minDate && date < minDate) || (maxDate && date > maxDate),
                        });
                    }

                    return result;
                },

                pad: pad,

                isEdge: function (date) {
                    var iso = toIso(date);

                    return (this.from && iso === toIso(this.from)) || (this.range && this.to && iso === toIso(this.to));
                },

                isBetween: function (date) {
                    if (!this.range || !this.from) {
                        return false;
                    }

                    var end = this.to || (this.hover && this.hover > this.from ? this.hover : null);

                    return end !== null && date > this.from && date < end;
                },

                show: function () {
                    if (this.open || config.disabled) {
                        return;
                    }

                    this.open = true;
                    this.view = "days";
                    this.$nextTick(() => {
                        mode !== "date" && this.revealTime();
                        mode !== "time" && this.focusDay();
                    });
                },

                hide: function (focusTrigger) {
                    this.open = false;
                    this.hover = null;
                    focusTrigger && this.$refs.trigger && this.$refs.trigger.focus();
                },

                shiftMonth: function (offset) {
                    this.month = new Date(this.month.getFullYear(), this.month.getMonth() + offset, 1);
                    var day = Math.min(this.focused ? this.focused.getDate() : 1, new Date(this.month.getFullYear(), this.month.getMonth() + 1, 0).getDate());
                    this.focused = new Date(this.month.getFullYear(), this.month.getMonth(), day);
                },

                focusDay: function () {
                    var button = this.$refs.grid && this.$refs.grid.querySelector('[data-day="' + toIso(this.focused) + '"]');
                    button && button.focus({ preventScroll: true });
                },

                revealTime: function () {
                    ["hours", "minutes"].forEach((ref) => {
                        var list = this.$refs[ref];
                        var selected = list && list.querySelector('[aria-pressed="true"]');
                        selected && selected.scrollIntoView({ block: "center" });
                    });
                    var first = this.$refs.hours && (this.$refs.hours.querySelector('[aria-pressed="true"]') || this.$refs.hours.querySelector("button"));
                    mode === "time" && first && first.focus({ preventScroll: true });
                },

                moveFocus: function (days) {
                    this.focused = addDays(this.focused, days);

                    if (this.focused.getMonth() !== this.month.getMonth() || this.focused.getFullYear() !== this.month.getFullYear()) {
                        this.month = new Date(this.focused.getFullYear(), this.focused.getMonth(), 1);
                    }

                    this.$nextTick(() => this.focusDay());
                },

                onGridKey: function (event) {
                    var handled = true;

                    switch (event.key) {
                        case "ArrowLeft": this.moveFocus(-1); break;
                        case "ArrowRight": this.moveFocus(1); break;
                        case "ArrowUp": this.moveFocus(-7); break;
                        case "ArrowDown": this.moveFocus(7); break;
                        case "PageUp": this.shiftMonth(-1); this.$nextTick(() => this.focusDay()); break;
                        case "PageDown": this.shiftMonth(1); this.$nextTick(() => this.focusDay()); break;
                        case "Home": this.moveFocus(-((this.focused.getDay() + 6) % 7)); break;
                        case "End": this.moveFocus(6 - ((this.focused.getDay() + 6) % 7)); break;
                        default: handled = false;
                    }

                    handled && event.preventDefault();
                },

                pick: function (iso) {
                    var date = fromIso(iso);

                    if ((minDate && date < minDate) || (maxDate && date > maxDate)) {
                        return;
                    }

                    this.focused = date;

                    if (!this.range) {
                        this.from = date;

                        if (mode === "datetime" && this.hour === null) {
                            var now = new Date();
                            this.hour = now.getHours();
                            this.minute = Math.floor(now.getMinutes() / step) * step;
                        }

                        this.commit();
                        mode === "date" && this.hide(true);

                        return;
                    }

                    if (!this.from || this.to || date < this.from) {
                        this.from = date;
                        this.to = null;
                    } else {
                        this.to = date;
                        this.commit();
                    }
                },

                setHour: function (hour) {
                    this.hour = hour;
                    this.minute = this.minute === null ? 0 : this.minute;

                    if (mode === "datetime" && !this.from) {
                        this.from = this.focused || new Date();
                    }

                    this.commit();
                },

                setMinute: function (minute) {
                    this.minute = minute;
                    this.hour = this.hour === null ? 0 : this.hour;

                    if (mode === "datetime" && !this.from) {
                        this.from = this.focused || new Date();
                    }

                    this.commit();
                },

                now: function () {
                    var now = new Date();
                    this.from = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                    this.hour = now.getHours();
                    this.minute = now.getMinutes();
                    this.month = new Date(now.getFullYear(), now.getMonth(), 1);
                    this.focused = this.from;
                    this.commit();
                },

                preset: function (name) {
                    var today = new Date();
                    today = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                    var ranges = {
                        today: [today, today],
                        yesterday: [addDays(today, -1), addDays(today, -1)],
                        tomorrow: [addDays(today, 1), addDays(today, 1)],
                        last7: [addDays(today, -6), today],
                        last30: [addDays(today, -29), today],
                        thisMonth: [new Date(today.getFullYear(), today.getMonth(), 1), new Date(today.getFullYear(), today.getMonth() + 1, 0)],
                        lastMonth: [new Date(today.getFullYear(), today.getMonth() - 1, 1), new Date(today.getFullYear(), today.getMonth(), 0)],
                        thisYear: [new Date(today.getFullYear(), 0, 1), new Date(today.getFullYear(), 11, 31)],
                    };
                    var chosen = ranges[name];

                    if (!chosen) {
                        return;
                    }

                    this.from = chosen[0];
                    this.to = this.range ? chosen[1] : null;
                    this.month = new Date(chosen[0].getFullYear(), chosen[0].getMonth(), 1);
                    this.focused = chosen[0];
                    this.commit();
                    mode === "date" && this.hide(true);
                },

                clear: function () {
                    this.from = null;
                    this.to = null;
                    this.hour = null;
                    this.minute = null;
                    this.commit();
                },

                commit: function () {
                    write(this, config, this.value === null ? (config.emptyValue !== undefined ? config.emptyValue : null) : this.value);
                },
            };
        });
    }

    if (window.Alpine) {
        register(window.Alpine);
    } else {
        document.addEventListener("alpine:init", function () {
            register(window.Alpine);
        });
    }
})();
