<?php

if (! function_exists('document_type_options')) {
    function document_type_options(): array
    {
        return [
            'invoice',
            'quotation',
            'official_receipt',
            'delivery_order',
            'cash_bill',
            'credit_note',
            'debit_note',
            'purchase_order',
            'payment_voucher',
            'proforma_invoice',
        ];
    }
}

if (! function_exists('effective_company_id')) {
    function effective_company_id(): ?int
    {
        return \App\Services\ActiveCompanyResolver::resolve(request()->user(), request());
    }
}

if (! function_exists('derivation_targets_for')) {
    /**
     * Return the list of document types this source can be derived into.
     * Single source of truth for both backend validation and the Vue UI.
     */
    function derivation_targets_for(?string $documentType): array
    {
        return \App\Services\DocumentWorkflowService::DERIVATION_TARGETS[$documentType] ?? [];
    }
}

if (! function_exists('derivation_targets_map')) {
    /**
     * Full map of source → allowed targets, used by the Edit Inertia page
     * to render the "Generate from this" button group without re-hardcoding.
     */
    function derivation_targets_map(): array
    {
        return \App\Services\DocumentWorkflowService::DERIVATION_TARGETS;
    }
}

if (! function_exists('thermal_eligible_doc_types')) {
    /**
     * Document types that should be renderable on 60mm thermal paper.
     * These are the short transactional receipt-style docs; everything
     * else (invoice, quotation, DO, ...) demands A4 with full details.
     */
    function thermal_eligible_doc_types(): array
    {
        return ['cash_bill', 'official_receipt', 'payment_voucher'];
    }
}

if (! function_exists('is_thermal_eligible')) {
    function is_thermal_eligible(?string $documentType): bool
    {
        return in_array($documentType, thermal_eligible_doc_types(), true);
    }
}
