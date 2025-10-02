<?php

namespace Noerd\Noerd\Http\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Noerd\Noerd\Models\TenantInvoice;
use Noerd\Noerd\Services\QrCodeService;

class TenantInvoiceController extends Controller
{
    public function __construct(
        private QrCodeService $qrCodeService,
    ) {}

    public function show(string $hash)
    {
        $invoice = TenantInvoice::where('hash', $hash)->first();
        $invoice->lines = json_decode($invoice->lines);
        $this->qrCodeService->createInvoiceQr($invoice);

        $path = 'invoices/' . $invoice->tenant_id . '/';
        $invoice = $invoice->toArray();
        $pdf = PDF::loadView('liefertool::pdf.invoice_lt', ['invoice' => $invoice])->setPaper('a4');

        $pathWithName = $path . $invoice['number'] . '.pdf';
        Storage::disk('invoices')->makeDirectory($path);
        Storage::disk('invoices')->put($pathWithName, $pdf->output());

        return response()->file(
            Storage::disk('invoices')->path($pathWithName),
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $invoice['number'] . '.pdf"',
            ],
        );
    }

    public function pdf(TenantInvoice $invoice)
    {
        $invoice->lines = json_decode($invoice->lines);
        $this->qrCodeService->createInvoiceQr($invoice);
        $path = 'invoices/' . $invoice->tenant_id . '/';
        $invoice = $invoice->toArray();
        $pdf = PDF::loadView('liefertool::pdf.invoice', ['invoice' => $invoice])->setPaper('a4');

        $pathWithName = $path . $invoice['number'] . '.pdf';
        Storage::disk('invoices')->makeDirectory($path);
        Storage::disk('invoices')->put($pathWithName, $pdf->output());

        return response()->file(
            Storage::disk('invoices')->path($pathWithName),
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $invoice['number'] . '.pdf"',
            ],
        );
    }
}
