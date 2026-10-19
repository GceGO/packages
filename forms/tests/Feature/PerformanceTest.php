<?php

declare(strict_types=1);

use Gcego\Components\Property;
use Gcego\Forms\Fields\Repeater;
use Gcego\Forms\Fields\TextInput;
use Gcego\Forms\Form;
use Gcego\Forms\Livewire\FormHost;
use Gcego\Runtime\Runtime;
use Livewire\Livewire;

it('compiles once per definition and processes large forms quickly', function (): void {
    $fields = [];

    for ($i = 0; $i < 100; $i++) {
        $fields[] = new TextInput('field_'.$i, new Property(TextInput::Required, true), new Property(TextInput::MaxLength, 50));
    }

    $form = new Form(...$fields, ...[new Repeater('items', new TextInput('title', new Property(TextInput::Required, true)), new Property(Repeater::MaxItems, 200))]);
    $input = [];

    foreach (range(0, 99) as $i) {
        $input['field_'.$i] = 'value '.$i;
    }

    foreach (range(1, 200) as $i) {
        $input['items']['i'.str_pad(dechex($i), 10, '0', STR_PAD_LEFT)] = ['title' => 'Item '.$i];
    }

    $started = hrtime(true);
    $data = null;
    $queries = queriesDuring(function () use ($form, $input, &$data): void {
        $data = forms()->process($form, $input);
    });
    $processMs = (hrtime(true) - $started) / 1e6;

    $started = hrtime(true);
    $html = app(Runtime::class)->render($form)->toHtml();
    $renderMs = (hrtime(true) - $started) / 1e6;

    fwrite(STDERR, sprintf("\n[forms perf] process 100 fields + 200 repeater items: %.1f ms; static render 100 fields: %.1f ms\n", $processMs, $renderMs));

    expect(forms()->schema($form))->toBe(forms()->schema($form))
        ->and($queries)->toBe([])
        ->and($data['items'])->toHaveCount(200)
        ->and(substr_count($html, '<input'))->toBe(100)
        ->and($processMs)->toBeLessThan(2000.0)
        ->and($renderMs)->toBeLessThan(2000.0);
});

it('keeps Livewire round trips bounded in queries', function (): void {
    $world = world();
    actAs($world['editor']);
    $host = Livewire::test(FormHost::class, ['definition' => 'subjects', 'record' => $world['subject']->id]);

    $queries = queriesDuring(fn () => $host->set('data.name', 'Algebra II'));
    fwrite(STDERR, sprintf("[forms perf] Livewire field update on the full subjects form: %d queries\n", count($queries)));

    expect(count($queries))->toBeLessThan(15);
});
