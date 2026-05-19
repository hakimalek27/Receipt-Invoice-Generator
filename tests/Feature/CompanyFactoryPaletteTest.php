<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyFactoryPaletteTest extends TestCase
{
    use RefreshDatabase;

    public function test_virtue_damsel_uses_carrot_orange_with_teal_accent(): void
    {
        $company = Company::factory()->virtueDamsel()->create();

        $this->assertSame('#E67E22', $company->brand_primary);
        $this->assertSame('#FBEEE6', $company->brand_secondary);
        $this->assertSame('#16A085', $company->brand_accent);
    }

    public function test_nas_ceria_uses_islamic_navy_cream_gold(): void
    {
        $company = Company::factory()->nasCeria()->create();

        $this->assertSame('#1F3A5F', $company->brand_primary);
        $this->assertSame('#F4ECD8', $company->brand_secondary);
        $this->assertSame('#C0A062', $company->brand_accent);
    }

    public function test_persada_keeps_purple_palette(): void
    {
        $company = Company::factory()->persada()->create();

        $this->assertSame('#5d3a9b', $company->brand_primary);
        $this->assertSame('#3f2872', $company->brand_accent);
    }

    public function test_wehdah_keeps_navy_palette(): void
    {
        $company = Company::factory()->wehdah()->create();

        $this->assertSame('#1a3a5c', $company->brand_primary);
        $this->assertSame('#16427a', $company->brand_accent);
    }
}
