<?php

namespace App\Services;

class AmountInWordsService
{
    private const MALAY_UNITS = [
        '', 'satu', 'dua', 'tiga', 'empat', 'lima',
        'enam', 'tujuh', 'lapan', 'sembilan',
    ];

    private const MALAY_TENS = [
        '', '', 'dua puluh', 'tiga puluh', 'empat puluh',
        'lima puluh', 'enam puluh', 'tujuh puluh', 'lapan puluh', 'sembilan puluh',
    ];

    private const MALAY_TEENS = [
        'sepuluh', 'sebelas', 'dua belas', 'tiga belas', 'empat belas',
        'lima belas', 'enam belas', 'tujuh belas', 'lapan belas', 'sembilan belas',
    ];

    private const ENGLISH_UNITS = [
        '', 'one', 'two', 'three', 'four', 'five',
        'six', 'seven', 'eight', 'nine',
    ];

    private const ENGLISH_TENS = [
        '', '', 'twenty', 'thirty', 'forty',
        'fifty', 'sixty', 'seventy', 'eighty', 'ninety',
    ];

    private const ENGLISH_TEENS = [
        'ten', 'eleven', 'twelve', 'thirteen', 'fourteen',
        'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen',
    ];

    /**
     * Convert amount to words.
     */
    public function convert(
        float $amount,
        string $locale = 'ms_MY',
        string $currency = 'MYR',
        string $zeroSenStyle = 'SAHAJA'
    ): string {
        $negative = $amount < 0;
        $amount = abs($amount);

        $ringgit = (int) floor($amount);
        $sen = (int) round(($amount - $ringgit) * 100);

        if ($locale === 'ms_MY') {
            return $this->convertMalay($ringgit, $sen, $zeroSenStyle, $negative);
        }

        if ($locale === 'en_WEHDAH') {
            return $this->convertWehdahEnglish($ringgit, $sen, $zeroSenStyle, $negative);
        }

        return $this->convertEnglish($ringgit, $sen, $currency, $negative);
    }

    private function convertWehdahEnglish(int $ringgit, int $sen, string $zeroSenStyle, bool $negative): string
    {
        $parts = [];

        if ($negative) {
            $parts[] = 'NEGATIVE';
        }

        $parts[] = 'RM:';

        if ($ringgit === 0) {
            $parts[] = 'ZERO';
        } else {
            $parts[] = $this->insertHundredsAnd($this->numberToEnglishWords($ringgit));
        }

        if ($sen === 0) {
            if ($zeroSenStyle !== 'NONE') {
                $parts[] = 'ONLY';
            }
        } else {
            $parts[] = 'AND';
            $parts[] = $this->insertHundredsAnd($this->numberToEnglishWords($sen));
            $parts[] = 'CENTS';
        }

        return strtoupper(implode(' ', $parts));
    }

    private function insertHundredsAnd(string $words): string
    {
        return preg_replace('/(\bhundred)\s+(?!and\b)([a-z])/i', '$1 and $2', $words);
    }

    private function convertMalay(int $ringgit, int $sen, string $zeroSenStyle, bool $negative): string
    {
        $parts = [];

        if ($negative) {
            $parts[] = 'NEGATIF';
        }

        $parts[] = 'RINGGIT MALAYSIA';

        if ($ringgit === 0) {
            $parts[] = 'KOSONG';
        } else {
            $parts[] = $this->numberToMalayWords($ringgit);
        }

        if ($sen === 0) {
            if ($zeroSenStyle === 'SAHAJA') {
                $parts[] = 'SAHAJA';
            } else {
                $parts[] = 'DAN SIFAR SEN';
            }
        } else {
            $parts[] = 'DAN';
            $parts[] = $this->numberToMalayWords($sen);
            $parts[] = 'SEN';
        }

        return strtoupper(implode(' ', $parts));
    }

    private function numberToMalayWords(int $number): string
    {
        if ($number === 0) {
            return 'kosong';
        }

        $words = [];

        // Billions
        if ($number >= 1_000_000_000) {
            $b = (int) ($number / 1_000_000_000);
            $words[] = $this->numberToMalayWords($b);
            $words[] = 'bilion';
            $number %= 1_000_000_000;
        }

        // Millions
        if ($number >= 1_000_000) {
            $m = (int) ($number / 1_000_000);
            $words[] = $this->numberToMalayWords($m);
            $words[] = 'juta';
            $number %= 1_000_000;
        }

        // Thousands
        if ($number >= 1_000) {
            $k = (int) ($number / 1_000);
            if ($k === 1) {
                $words[] = 'seribu';
            } else {
                $words[] = $this->numberToMalayWords($k);
                $words[] = 'ribu';
            }
            $number %= 1_000;
        }

        // Hundreds
        if ($number >= 100) {
            $h = (int) ($number / 100);
            if ($h === 1) {
                $words[] = 'seratus';
            } else {
                $words[] = self::MALAY_UNITS[$h] . ' ratus';
            }
            $number %= 100;
        }

        // Tens and ones
        if ($number >= 10 && $number <= 19) {
            $words[] = self::MALAY_TEENS[$number - 10];
        } else {
            if ($number >= 20) {
                $t = (int) ($number / 10);
                $words[] = self::MALAY_TENS[$t];
                $number %= 10;
            }
            if ($number > 0) {
                $words[] = self::MALAY_UNITS[$number];
            }
        }

        return implode(' ', array_filter($words));
    }

    private function convertEnglish(int $dollars, int $cents, string $currency, bool $negative): string
    {
        $parts = [];

        if ($negative) {
            $parts[] = 'NEGATIVE';
        }

        if ($dollars === 0) {
            $parts[] = 'ZERO';
        } else {
            $parts[] = $this->numberToEnglishWords($dollars);
        }

        $parts[] = strtoupper($currency);

        if ($cents === 0) {
            $parts[] = 'ONLY';
        } else {
            $parts[] = 'AND';
            $parts[] = $this->numberToEnglishWords($cents);
            $parts[] = 'CENTS';
        }

        return strtoupper(implode(' ', $parts));
    }

    private function numberToEnglishWords(int $number): string
    {
        if ($number === 0) {
            return 'zero';
        }

        $words = [];

        if ($number >= 1_000_000) {
            $m = (int) ($number / 1_000_000);
            $words[] = $this->numberToEnglishWords($m);
            $words[] = 'million';
            $number %= 1_000_000;
        }

        if ($number >= 1_000) {
            $k = (int) ($number / 1_000);
            $words[] = $this->numberToEnglishWords($k);
            $words[] = 'thousand';
            $number %= 1_000;
        }

        if ($number >= 100) {
            $h = (int) ($number / 100);
            $words[] = self::ENGLISH_UNITS[$h] . ' hundred';
            $number %= 100;
        }

        if ($number >= 10 && $number <= 19) {
            $words[] = self::ENGLISH_TEENS[$number - 10];
        } else {
            if ($number >= 20) {
                $t = (int) ($number / 10);
                $words[] = self::ENGLISH_TENS[$t];
                $number %= 10;
            }
            if ($number > 0) {
                $words[] = self::ENGLISH_UNITS[$number];
            }
        }

        return implode(' ', array_filter($words));
    }
}
