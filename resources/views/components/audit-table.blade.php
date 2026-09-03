@props([
    'audits' => [],
    /** user id => email; resolved here when the caller does not pass it. */
    'userEmails' => null,
])

@php
    $auditUserEmails = $userEmails ?? \Noerd\Models\NoerdUser::whereIn(
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
            <tr wire:key="audit-{{ $audit['id'] ?? $loop->index }}">
                <td class="px-3 py-2 text-sm whitespace-nowrap text-gray-900">
                    {{ \Noerd\Helpers\FormatHelper::date($audit['created_at']) }}
                </td>
                <td class="px-3 py-2 text-sm whitespace-nowrap text-gray-900">
                    {{ \Noerd\Helpers\FormatHelper::time($audit['created_at']) }}
                </td>
                <td class="px-3 py-2 text-sm whitespace-nowrap text-gray-900">
                    {{ $auditUserEmails[$audit['user_id']] ?? '' }}
                </td>
                <td class="px-3 py-2 text-sm whitespace-nowrap text-gray-900">
                    @foreach ($audit['new_values'] ?? [] as $key => $value)
                        <div>
                            <code class="mr-1 rounded-sm p-0.5 text-xs font-bold">{{ $key }}</code>
                            <code class="mx-1 rounded-sm bg-red-100 p-0.5 px-1 text-xs">{{ $audit['old_values'][$key] ?? '' }}</code>
                            {{ __('to') }} <code class="mx-1 rounded-sm bg-green-100 p-0.5 px-1 text-xs"> {{ $value }}</code>
                        </div>
                    @endforeach
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
