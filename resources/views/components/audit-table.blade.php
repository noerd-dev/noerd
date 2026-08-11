@props([
    'audits' => [],
])

@php
    $auditUserEmails = \Noerd\Models\NoerdUser::whereIn(
        'id',
        collect($audits)->pluck('user_id')->filter()->unique(),
    )->pluck('email', 'id');
@endphp

<table class="min-w-full divide-y divide-gray-100" style="table-layout: fixed">
    <thead class="sticky top-0 border-b border-gray-300 bg-gray-50">
        <tr>
            <th scope="col" class="py-3 pl-2 text-left text-xs font-medium tracking-wide text-gray-500 uppercase">
                {{ __('Date') }}
            </th>
            <th scope="col" class="py-3 pr-2 text-left text-xs font-medium tracking-wide text-gray-500 uppercase">
                {{ __('Time') }}
            </th>
            <th scope="col" class="py-3 pr-2 text-left text-xs font-medium tracking-wide text-gray-500 uppercase">
                {{ __('User') }}
            </th>
            <th scope="col" class="py-3 pr-2 text-left text-xs font-medium tracking-wide text-gray-500 uppercase">
                {{ __('Change') }}
            </th>
        </tr>
    </thead>

    <tbody class="divide-y divide-gray-200 bg-white">
        @foreach ($audits as $audit)
            <tr>
                <td class="px-3 py-2 text-sm whitespace-nowrap text-gray-900">
                    {{ Carbon\Carbon::parse($audit['created_at'])->format('d.m.Y') }}
                </td>
                <td class="px-3 py-2 text-sm whitespace-nowrap text-gray-900">
                    {{ Carbon\Carbon::parse($audit['created_at'])->format('H:i') }}
                </td>
                <td class="px-3 py-2 text-sm whitespace-nowrap text-gray-900">
                    {{ $auditUserEmails[$audit['user_id']] ?? '' }}
                </td>
                <td class="px-3 py-2 text-sm whitespace-nowrap text-gray-900">
                    @foreach ($audit['new_values'] as $key => $value)
                        <div>
                            <code class="mr-1 rounded-sm p-0.5 text-xs font-bold">{{ $key }}</code>
                            <code class="mx-1 rounded-sm bg-red-100 p-0.5 px-1 text-xs">{{ $audit['old_values'][$key] ?? '' }}</code>
                            to <code class="mx-1 rounded-sm bg-green-100 p-0.5 px-1 text-xs"> {{ $value }}</code>
                        </div>
                    @endforeach
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
