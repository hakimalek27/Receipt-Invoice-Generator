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
