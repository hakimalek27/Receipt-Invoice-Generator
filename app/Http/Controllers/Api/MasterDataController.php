<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\DocumentTemplate;
use App\Models\NumberingPolicy;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MasterDataController extends Controller
{
    public function companies(Request $request): JsonResponse
    {
        $query = Company::query()->orderBy('name');
        if (! $request->user()->isSuperAdmin()) {
            $query->whereKey(\App\Services\ActiveCompanyResolver::resolve($request->user(), $request));
        }

        return response()->json($query->get());
    }

    public function updateCompany(Request $request, int $company): JsonResponse
    {
        $company = $this->scopedCompany($request, $company);
        $company->update($request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('companies')->ignore($company->id)],
            'address' => 'nullable|string',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:10',
            'country' => 'nullable|string|min:2|max:3',
            'phone' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'registration_number' => 'nullable|string|max:100',
            'tin' => 'nullable|string|max:100',
            'sst_registration_number' => 'nullable|string|max:100',
            'msic_code' => 'nullable|string|max:10',
            'business_activity_description' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'brand_primary' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'brand_secondary' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'brand_accent' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'pdf_boilerplate' => 'nullable|array',
            'pdf_boilerplate.invoice' => 'nullable|array',
            'pdf_boilerplate.invoice.intro' => 'nullable|string|max:500',
            'pdf_boilerplate.invoice.footer_terms' => 'nullable|string|max:2000',
            'pdf_boilerplate.invoice.signature_left_intro' => 'nullable|string|max:200',
            'pdf_boilerplate.invoice.signature_left_label' => 'nullable|string|max:200',
            'pdf_boilerplate.invoice.signature_right_intro' => 'nullable|string|max:200',
            'pdf_boilerplate.invoice.signature_right_label' => 'nullable|string|max:200',
            'pdf_boilerplate.quotation' => 'nullable|array',
            'pdf_boilerplate.quotation.intro' => 'nullable|string|max:500',
            'pdf_boilerplate.quotation.footer_terms' => 'nullable|string|max:2000',
            'pdf_boilerplate.quotation.signature_left_intro' => 'nullable|string|max:200',
            'pdf_boilerplate.quotation.signature_left_label' => 'nullable|string|max:200',
            'pdf_boilerplate.quotation.signature_right_intro' => 'nullable|string|max:200',
            'pdf_boilerplate.quotation.signature_right_label' => 'nullable|string|max:200',
            'pdf_boilerplate.delivery_order' => 'nullable|array',
            'pdf_boilerplate.delivery_order.intro' => 'nullable|string|max:500',
            'pdf_boilerplate.delivery_order.footer_terms' => 'nullable|string|max:2000',
            'pdf_boilerplate.delivery_order.signature_left_intro' => 'nullable|string|max:200',
            'pdf_boilerplate.delivery_order.signature_left_label' => 'nullable|string|max:200',
            'pdf_boilerplate.delivery_order.signature_right_intro' => 'nullable|string|max:200',
            'pdf_boilerplate.delivery_order.signature_right_label' => 'nullable|string|max:200',
            'pdf_boilerplate.official_receipt' => 'nullable|array',
            'pdf_boilerplate.official_receipt.intro' => 'nullable|string|max:500',
            'pdf_boilerplate.official_receipt.footer_terms' => 'nullable|string|max:2000',
            'pdf_boilerplate.official_receipt.signature_left_intro' => 'nullable|string|max:200',
            'pdf_boilerplate.official_receipt.signature_left_label' => 'nullable|string|max:200',
            'pdf_boilerplate.official_receipt.signature_right_intro' => 'nullable|string|max:200',
            'pdf_boilerplate.official_receipt.signature_right_label' => 'nullable|string|max:200',
            'settings' => 'nullable|array',
            'settings.show_computer_generated_footer' => 'nullable|boolean',
        ]));

        return response()->json($company->fresh());
    }

    public function customers(Request $request): JsonResponse
    {
        return response()->json(Customer::forCompany(\App\Services\ActiveCompanyResolver::resolve($request->user(), $request))->orderBy('name')->paginate(50));
    }

    public function storeCustomer(Request $request): JsonResponse
    {
        $customer = Customer::create($this->customerData($request) + [
            'company_id' => \App\Services\ActiveCompanyResolver::resolve($request->user(), $request),
        ]);

        return response()->json($customer, 201);
    }

    public function updateCustomer(Request $request, int $customer): JsonResponse
    {
        $customer = Customer::forCompany(\App\Services\ActiveCompanyResolver::resolve($request->user(), $request))->findOrFail($customer);
        $customer->update($this->customerData($request, false));

        return response()->json($customer->fresh());
    }

    public function destroyCustomer(Request $request, int $customer): JsonResponse
    {
        $customer = Customer::forCompany(\App\Services\ActiveCompanyResolver::resolve($request->user(), $request))->findOrFail($customer);
        $customer->delete();

        return response()->json(['deleted' => true]);
    }

    public function products(Request $request): JsonResponse
    {
        return response()->json(Product::forCompany(\App\Services\ActiveCompanyResolver::resolve($request->user(), $request))->orderBy('name')->paginate(50));
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $product = Product::create($this->productData($request) + [
            'company_id' => \App\Services\ActiveCompanyResolver::resolve($request->user(), $request),
        ]);

        return response()->json($product, 201);
    }

    public function updateProduct(Request $request, int $product): JsonResponse
    {
        $product = Product::forCompany(\App\Services\ActiveCompanyResolver::resolve($request->user(), $request))->findOrFail($product);
        $product->update($this->productData($request, false));

        return response()->json($product->fresh());
    }

    public function destroyProduct(Request $request, int $product): JsonResponse
    {
        $product = Product::forCompany(\App\Services\ActiveCompanyResolver::resolve($request->user(), $request))->findOrFail($product);
        $product->delete();

        return response()->json(['deleted' => true]);
    }

    public function templates(Request $request): JsonResponse
    {
        return response()->json(DocumentTemplate::forCompany(\App\Services\ActiveCompanyResolver::resolve($request->user(), $request))->orderBy('document_type')->get());
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $template = DocumentTemplate::create($this->templateData($request) + [
            'company_id' => \App\Services\ActiveCompanyResolver::resolve($request->user(), $request),
        ]);

        return response()->json($template, 201);
    }

    public function updateTemplate(Request $request, int $template): JsonResponse
    {
        $template = DocumentTemplate::forCompany(\App\Services\ActiveCompanyResolver::resolve($request->user(), $request))->findOrFail($template);
        $template->update($this->templateData($request, false));

        return response()->json($template->fresh());
    }

    public function destroyTemplate(Request $request, int $template): JsonResponse
    {
        $template = DocumentTemplate::forCompany(\App\Services\ActiveCompanyResolver::resolve($request->user(), $request))->findOrFail($template);
        $template->delete();

        return response()->json(['deleted' => true]);
    }

    public function numberingPolicies(Request $request): JsonResponse
    {
        return response()->json(NumberingPolicy::forCompany(\App\Services\ActiveCompanyResolver::resolve($request->user(), $request))->orderBy('document_type')->get());
    }

    public function storeNumberingPolicy(Request $request): JsonResponse
    {
        $policy = NumberingPolicy::create($this->numberingData($request) + [
            'company_id' => \App\Services\ActiveCompanyResolver::resolve($request->user(), $request),
        ]);

        return response()->json($policy, 201);
    }

    public function updateNumberingPolicy(Request $request, int $policy): JsonResponse
    {
        $policy = NumberingPolicy::forCompany(\App\Services\ActiveCompanyResolver::resolve($request->user(), $request))->findOrFail($policy);
        $policy->update($this->numberingData($request, false));

        return response()->json($policy->fresh());
    }

    public function destroyNumberingPolicy(Request $request, int $policy): JsonResponse
    {
        $policy = NumberingPolicy::forCompany(\App\Services\ActiveCompanyResolver::resolve($request->user(), $request))->findOrFail($policy);
        $policy->delete();

        return response()->json(['deleted' => true]);
    }

    private function scopedCompany(Request $request, int $companyId): Company
    {
        if (! $request->user()->isSuperAdmin() && \App\Services\ActiveCompanyResolver::resolve($request->user(), $request) !== $companyId) {
            abort(403, 'Company scope violation');
        }

        return Company::findOrFail($companyId);
    }

    public static function customerRules(bool $creating = true): array
    {
        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'attention_to' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:10',
            'country' => 'nullable|string|min:2|max:3',
            'phone' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'tax_identifier' => 'nullable|string|max:100',
            'brn_registration_number' => 'nullable|string|max:100',
            'sst_registration_number' => 'nullable|string|max:100',
            'msic_code' => 'nullable|string|max:10',
            'is_active' => 'nullable|boolean',
        ];
    }

    private function customerData(Request $request, bool $creating = true): array
    {
        return $request->validate(self::customerRules($creating));
    }

    public static function productRules(bool $creating = true): array
    {
        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => 'nullable|string',
            'sku' => 'nullable|string|max:100',
            'default_price' => 'nullable|numeric|min:0',
            'uom' => 'nullable|string|max:20',
            'tax_type' => 'nullable|string|max:50',
            'tax_rate' => 'nullable|numeric|min:0',
            'classification_code' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ];
    }

    private function productData(Request $request, bool $creating = true): array
    {
        return $request->validate(self::productRules($creating));
    }

    private function templateData(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'document_type' => [$creating ? 'required' : 'sometimes', 'string', 'max:50'],
            'paper_size' => 'nullable|string|max:10',
            'is_default' => 'nullable|boolean',
            'show_amount_in_words' => 'nullable|boolean',
            'amount_in_words_locale' => 'nullable|string|max:20',
            'amount_in_words_currency' => 'nullable|string|size:3',
            'amount_in_words_zero_sen_style' => 'nullable|string|max:50',
            'amount_in_words_label' => 'nullable|string|max:100',
            'amount_in_words_position' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);
    }

    private function numberingData(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'document_type' => [$creating ? 'required' : 'sometimes', 'string', 'max:50'],
            'prefix' => 'nullable|string|max:50',
            'suffix' => 'nullable|string|max:50',
            'separator' => 'nullable|string|max:5',
            'year_token' => 'nullable|string|max:20',
            'sequence_padding' => 'nullable|integer|min:1|max:12',
            'reset_policy' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);
    }
}
