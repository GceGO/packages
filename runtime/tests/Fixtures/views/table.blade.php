<table data-path="{{ $context->path() }}">
    <caption>{{ $component->get('label', $component->name()) }}</caption>
    <tbody>
        @foreach ($children as $child)
            <tr><td>{{ $child }}</td></tr>
        @endforeach
    </tbody>
</table>
