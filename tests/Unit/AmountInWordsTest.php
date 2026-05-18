<?php

namespace Tests\Unit;

use App\Services\AmountInWordsService;
use Tests\TestCase;

class AmountInWordsTest extends TestCase
{
    private AmountInWordsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AmountInWordsService::class);
    }

    public function test_malay_myr_0_00_sahaja(): void
    {
        $result = $this->service->convert(0.00, 'ms_MY', 'MYR', 'SAHAJA');
        $this->assertEquals('RINGGIT MALAYSIA KOSONG SAHAJA', $result);
    }

    public function test_malay_myr_0_00_dan_sifar_sen(): void
    {
        $result = $this->service->convert(0.00, 'ms_MY', 'MYR', 'DAN SIFAR SEN');
        $this->assertEquals('RINGGIT MALAYSIA KOSONG DAN SIFAR SEN', $result);
    }

    public function test_malay_myr_1_00(): void
    {
        $result = $this->service->convert(1.00, 'ms_MY');
        $this->assertEquals('RINGGIT MALAYSIA SATU SAHAJA', $result);
    }

    public function test_malay_myr_1_01(): void
    {
        $result = $this->service->convert(1.01, 'ms_MY');
        $this->assertEquals('RINGGIT MALAYSIA SATU DAN SATU SEN', $result);
    }

    public function test_malay_myr_10_10(): void
    {
        $result = $this->service->convert(10.10, 'ms_MY');
        $this->assertEquals('RINGGIT MALAYSIA SEPULUH DAN SEPULUH SEN', $result);
    }

    public function test_malay_myr_100_05(): void
    {
        $result = $this->service->convert(100.05, 'ms_MY');
        $this->assertEquals('RINGGIT MALAYSIA SERATUS DAN LIMA SEN', $result);
    }

    public function test_contract_example_1234567_89(): void
    {
        $result = $this->service->convert(1234567.89, 'ms_MY');
        $this->assertEquals(
            'RINGGIT MALAYSIA SATU JUTA DUA RATUS TIGA PULUH EMPAT RIBU LIMA RATUS ENAM PULUH TUJUH DAN LAPAN PULUH SEMBILAN SEN',
            $result
        );
    }

    public function test_malay_spelling_uses_tiga_not_tuga(): void
    {
        $result = $this->service->convert(3.00, 'ms_MY');
        $this->assertStringContainsString('TIGA', $result);
        $this->assertStringNotContainsString('TUGA', $result);
    }

    public function test_malay_teen_numbers(): void
    {
        $this->assertEquals('RINGGIT MALAYSIA SEBELAS SAHAJA', $this->service->convert(11));
        $this->assertEquals('RINGGIT MALAYSIA DUA BELAS SAHAJA', $this->service->convert(12));
        $this->assertEquals('RINGGIT MALAYSIA TIGA BELAS SAHAJA', $this->service->convert(13));
        $this->assertEquals('RINGGIT MALAYSIA SEMBILAN BELAS SAHAJA', $this->service->convert(19));
    }

    public function test_malay_seratus_and_seribu(): void
    {
        $this->assertEquals('RINGGIT MALAYSIA SERATUS SAHAJA', $this->service->convert(100));
        $this->assertEquals('RINGGIT MALAYSIA SERIBU SAHAJA', $this->service->convert(1000));
    }

    public function test_english_usd(): void
    {
        $result = $this->service->convert(100.00, 'en_MY', 'USD', 'ONLY');
        $this->assertEquals('ONE HUNDRED USD ONLY', $result);
    }

    public function test_english_with_cents(): void
    {
        $result = $this->service->convert(100.50, 'en_MY', 'USD', 'ONLY');
        $this->assertEquals('ONE HUNDRED USD AND FIFTY CENTS', $result);
    }
}
