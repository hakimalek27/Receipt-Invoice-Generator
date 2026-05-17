<?php

namespace App\Services;

use App\Models\Document;
use App\Models\PdfRender;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PdfRenderService
{
    public function __construct(
        private readonly AmountInWordsService $amountInWords,
    ) {}

    /**
     * Render a document to PDF and store immutably.
     */
    public function render(Document $document, ?string $paperSize = null): PdfRender
    {
        $paperSize = $paperSize ?? 'A4';
        $template = $this->resolveTemplate($document->document_type, $paperSize, $document->company_id);

        $data = $this->prepareData($document);

        $pdf = Pdf::loadView($template, $data)
            ->setPaper($paperSize === '60mm' ? [0, 0, 170.08, 0] : $paperSize)
            ->setOptions([
                'defaultFont' => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
            ]);

        $version = ($document->pdfRenders()->max('version') ?? 0) + 1;
        $filename = "documents/{$document->company_id}/{$document->id}_v{$version}.pdf";
        $pdfBytes = $pdf->output();

        // Store in private storage
        Storage::disk('local')->put($filename, $pdfBytes);

        // Mark previous versions as not current
        $document->pdfRenders()->update(['is_current' => false]);

        return PdfRender::create([
            'document_id' => $document->id,
            'version' => $version,
            'file_path' => $filename,
            'file_size' => strlen($pdfBytes),
            'sha256' => hash('sha256', $pdfBytes),
            'page_count' => $pdf->getDomPDF()->getCanvas()->get_page_number(),
            'paper_size' => $paperSize ?? 'A4',
            'template_used' => $template,
            'is_current' => true,
        ]);
    }

    private function resolveTemplate(string $documentType, string $paperSize, int $companyId): string
    {
        if ($paperSize === '60mm') {
            return 'pdf.thermal_receipt';
        }

        $company = \App\Models\Company::find($companyId);
        $code = $company?->code;

        $map = [
            'WS' => [
                'invoice' => 'pdf.wehdah.invoice',
                'quotation' => 'pdf.wehdah.quotation',
            ],
            'NCS' => [
                'invoice' => 'pdf.nasceria.invoice',
                'quotation' => 'pdf.nasceria.quotation',
            ],
            'PGG' => [
                'invoice' => 'pdf.persada.invoice',
            ],
        ];

        $template = $map[$code][$documentType] ?? null;

        // Fall back to generic
        if (! $template || ! view()->exists($template)) {
            $genericMap = [
                'invoice' => 'pdf.generic.invoice',
                'quotation' => 'pdf.generic.quotation',
                'official_receipt' => 'pdf.generic.official_receipt',
                'delivery_order' => 'pdf.generic.delivery_order',
                'cash_bill' => 'pdf.generic.cash_bill',
            ];
            $template = $genericMap[$documentType] ?? 'pdf.generic.invoice';
        }

        if (! view()->exists($template)) {
            throw new \RuntimeException("PDF template not found: {$template}");
        }

        return $template;
    }

    private function prepareData(Document $document): array
    {
        $document->load('items', 'company', 'customer');

        // Use snapshotted value for issued documents; recompute only for drafts
        $amountWords = $document->amount_in_words_text;
        if (! $amountWords && $document->show_amount_in_words) {
            $amountWords = $this->amountInWords->convert(
                (float) $document->grand_total,
                $document->amount_in_words_locale ?? 'ms_MY',
                $document->amount_in_words_currency ?? 'MYR'
            );
        }

        // Paginate items: ~15 items per page for A4 (rough estimate)
        $perPage = $document->document_type === 'delivery_order' ? 20 : 15;
        $pages = $document->items->chunk($perPage);
        $totalPages = $pages->count();

        return [
            'document' => $document,
            'company' => $document->company,
            'customer' => $document->customer,
            'items' => $document->items,
            'itemPages' => $pages,
            'totalPages' => $totalPages,
            'amountWords' => $amountWords,
            'isLastPage' => false,
            'pageNumber' => 1,
        ];
    }
}
