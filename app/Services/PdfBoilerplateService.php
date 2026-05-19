<?php

namespace App\Services;

class PdfBoilerplateService
{
    /**
     * Default boilerplate text per document type. Used as fallback when a company
     * has not customised its pdf_boilerplate JSON. Per-doc-type keys:
     * - intro            : optional intro line above the items table
     * - footer_terms     : multi-line terms shown below totals (supports \n)
     * - signature_left_intro / signature_left_label  : left sign-off column
     * - signature_right_intro / signature_right_label: right sign-off column
     *
     * Tokens replaced at render time:
     *   {company_name} -> company display name (uppercased)
     */
    public const DEFAULTS = [
        'invoice' => [
            'intro' => null,
            'footer_terms' => "Goods sold are not returnable and payment made is not refundable.\nAll cheques should be crossed and made payable to {company_name}.",
            'signature_left_intro' => 'Yours faithfully,',
            'signature_left_label' => 'Authorised Signature',
            'signature_right_intro' => 'Goods received in right and good condition',
            'signature_right_label' => 'Company Sign & Chop',
        ],
        'quotation' => [
            'intro' => 'Thank you for your inquiry. We are pleased to submit our quote as follows:',
            'footer_terms' => "We hope that our quotation is favourable to you and we look forward to receiving your valued order.\nIf you require further clarification, please do not hesitate to contact us.",
            'signature_left_intro' => 'Yours faithfully,',
            'signature_left_label' => 'Authorised Signature',
            'signature_right_intro' => 'We confirm the order by accepting the terms',
            'signature_right_label' => 'Signature & Company Stamp',
        ],
        'delivery_order' => [
            'intro' => null,
            'footer_terms' => '',
            'signature_left_intro' => 'Delivered by,',
            'signature_left_label' => 'Authorised Signature',
            'signature_right_intro' => 'Goods received in right and good condition',
            'signature_right_label' => 'Customer Sign & Chop',
        ],
        'official_receipt' => [
            'intro' => 'Received with thanks the sum of:',
            'footer_terms' => '',
            'signature_left_intro' => null,
            'signature_left_label' => null,
            'signature_right_intro' => 'For {company_name}',
            'signature_right_label' => 'Authorised Signature',
        ],
    ];

    /**
     * Resolve the boilerplate map for a (company, document_type) pair, merging
     * defaults with overrides and replacing tokens.
     *
     * @return array<string, string|null>
     */
    public function resolve(?array $companyBoilerplate, string $documentType, ?string $companyName): array
    {
        $defaults = self::DEFAULTS[$documentType] ?? [];
        $overrides = $companyBoilerplate[$documentType] ?? [];
        $merged = array_merge($defaults, array_filter($overrides, fn ($v) => $v !== null && $v !== ''));

        $companyName = strtoupper((string) ($companyName ?? ''));
        foreach ($merged as $k => $v) {
            if (is_string($v)) {
                $merged[$k] = str_replace('{company_name}', $companyName, $v);
            }
        }

        return $merged;
    }
}
