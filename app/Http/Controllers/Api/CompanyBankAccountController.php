<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyBankAccountController extends Controller
{
    public function index(Request $request, int $company): JsonResponse
    {
        $company = $this->scopedCompany($request, $company);

        return response()->json(
            $company->bankAccounts()->orderBy('sort_order')->get()
        );
    }

    public function store(Request $request, int $company): JsonResponse
    {
        $company = $this->scopedCompany($request, $company);
        $data = $this->validated($request);

        $account = DB::transaction(function () use ($company, $data) {
            $account = $company->bankAccounts()->create($data);
            $this->enforceSinglePrimary($company, $account);

            return $account->fresh();
        });

        return response()->json($account, 201);
    }

    public function update(Request $request, int $company, int $account): JsonResponse
    {
        $company = $this->scopedCompany($request, $company);
        $account = $company->bankAccounts()->findOrFail($account);
        $data = $this->validated($request, creating: false);

        DB::transaction(function () use ($account, $company, $data) {
            $account->update($data);
            $this->enforceSinglePrimary($company, $account);
        });

        return response()->json($account->fresh());
    }

    public function destroy(Request $request, int $company, int $account): JsonResponse
    {
        $company = $this->scopedCompany($request, $company);
        $account = $company->bankAccounts()->findOrFail($account);
        $account->delete();

        return response()->json(['deleted' => true]);
    }

    private function validated(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'bank_name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'account_number' => [$creating ? 'required' : 'sometimes', 'string', 'max:50'],
            'account_holder' => 'nullable|string|max:255',
            'swift_code' => 'nullable|string|max:20',
            'is_primary' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:999',
            'is_active' => 'nullable|boolean',
        ]);
    }

    private function enforceSinglePrimary(Company $company, CompanyBankAccount $account): void
    {
        if (! $account->is_primary) {
            return;
        }

        $company->bankAccounts()
            ->where('id', '!=', $account->id)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }

    private function scopedCompany(Request $request, int $companyId): Company
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && $user->company_id !== $companyId) {
            abort(403, 'Company scope violation');
        }

        return Company::findOrFail($companyId);
    }
}
