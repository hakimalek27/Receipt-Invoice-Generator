<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyBrandingController extends Controller
{
    private const KINDS = ['logo', 'stamp', 'signature'];

    public function upload(Request $request, int $company, string $kind): JsonResponse
    {
        if (! in_array($kind, self::KINDS, true)) {
            abort(404);
        }

        $company = $this->scopedCompany($request, $company);

        $data = $request->validate([
            'file' => 'required|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        $column = $kind.'_path';

        if ($company->{$column}) {
            Storage::disk('public')->delete($company->{$column});
        }

        $path = $data['file']->store("companies/{$company->id}", 'public');
        $company->forceFill([$column => $path])->save();

        return response()->json([
            'kind' => $kind,
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'company' => $company->fresh(),
        ]);
    }

    public function destroy(Request $request, int $company, string $kind): JsonResponse
    {
        if (! in_array($kind, self::KINDS, true)) {
            abort(404);
        }

        $company = $this->scopedCompany($request, $company);
        $column = $kind.'_path';

        if ($company->{$column}) {
            Storage::disk('public')->delete($company->{$column});
            $company->forceFill([$column => null])->save();
        }

        return response()->json([
            'kind' => $kind,
            'company' => $company->fresh(),
        ]);
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
