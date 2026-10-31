<?php

namespace App\Services;

use App\Models\Invoice;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class InvoicePdfService
{
    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['items', 'organization', 'payments']);

        $tempDir = storage_path('app/mpdf');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'dejavusans',
            'tempDir' => $tempDir,
            'margin_top' => 18,
            'margin_bottom' => 18,
            'margin_left' => 16,
            'margin_right' => 16,
        ]);

        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->SetDirectionality(app()->getLocale() === 'ar' ? 'rtl' : 'ltr');
        $mpdf->SetTitle($invoice->number);
        $mpdf->WriteHTML(view('pdf.invoice', [
            'invoice' => $invoice,
            'organization' => $invoice->organization,
        ])->render());

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
