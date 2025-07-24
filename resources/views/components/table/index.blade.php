<div {{ $attributes->whereDoesntStartWith('class') }}
    {{ $attributes->merge(['class' => '']) }}>
    <table class="noerd-table min-w-full divide-y divide-gray-100" style="table-layout: fixed;">
        @isset($headers)
            <thead class="bg-gray-50 border-b border-gray-300 sticky top-0">
            <tr>
                {{$headers}}
            </tr>
            </thead>
        @endisset
        <tbody x-data="{activeRow: null}" class="divide-y divide-gray-200 bg-white">
        {{$slot}}
        </tbody>
    </table>
</div>
<style>
    .noerd-table {
        td {
            padding-top: 6px;
            padding-bottom: 6px;
            padding-left: 8px;
            padding-right: 8px;
            font-size: 0.875rem;
        }

        tr:hover {
            background: #f9fafb;
        }
    }
</style>
