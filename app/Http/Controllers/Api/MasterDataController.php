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
            $query->whereKey($request->user()->company_id);
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
        ]));

        return response()->json($company->fresh());
    }

    public function customers(Request $request): JsonResponse
    {
        return response()->json(Customer::forCompany($request->user()->company_id)->orderBy('name')->paginate(50));
    }

    public function storeCustomer(Request $request): JsonResponse
    {
        $customer = Customer::create($this->customerData($request) + [
            'company_id' => $request->user()->company_id,
        ]);

        return response()->json($customer, 201);
    }

    public function updateCustomer(Request $request, int $customer): JsonResponse
    {
        $customer = Customer::forCompany($request->user()->company_id)->findOrFail($customer);
        $customer->update($this->customerData($request, false));

        return response()->json($customer->fresh());
    }

    public function products(Request $request): JsonResponse
    {
        return response()->json(Product::forCompany($request->user()->company_id)->orderBy('name')->paginate(50));
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $product = Product::create($this->productData($request) + [
            'company_id' => $request->user()->company_id,
        ]);

        return response()->json($product, 201);
    }

    public function updateProduct(Request $request, int $product): JsonResponse
    {
        $product = Product::forCompany($request->user()->company_id)->findOrFail($product);
        $product->update($this->productData($request, false));

        return response()->json($product->fresh());
    }

    public function templates(Request $request): JsonResponse
    {
        return response()->json(DocumentTemplate::forCompany($request->user()->company_id)->orderBy('document_type')->get());
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $template = DocumentTemplate::create($this->templateData($request) + [
            'company_id' => $request->user()->company_id,
        ]);

        return response()->json($template, 201);
    }

    public function updateTemplate(Request $request, int $template): JsonResponse
    {
        $template = DocumentTemplate::forCompany($request->user()->company_id)->findOrFail($template);
        $template->update($this->templateData($request, false));

        return response()->json($template->fresh());
    }

    public function numberingPolicies(Request $request): JsonResponse
    {
        return response()->json(NumberingPolicy::forCompany($request->user()->company_id)->orderBy('document_type')->get());
    }

    public function storeNumberingPolicy(Request $request): JsonResponse
    {
        $policy = NumberingPolicy::create($this->numberingData($request) + [
            'company_id' => $request->user()->company_id,
        ]);

        return response()->json($policy, 201);
    }

    public function updateNumberingPolicy(Request $request, int $policy): JsonResponse
    {
        $policy = NumberingPolicy::forCompany($request->user()->company_id)->findOrFail($policy);
        $policy->update($this->numberingData($request, false));

        return response()->json($policy->fresh());
    }

    private function scopedCompany(Request $request, int $companyId): Company
    {
        if (! $request->user()->isSuperAdmin() && $request->user()->company_id !== $companyId) {
            abort(403, 'Company scope violation');
        }

        return Company::findOrFail($companyId);
    }

    private function customerData(Request $request, bool $creating = true): array
    {
        return $request->validate([
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
        ]);
    }

    private function productData(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => 'nullable|string',
            'sku' => 'nullable|string|max:100',
            'default_price' => 'nullable|numeric|min:0',
            'uom' => 'nullable|string|max:20',
            'tax_type' => 'nullable|string|max:50',
            'tax_rate' => 'nullable|numeric|min:0',
            'classification_code' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);
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
