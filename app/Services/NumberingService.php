<?php

namespace App\Services;

use App\Models\Document;
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

            // Gap-fill: pick the lowest sequence number in [1..current_sequence]
            // that no live document is using. Soft-deleted docs are auto-excluded
            // by the SoftDeletes default scope, so their numbers become free again.
            $usedSequences = Document::where('company_id', $companyId)
                ->where('document_type', $documentType)
                ->whereYear('document_date', $year)
                ->whereNotNull('official_number')
                ->pluck('official_number')
                ->map(fn ($n) => $this->extractSequence((string) $n, $policy))
                ->filter(fn ($v) => $v !== null)
                ->values()
                ->all();
            $usedSet = array_flip($usedSequences);

            for ($candidate = 1; $candidate <= $counter->current_sequence; $candidate++) {
                if (! isset($usedSet[$candidate])) {
                    // Found a hole — re-use it without bumping the counter.
                    return $this->format($policy, $candidate, $year);
                }
            }

            // No gaps → continue the sequence as before.
            $counter->increment('current_sequence');
            $counter->refresh();

            return $this->format($policy, $counter->current_sequence, $year);
        });
    }

    /**
     * Parse the numeric sequence portion out of a fully-formatted official
     * number. Returns null if the format does not match the policy (e.g.
     * legacy data, manual entry).
     *
     * Heuristic: walk the segments back-to-front and return the first
     * all-digit segment whose length matches the policy's sequence_padding.
     * That avoids confusing the sequence with the year token, which is
     * typically a different width.
     */
    private function extractSequence(string $officialNumber, NumberingPolicy $policy): ?int
    {
        $separator = $policy->separator ?? '-';
        if ($separator === '') {
            return null;
        }

        $parts = explode($separator, $officialNumber);
        $expectedLen = (int) ($policy->sequence_padding ?? 0);

        foreach (array_reverse($parts) as $part) {
            if (preg_match('/^\d+$/', $part)
                && ($expectedLen === 0 || strlen($part) === $expectedLen)) {
                return (int) $part;
            }
        }

        return null;
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
