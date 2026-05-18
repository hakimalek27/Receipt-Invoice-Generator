<?php

namespace App\Services;

use App\Models\NumberingPolicy;
use App\Models\SequenceCounter;
use Illuminate\Support\Facades\DB;

class NumberingService
{
    /**
     * Allocate the next official number for the given company/document/year.
     * Uses SELECT ... FOR UPDATE to prevent concurrent collisions.
     *
     * @throws \RuntimeException if no active policy exists
     */
    public function allocate(int $companyId, string $documentType, ?int $year = null): string
    {
        $year = $year ?? now()->year;

        $policy = NumberingPolicy::active()
            ->forCompany($companyId)
            ->forType($documentType)
            ->first();

        if (! $policy) {
            throw new \RuntimeException(
                "No active numbering policy for company {$companyId}, type {$documentType}"
            );
        }

        return DB::transaction(function () use ($companyId, $documentType, $year, $policy) {
            SequenceCounter::query()->upsert([
                [
                    'company_id' => $companyId,
                    'document_type' => $documentType,
                    'year' => $year,
                    'current_sequence' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ], ['company_id', 'document_type', 'year'], ['updated_at']);

            $counter = SequenceCounter::where('company_id', $companyId)
                ->where('document_type', $documentType)
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            $counter->increment('current_sequence');
            $counter->refresh();

            return $this->format($policy, $counter->current_sequence, $year);
        });
    }

    /**
     * Generate a format-only preview. Never reserves a number.
     */
    public function preview(int $companyId, string $documentType, ?int $year = null): string
    {
        $year = $year ?? now()->year;

        $policy = NumberingPolicy::active()
            ->forCompany($companyId)
            ->forType($documentType)
            ->first();

        if (! $policy) {
            throw new \RuntimeException(
                "No active numbering policy for company {$companyId}, type {$documentType}"
            );
        }

        return $policy->preview($year);
    }

    /**
     * Format a sequence number according to the policy.
     */
    private function format(NumberingPolicy $policy, int $sequence, int $year): string
    {
        $parts = [];

        if ($policy->prefix) {
            $parts[] = $policy->prefix;
        }

        $yearPart = str_replace('{YYYY}', (string) $year, $policy->year_token);
        $parts[] = $yearPart;

        $padded = str_pad((string) $sequence, $policy->sequence_padding, '0', STR_PAD_LEFT);
        $parts[] = $padded;

        if ($policy->suffix) {
            $parts[] = $policy->suffix;
        }

        return implode($policy->separator ?? '-', $parts);
    }
}
