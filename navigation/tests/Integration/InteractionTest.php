<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['teacher']);
});

/**
 * Evaluates a rendered x-data expression in Node with a small DOM double and runs a scenario.
 *
 * @return array<string, mixed>
 */
function alpine(string $html, string $marker, string $scenario): array
{
    preg_match('/x-data="(\{ '.preg_quote($marker, '/').'[^"]*)"/', $html, $match);
    $harness = <<<'JS'
        const log = [];
        const element = (name) => ({ name, focus() { document.activeElement = this; log.push('focus:' + name); } });
        const links = [element('first'), element('second'), element('third')];
        const store = {};
        globalThis.localStorage = { getItem: (key) => (key in store ? store[key] : null), setItem: (key, value) => { store[key] = value; log.push('store:' + value); } };
        const classes = new Set();
        globalThis.document = { activeElement: null, documentElement: { classList: { add: (name) => classes.add(name), remove: (name) => classes.delete(name) } } };
        const data = eval('(' + process.env.XDATA + ')');
        data.$nextTick = (callback) => callback();
        data.$refs = {
            trigger: element('trigger'),
            panel: { querySelector: (selector) => (selector.includes('aria-current') ? links[1] : links[0]), querySelectorAll: () => links },
        };
        const result = {};
        SCENARIO
        console.log(JSON.stringify({ ...result, log, locked: classes.has('overflow-hidden') }));
        JS;
    $process = Process::env(['XDATA' => html_entity_decode($match[1] ?? '', ENT_QUOTES)])->run(['node', '-e', str_replace('SCENARIO', $scenario, $harness)]);

    return json_decode($process->throw()->output(), true);
}

it('opens dropdowns with the keyboard, moves between links, and closes on Escape with focus restored', function (): void {
    $html = squish(test()->get('/admin?display=topbar')->getContent());
    $result = alpine($html, 'open: false, toggle()', <<<'JS'
        data.show(0); result.afterArrowDown = [data.open, document.activeElement.name];
        data.move(1); data.move(1); data.move(1); result.wrapped = document.activeElement.name;
        data.move(-1); result.back = document.activeElement.name;
        data.focusItem(-1); result.end = document.activeElement.name;
        data.close(true); result.closed = [data.open, document.activeElement.name];
        data.toggle(); result.toggled = data.open; data.close(false); result.outside = [data.open, document.activeElement.name];
        data.close(true); result.closeTwice = document.activeElement.name;
        JS);

    expect($result['afterArrowDown'])->toBe([true, 'first'])
        ->and($result['wrapped'])->toBe('first')
        ->and($result['back'])->toBe('third')
        ->and($result['end'])->toBe('third')
        ->and($result['closed'])->toBe([false, 'trigger'])
        ->and($result['toggled'])->toBeTrue()
        ->and($result['outside'])->toBe([false, 'trigger'])
        ->and($result['closeTwice'])->toBe('trigger');
})->skip(fn (): bool => Process::run(['node', '--version'])->failed(), 'Node.js is not installed.');

it('opens the mobile drawer on its trigger or an event, traps focus, locks scrolling and restores focus', function (): void {
    $html = squish(test()->get('/admin/students?display=mobile')->getContent());
    $result = alpine($html, 'open: false, id:', <<<'JS'
        data.show(); result.opened = [data.open, document.activeElement.name, classes.has('overflow-hidden')];
        let prevented = 0;
        document.activeElement = links[2]; data.trap({ shiftKey: false, preventDefault: () => prevented++ }); result.forward = document.activeElement.name;
        data.trap({ shiftKey: true, preventDefault: () => prevented++ }); result.backward = document.activeElement.name;
        result.prevented = prevented;
        data.close(); result.closed = [data.open, document.activeElement.name, classes.has('overflow-hidden')];
        result.matches = [data.matches({ detail: { id: 'navigation:admin' } }), data.matches({ detail: { id: 'navigation:other' } }), data.matches({})];
        data.toggle(); result.toggled = data.open; data.toggle();
        JS);

    expect($result['opened'])->toBe([true, 'second', true])
        ->and($result['forward'])->toBe('first')
        ->and($result['backward'])->toBe('third')
        ->and($result['prevented'])->toBe(2)
        ->and($result['closed'])->toBe([false, 'trigger', false])
        ->and($result['matches'])->toBe([true, false, true])
        ->and($result['toggled'])->toBeTrue()
        ->and($result['locked'])->toBeFalse();
})->skip(fn (): bool => Process::run(['node', '--version'])->failed(), 'Node.js is not installed.');

it('remembers the compact sidebar preference in the browser only', function (): void {
    $html = squish(test()->get('/admin')->getContent());
    $result = alpine($html, 'compact: false', <<<'JS'
        data.init(); result.initial = data.compact;
        data.toggle(); result.toggled = data.compact;
        const again = eval('(' + process.env.XDATA + ')'); again.init(); result.restored = again.compact;
        JS);

    expect($result['initial'])->toBeFalse()
        ->and($result['toggled'])->toBeTrue()
        ->and($result['restored'])->toBeTrue()
        ->and($result['log'])->toBe(['store:1']);
})->skip(fn (): bool => Process::run(['node', '--version'])->failed(), 'Node.js is not installed.');

it('filters the sidebar by every word typed, opens the icon rail to search, and ignores "/" while typing', function (): void {
    $html = squish(test()->get('/admin/subjects/1')->getContent());
    $result = alpine($html, 'compact: false, tip', <<<'JS'
        data.$refs.search = { ...element('search'), blur() { log.push('blur'); } };
        data.query = 'req'; result.word = [data.searching, data.found('administration requests'), data.found('planning subjects')];
        data.query = 'admin  REQ'; result.words = data.found('administration requests');
        data.query = 'zzz'; result.none = data.foundAny(['administration requests', 'planning subjects']);
        data.clear(); result.cleared = [data.query, data.searching]; data.clear();
        data.compact = true; data.search(); result.opened = data.compact;
        let prevented = 0;
        data.shortcut({ target: { tagName: 'INPUT' }, preventDefault() { prevented++; } });
        data.shortcut({ target: { tagName: 'BODY' }, preventDefault() { prevented++; } });
        result.prevented = prevented;
        JS);

    expect($result['word'])->toBe([true, true, false])
        ->and($result['words'])->toBeTrue()
        ->and($result['none'])->toBeFalse()
        ->and($result['cleared'])->toBe(['', false])
        ->and($result['opened'])->toBeFalse()
        ->and($result['prevented'])->toBe(1)
        ->and($result['log'])->toBe(['blur', 'store:0', 'focus:search', 'focus:search']);
});
