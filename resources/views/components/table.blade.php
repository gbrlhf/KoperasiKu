@props([
    'headers' => [],
    'striped' => true,
    'hover' => true,
    'responsive' => true,
])

<div @class(['table-responsive' => $responsive])>
    <table {{ $attributes->class([
        'table align-middle mb-0',
        'table-striped' => $striped,
        'table-hover' => $hover,
    ]) }}>
        @if(count($headers) > 0 || isset($thead))
            <thead class="table-light">
                @if(isset($thead))
                    {{ $thead }}
                @else
                    <tr>
                        @foreach($headers as $header)
                            <th scope="col">{{ $header }}</th>
                        @endforeach
                    </tr>
                @endif
            </thead>
        @endif
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
