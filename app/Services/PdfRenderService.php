<?php

namespace App\Services;

use App\Models\Document;
use App\Models\PdfRender;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        $data = $this->renderData($document);

        $pdf = Pdf::loadView($template, $data)
            ->setPaper($paperSize === '60mm' ? $this->thermalPaperBox($document) : $paperSize)
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
                'credit_note' => 'pdf.generic.credit_note',
                'debit_note' => 'pdf.generic.debit_note',
                'purchase_order' => 'pdf.generic.purchase_order',
                'payment_voucher' => 'pdf.generic.payment_voucher',
                'proforma_invoice' => 'pdf.generic.proforma_invoice',
            ];
            $template = $genericMap[$documentType] ?? 'pdf.generic.invoice';
        }

        if (! view()->exists($template)) {
            throw new \RuntimeException("PDF template not found: {$template}");
        }

        return $template;
    }

    public function renderData(Document $document): array
    {
        $document->load('items', 'company', 'customer', 'attachments');

        $company = $document->company;
        $customer = $document->customer;
        if ($document->isIssued()) {
            $company = $document->issuer_snapshot_json
                ? $this->snapshotObject($document->issuer_snapshot_json)
                : $company;
            $customer = $document->buyer_snapshot_json
                ? $this->snapshotObject($document->buyer_snapshot_json)
                : $customer;
        }

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
        if ($pages->isEmpty()) {
            $pages = collect([collect()]);
        }
        $totalPages = $pages->count();

        return [
            'document' => $document,
            'company' => $company,
            'customer' => $customer,
            'items' => $document->items,
            'itemPages' => $pages,
            'itemsPerPage' => $perPage,
            'totalPages' => $totalPages,
            'attachments' => $this->attachmentPayloads($document),
            'amountWords' => $amountWords,
            'isLastPage' => false,
            'pageNumber' => 1,
        ];
    }

    private function thermalPaperBox(Document $document): array
    {
        $document->loadMissing('items');

        $lineCount = max(1, $document->items->count());
        $descriptionLines = $document->items->sum(function ($item) {
            return max(1, (int) ceil(Str::length((string) $item->description) / 28));
        });

        $height = max(360, 220 + ($lineCount * 34) + ($descriptionLines * 12));

        return [0, 0, 170.08, $height];
    }

    private function attachmentPayloads(Document $document): array
    {
        $prefix = "documents/{$document->company_id}/{$document->id}/attachments/";

        return $document->attachments
            ->where('include_in_pdf', true)
            ->sortBy('sort_order')
            ->values()
            ->map(function ($attachment) use ($prefix) {
                $path = str_replace('\\', '/', (string) $attachment->storage_path);
                $safePath = ! str_contains($path, '..')
                    && ! str_starts_with($path, '/')
                    && str_starts_with($path, $prefix);

                $dataUri = null;
                $isImage = in_array($attachment->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true);
                if ($safePath && $isImage && Storage::disk('local')->exists($path)) {
                    $bytes = Storage::disk('local')->get($path);
                    $dataUri = 'data:'.$attachment->mime_type.';base64,'.base64_encode($bytes);
                }

                return [
                    'id' => $attachment->id,
                    'caption' => $attachment->caption,
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size_bytes' => $attachment->size_bytes,
                    'is_image' => $isImage,
                    'data_uri' => $dataUri,
                ];
            })
            ->all();
    }

    private function snapshotObject(array $snapshot): object
    {
        return json_decode(json_encode($snapshot, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
    }
}
