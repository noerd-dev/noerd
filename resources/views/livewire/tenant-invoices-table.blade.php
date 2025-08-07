<?php

use Livewire\Volt\Component;
use Noerd\Noerd\Models\TenantInvoice;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {

    use Noerd;

    public const COMPONENT = 'tenant-invoices-table';

    public function tableAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch('set-app-id', ['id' => null]);

        // Open PDF in new tab instead of modal
        $invoice = TenantInvoice::find($modelId);
        if ($invoice) {
            $this->js("window.open('/tenant-invoice/{$invoice->hash}', '_blank')");
        }
    }

    public function with()
    {
        $client = auth()->user()->selectedTenant();
        $rows = TenantInvoice::where('tenant_id', auth()->user()->selected_tenant_id)
            ->orderBy($this->sortField, $this->sortAsc ? 'asc' : 'desc')
            ->when($this->search, function ($query): void {
                $query->where(function ($query): void {
                    $query->where('number', 'like', '%' . $this->search . '%')
                          ->orWhere('total_gross_amount', 'like', '%' . $this->search . '%')
                          ->orWhere('date', 'like', '%' . $this->search . '%')
                          ->orWhere('due_date', 'like', '%' . $this->search . '%');
                });
            })
            ->paginate(6);

        // Add formatted fields for display
        foreach ($rows as $row) {
            $row->formatted_date = \Carbon\Carbon::parse($row->date)->format('d.m.Y');
            $row->formatted_due_date = \Carbon\Carbon::parse($row->due_date)->format('d.m.Y');
            $row->formatted_amount = number_format($row->total_gross_amount, 2, ',', '.') . ' €';
            $row->status_text = $row->paid == 0 ? 'Offen' : 'Bezahlt';
        }

        $overduePayment = count($client->dueInvoices) > 0;

        return [
            'rows' => $rows,
            'overduePayment' => $overduePayment,
        ];
    }

} ?>

<x-noerd::page :disableModal="$disableModal">

    @if($overduePayment)
        <div class="mb-6 bg-red-100 rounded p-4">
            <div>Überfällige Rechnung</div>
        </div>
    @endif

    <div>
        @include('noerd::components.table.table-build',
        [
            'title' => __('Rechnungen'),
            'description' => '',
            'newLabel' => false,
            'redirectAction' => '',
            'disableSearch' => false,
            'table' => [
                [
                    'width' => 30,
                    'field' => 'number',
                    'label' => __('Rechnungsnummer'),
                ],
                [
                    'width' => 20,
                    'field' => 'formatted_date',
                    'label' => __('Rechnungsdatum'),
                ],
                [
                    'width' => 20,
                    'field' => 'formatted_due_date',
                    'label' => __('Fälligkeitsdatum'),
                ],
                [
                    'width' => 15,
                    'field' => 'formatted_amount',
                    'align' => 'right',
                    'label' => __('Betrag'),
                ],
                [
                    'width' => 15,
                    'field' => 'status_text',
                    'label' => __('Status'),
                ],
            ],
        ])
    </div>

</x-noerd::page>
