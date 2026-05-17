<?php

namespace App\Services;

use App\Models\Document;

class DocumentFingerprintService
{
    public function hash(Document $document): string
    {
        return hash('sha256', json_encode(
            $this->payload($document),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
    }

    public function payload(Document $document): array
    {
        $document->loadMissing('items');

        return [
            'company_id' => $document->company_id,
            'document_type' => $document->document_type,
            'customer_id' => $document->customer_id,
            'document_date' => optional($document->document_date)->format('Y-m-d'),
            'due_date' => optional($document->due_date)->format('Y-m-d'),
            'currency' => $document->currency ?? 'MYR',
            'fx_rate' => $this->decimal($document->fx_rate, 8),
            'notes' => $document->notes,
            'terms' => $document->terms,
            'show_amount_in_words' => (bool) $document->show_amount_in_words,
            'amount_in_words_locale' => $document->amount_in_words_locale,
            'amount_in_words_currency' => $document->amount_in_words_currency,
            'subtotal' => $this->decimal($document->subtotal),
            'discount_total' => $this->decimal($document->discount_total),
            'tax_total' => $this->decimal($document->tax_total),
            'grand_total' => $this->decimal($document->grand_total),
            'items' => $document->items
                ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
                ->values()
                ->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => $this->decimal($item->quantity, 4),
                    'uom' => $item->uom,
                    'unit_price' => $this->decimal($item->unit_price),
                    'discount' => $this->decimal($item->discount),
                    'line_total' => $this->decimal($item->line_total),
                    'tax_type' => $item->tax_type,
                    'tax_rate' => $this->decimal($item->tax_rate),
                    'tax_amount' => $this->decimal($item->tax_amount),
                    'classification_code' => $item->classification_code,
                    'tax_exemption_reason' => $item->tax_exemption_reason,
                    'sort_order' => (int) $item->sort_order,
                ])
                ->all(),
            'attachments' => [],
        ];
    }

    private function decimal(mixed $value, int $precision = 2): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, $precision, '.', '');
    }
}
