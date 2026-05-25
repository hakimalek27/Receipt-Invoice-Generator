<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Document;
use App\Models\Payment;
use App\Models\PdfRender;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class PdfRenderService
{
    public function __construct(
        private readonly AmountInWordsService $amountInWords,
        private readonly PdfBoilerplateService $boilerplate,
    ) {}

    /**
     * Render a document to PDF and store immutably.
     */
    public function render(Document $document, ?string $paperSize = null): PdfRender
    {
        $paperSize = $paperSize ?? 'A4';
        $template = $this->resolveTemplate($document->document_type, $paperSize, $document->company_id);

        $data = $this->renderData($document);

        $version = ($document->pdfRenders()->max('version') ?? 0) + 1;
        $filename = "documents/{$document->company_id}/{$document->id}_v{$version}.pdf";
        [$pdfBytes, $pageCount] = $this->renderBytes($template, $data, $paperSize, $document);

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
            'page_count' => $pageCount,
            'paper_size' => $paperSize ?? 'A4',
            'template_used' => $template,
            'is_current' => true,
        ]);
    }

    private function renderBytes(string $template, array $data, string $paperSize, Document $document): array
    {
        if ((string) config('pdf.renderer', 'dompdf') === 'playwright') {
            try {
                return $this->renderWithPlaywright(view($template, $data)->render(), $paperSize, $data);
            } catch (\Throwable $exception) {
                if (! config('pdf.legacy_fallback', false)) {
                    throw new \RuntimeException(
                        'Playwright PDF renderer failed and legacy fallback is disabled: '.$exception->getMessage(),
                        previous: $exception
                    );
                }
            }
        }

        return $this->renderWithDompdf($template, $data, $paperSize, $document);
    }

    private function renderWithDompdf(string $template, array $data, string $paperSize, Document $document): array
    {
        $pdf = Pdf::loadView($template, $data)
            ->setPaper($paperSize === '60mm' ? $this->thermalPaperBox($document) : $paperSize)
            ->setOptions([
                'defaultFont' => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
            ]);

        return [
            $pdf->output(),
            $pdf->getDomPDF()->getCanvas()->get_page_number(),
        ];
    }

    private function renderWithPlaywright(string $html, string $paperSize, array $data): array
    {
        $tempDir = storage_path('app/private/pdf-render-temp');
        File::ensureDirectoryExists($tempDir);

        $base = Str::uuid()->toString();
        $input = $tempDir.'/'.$base.'.html';
        $output = $tempDir.'/'.$base.'.pdf';
        file_put_contents($input, $html);

        try {
            $process = new Process([
                (string) config('pdf.node_binary', 'node'),
                (string) config('pdf.playwright_script'),
                $input,
                $output,
                $paperSize,
            ], base_path(), null, null, 60);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new \RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()));
            }
            if (! is_file($output)) {
                throw new \RuntimeException('Playwright did not create an output PDF.');
            }

            $pageCount = max(1, (int) ($data['totalPages'] ?? 1) + count($data['attachments'] ?? []));

            return [
                file_get_contents($output),
                $pageCount,
            ];
        } finally {
            @unlink($input);
            @unlink($output);
        }
    }

    private function resolveTemplate(string $documentType, string $paperSize, int $companyId): string
    {
        if ($paperSize === '60mm') {
            return 'pdf.thermal_receipt';
        }

        $company = Company::find($companyId);
        $code = $company?->code;

        $map = [
            'WS' => [
                'invoice' => 'pdf.wehdah.invoice',
                'quotation' => 'pdf.wehdah.quotation',
                'delivery_order' => 'pdf.wehdah.delivery_order',
                'official_receipt' => 'pdf.wehdah.official_receipt',
                'cash_bill' => 'pdf.wehdah.cash_bill',
                'proforma_invoice' => 'pdf.wehdah.proforma_invoice',
                'credit_note' => 'pdf.wehdah.credit_note',
                'debit_note' => 'pdf.wehdah.debit_note',
                'purchase_order' => 'pdf.wehdah.purchase_order',
                'payment_voucher' => 'pdf.wehdah.payment_voucher',
            ],
            'NCS' => [
                'invoice' => 'pdf.nasceria.invoice',
                'quotation' => 'pdf.nasceria.quotation',
                'delivery_order' => 'pdf.nasceria.delivery_order',
                'official_receipt' => 'pdf.nasceria.official_receipt',
            ],
            'PGG' => [
                'invoice' => 'pdf.persada.invoice',
                'quotation' => 'pdf.persada.quotation',
                'delivery_order' => 'pdf.persada.delivery_order',
                'official_receipt' => 'pdf.persada.official_receipt',
            ],
            'VD' => [
                'invoice' => 'pdf.virtuedamsel.invoice',
                'quotation' => 'pdf.virtuedamsel.quotation',
                'delivery_order' => 'pdf.virtuedamsel.delivery_order',
                'official_receipt' => 'pdf.virtuedamsel.official_receipt',
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
        // Per-document override beats the company-wide default. The document
        // column is nullable — null means "inherit from company".
        $companyFooterDefault = (bool) data_get($company?->settings, 'show_computer_generated_footer', true);
        $showComputerGenFooter = $document->show_computer_generated_footer ?? $companyFooterDefault;
        $brandingPaths = [
            'logo' => $company?->logo_path,
            'signature' => $company?->signature_path,
            'stamp' => $company?->stamp_path,
        ];
        if ($document->isIssued()) {
            $company = $document->issuer_snapshot_json
                ? $this->snapshotObject($document->issuer_snapshot_json)
                : $company;
            $customer = $document->buyer_snapshot_json
                ? $this->snapshotObject($document->buyer_snapshot_json)
                : $customer;
            // Prefer paths captured in the snapshot if present; fall back to live paths.
            $brandingPaths = [
                'logo' => data_get($document->issuer_snapshot_json, 'logo_path') ?? $brandingPaths['logo'],
                'signature' => data_get($document->issuer_snapshot_json, 'signature_path') ?? $brandingPaths['signature'],
                'stamp' => data_get($document->issuer_snapshot_json, 'stamp_path') ?? $brandingPaths['stamp'],
            ];
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

        // Paginate items per A4: tuned per template family.
        // Wehdah templates carry a taller header band + customer/bracket box, so they fit
        // ~12 invoice rows on page 1 and ~15 on continuation pages; we chunk conservatively
        // so the "Continued on next page" footer/totals always land on the right logical page.
        $isWehdah = ($company?->code ?? null) === 'WS';
        if ($document->document_type === 'delivery_order') {
            $perPage = $isWehdah ? 24 : 20;
        } elseif ($document->document_type === 'official_receipt') {
            $perPage = $isWehdah ? 24 : 15;
        } else {
            $perPage = $isWehdah ? 18 : 15;
        }
        // Section header rows take vertical space too; trim per-page when any are present.
        if ($document->items->whereNotNull('section_header')->isNotEmpty()) {
            $perPage = max(10, $perPage - 1);
        }
        $pages = $document->items->chunk($perPage);
        if ($pages->isEmpty()) {
            $pages = collect([collect()]);
        }
        $totalPages = $pages->count();

        $rawBoilerplate = data_get($company, 'pdf_boilerplate');
        $companyBoilerplate = null;
        if (is_array($rawBoilerplate)) {
            $companyBoilerplate = $rawBoilerplate;
        } elseif (is_object($rawBoilerplate)) {
            $companyBoilerplate = json_decode(json_encode($rawBoilerplate), true);
        }

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
            'payment' => $this->paymentPayload($document),
            'isLastPage' => false,
            'pageNumber' => 1,
            'brand' => $this->brandPalette($company),
            'logoDataUri' => $this->brandingDataUri($brandingPaths['logo']),
            'signatureDataUri' => $this->brandingDataUri($brandingPaths['signature']),
            'stampDataUri' => $this->brandingDataUri($brandingPaths['stamp']),
            'showComputerGenFooter' => $showComputerGenFooter,
            'boilerplate' => $this->boilerplate->resolve(
                $companyBoilerplate,
                $document->document_type,
                $company?->name
            ),
        ];
    }

    /**
     * Inline branding assets as base64 data URIs so DomPDF (isRemoteEnabled=false)
     * and Playwright both render them without HTTP fetches.
     */
    private function brandingDataUri(?string $relativePath): ?string
    {
        if (! $relativePath) {
            return null;
        }
        $disk = Storage::disk('public');
        if (! $disk->exists($relativePath)) {
            return null;
        }
        $bytes = $disk->get($relativePath);
        $mime = $disk->mimeType($relativePath) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    private function brandPalette($company): array
    {
        if (! $company) {
            return ['primary' => '#1a3a5c', 'secondary' => '#f0f4f8', 'accent' => '#16427a'];
        }

        $primary = data_get($company, 'brand_primary') ?: '#1a3a5c';
        $secondary = data_get($company, 'brand_secondary') ?: '#f0f4f8';
        $accent = data_get($company, 'brand_accent') ?: '#16427a';

        $palette = [
            'primary' => $primary,
            'secondary' => $secondary,
            'accent' => $accent,
        ];

        if ((data_get($company, 'code') ?? null) === 'PGG') {
            $gradient = resource_path('views/pdf/persada/assets/header-gradient.png');
            if (is_file($gradient)) {
                $palette['header_image_data_uri'] = 'data:image/png;base64,'.base64_encode(file_get_contents($gradient));
            }
        }

        return $palette;
    }

    private function paymentPayload(Document $document): ?array
    {
        if ($document->document_type !== 'official_receipt') {
            return null;
        }

        $payment = Payment::with('allocations.document')
            ->where('receipt_document_id', $document->id)
            ->first();

        if (! $payment) {
            return null;
        }

        return [
            'method' => $payment->method,
            'reference_number' => $payment->reference_number,
            'payment_date' => optional($payment->payment_date)->format('d/m/Y'),
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'allocations' => $payment->allocations->map(fn ($allocation) => [
                'document_number' => $allocation->document?->official_number,
                'document_type' => $allocation->document?->document_type,
                'amount' => (float) $allocation->amount,
            ])->all(),
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
