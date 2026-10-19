{{-- gcego-analytics::partials.table — an accessible report table. $sortable: whether headers sort (the report viewer). --}}
@php($p = \Gcego\Analytics\Presets\AnalyticsPresets::class)
<div class="{{ implode(' ', $p::Scroller) }}">
    <table class="{{ $styles->classes('report_table', $p::ReportTable) }}">
        <caption class="sr-only">{{ $result->title }}</caption>
        <thead class="{{ implode(' ', $p::TableHead) }}">
            <tr>
                @foreach ($result->columns as $column)
                    @php($active = $result->sort === $column['name'])
                    <th scope="col" class="{{ implode(' ', $p::HeaderCell) }} {{ $column['kind'] === 'measure' ? 'text-right' : '' }}" @if ($active) aria-sort="{{ $result->direction === 'asc' ? 'ascending' : 'descending' }}" @endif>
                        @if (($sortable ?? false) && $column['sortable'])
                            <button type="button" wire:click="sortBy('{{ $column['name'] }}')" class="{{ implode(' ', $p::SortButton) }}">
                                {{ $column['label'] }}<span class="sr-only">, {{ __('gcego-analytics::messages.sort_by', ['column' => $column['label']]) }}@if ($active), {{ __('gcego-analytics::messages.sorted.'.$result->direction) }}@endif</span>
                                @if ($active)<span aria-hidden="true">{{ $result->direction === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        @else
                            {{ $column['label'] }}
                        @endif
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody class="{{ implode(' ', $p::TableBody) }}">
            @forelse ($result->rows as $row)
                <tr class="{{ implode(' ', $p::Row) }}">
                    @foreach ($result->columns as $column)
                        @php($cell = $row['cells'][$column['name']] ?? ['formatted' => '', 'key' => null])
                        <td class="{{ implode(' ', $p::Cell) }} {{ $column['kind'] === 'measure' ? 'text-right tabular-nums' : '' }}">
                            @if (isset($drill[$column['name']]) && $cell['key'] !== null)
                                <a href="{{ $drill[$column['name']]['url'] }}?{{ http_build_query(['filters' => [$drill[$column['name']]['filter'] => $cell['key']]]) }}" class="{{ implode(' ', $p::Link) }}">{{ $cell['formatted'] }}<span class="sr-only"> — {{ __('gcego-analytics::messages.drill_down', ['report' => $drill[$column['name']]['title'], 'value' => $cell['formatted']]) }}</span></a>
                            @else
                                {{ $cell['formatted'] }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($result->columns) }}" class="{{ $styles->classes('empty', $p::Empty) }}">{{ __('gcego-analytics::messages.empty') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
