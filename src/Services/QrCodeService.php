<?php

namespace Noerd\Noerd\Services;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\TenantInvoice;

class QrCodeService
{
    public function createInvoiceQr(TenantInvoice $invoice): void
    {
        $url = 'https://liefertool.de/paypal/' . $invoice->hash;
        $folder = 'invoices';
        $this->makeInvoiceQr($url, $folder, 'Rechnung ' . $invoice->number, $invoice->hash);
    }

    public function createQr(QrCode $qrCode, Tenant $user): string
    {
        $qrCode->setSize(800);
        $qrCode->setEncoding(new Encoding('UTF-8'));
        $qrCode->setMargin(10);

        $path = '/qrcodes/users';
        Storage::disk('public')->makeDirectory($path);
        $path = $path . '/qrcode' . $user->uuid . '.png';

        $storage_path = Storage::disk('public')->path('/') . $path;
        $writer = new PngWriter();
        // Create generic label
        $label = Label::create($user->name)
            ->setTextColor(new Color(255, 0, 0));
        $writer->write($qrCode, null, $label)->saveToFile($storage_path);

        return $path;
    }

    private function makeInvoiceQr(string $url, string $folder, string $name, string $hash): string
    {
        $path = '/qrcodes/invoices';
        Storage::disk('public')->makeDirectory($path);
        $qrCode = new QrCode($url);
        $path = '/qrcodes/' . $folder . '/' . $hash . '.png';

        $writer = new PngWriter();
        $storage_path = Storage::disk('public')->path('/') . $path;
        $writer->write($qrCode)->saveToFile($storage_path);

        return $path;
    }
}
