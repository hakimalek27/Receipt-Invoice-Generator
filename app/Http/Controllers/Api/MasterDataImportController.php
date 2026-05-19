<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Services\ActiveCompanyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MasterDataImportController extends Controller
{
    private const MAX_ROWS = 500;

    public function importCustomers(Request $request): JsonResponse
    {
        return $this->import(
            $request,
            ['name', 'attention_to', 'address', 'address_line_2', 'city', 'state', 'postcode',
                'country', 'phone', 'email', 'tax_identifier', 'brn_registration_number',
                'sst_registration_number', 'msic_code'],
            MasterDataController::customerRules(true),
            fn (array $row, int $companyId) => Customer::create($row + ['company_id' => $companyId]),
            fn (string $name, int $companyId) => Customer::where('company_id', $companyId)
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->exists(),
        );
    }

    public function importProducts(Request $request): JsonResponse
    {
        return $this->import(
            $request,
            ['name', 'description', 'sku', 'default_price', 'uom', 'tax_type',
                'tax_rate', 'classification_code'],
            MasterDataController::productRules(true),
            fn (array $row, int $companyId) => Product::create($row + ['company_id' => $companyId]),
            fn (string $name, int $companyId) => Product::where('company_id', $companyId)
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->exists(),
        );
    }

    public function customerTemplate(): StreamedResponse
    {
        return $this->templateResponse('customers.csv', [
            'name', 'attention_to', 'address', 'address_line_2', 'city', 'state', 'postcode',
            'country', 'phone', 'email', 'tax_identifier', 'brn_registration_number',
            'sst_registration_number', 'msic_code',
        ]);
    }

    public function productTemplate(): StreamedResponse
    {
        return $this->templateResponse('products.csv', [
            'name', 'description', 'sku', 'default_price', 'uom', 'tax_type',
            'tax_rate', 'classification_code',
        ]);
    }

    private function import(
        Request $request,
        array $allowedFields,
        array $rules,
        callable $createRow,
        callable $duplicateCheck,
    ): JsonResponse {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $companyId = ActiveCompanyResolver::resolve($request->user(), $request);
        if (! $companyId) {
            return response()->json(['error' => 'No active company.'], 403);
        }

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if (! $handle) {
            return response()->json(['error' => 'Could not open uploaded file.'], 422);
        }

        $rawHeaders = fgetcsv($handle);
        if (! $rawHeaders) {
            fclose($handle);
            return response()->json(['error' => 'CSV is empty or unreadable.'], 422);
        }

        // Strip UTF-8 BOM from first header cell.
        $rawHeaders[0] = preg_replace('/^\xEF\xBB\xBF/', '', $rawHeaders[0]) ?? $rawHeaders[0];
        $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $rawHeaders);

        if (! in_array('name', $headers, true)) {
            fclose($handle);
            return response()->json(['error' => 'CSV must include a "name" header column.'], 422);
        }

        $inserted = 0;
        $skipped = 0;
        $errors = [];
        $rowNum = 1; // header row counted as row 1

        DB::beginTransaction();
        try {
            while (($line = fgetcsv($handle)) !== false) {
                $rowNum++;

                if ($rowNum - 1 > self::MAX_ROWS) {
                    $errors[] = ['row' => $rowNum, 'message' => 'Row limit exceeded; stopped at '.self::MAX_ROWS];
                    break;
                }

                // Skip fully empty lines.
                if (count(array_filter($line, fn ($v) => $v !== null && $v !== '')) === 0) {
                    continue;
                }

                $assoc = [];
                foreach ($headers as $i => $key) {
                    if (in_array($key, $allowedFields, true) && isset($line[$i])) {
                        $value = trim((string) $line[$i]);
                        $assoc[$key] = $value === '' ? null : $value;
                    }
                }

                if (empty($assoc['name'])) {
                    $errors[] = ['row' => $rowNum, 'message' => 'Missing name.'];
                    continue;
                }

                if ($duplicateCheck($assoc['name'], $companyId)) {
                    $skipped++;
                    continue;
                }

                $validator = Validator::make($assoc, $rules);
                if ($validator->fails()) {
                    $errors[] = [
                        'row' => $rowNum,
                        'message' => $validator->errors()->first(),
                    ];
                    continue;
                }

                $createRow($assoc, $companyId);
                $inserted++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            return response()->json(['error' => 'Import aborted: '.$e->getMessage()], 500);
        }

        fclose($handle);

        return response()->json([
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    private function templateResponse(string $filename, array $headers): StreamedResponse
    {
        return Response::stream(function () use ($headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
