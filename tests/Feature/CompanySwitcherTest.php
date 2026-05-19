<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\User;
use App\Services\ActiveCompanyResolver;
use App\Services\DocumentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanySwitcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_switch_active_company_via_session(): void
    {
        $wehdah = Company::factory()->wehdah()->create();
        $ncs = Company::factory()->nasCeria()->create();
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'company_id' => $wehdah->id,
        ]);

        $response = $this->actingAs($superAdmin)
            ->from('/dashboard')
            ->post('/active-company', ['company_id' => $ncs->id]);

        $response->assertRedirect('/dashboard');
        $this->assertSame($ncs->id, session('active_company_id'));
    }

    public function test_regular_admin_cannot_switch_company(): void
    {
        $wehdah = Company::factory()->wehdah()->create();
        $other = Company::factory()->create(['code' => 'X1']);
        $admin = User::factory()->create([
            'role' => 'admin',
            'company_id' => $wehdah->id,
        ]);

        $this->actingAs($admin)
            ->post('/active-company', ['company_id' => $other->id])
            ->assertForbidden();

        $this->assertNull(session('active_company_id'));
    }

    public function test_resolver_returns_session_override_for_super_admin(): void
    {
        $wehdah = Company::factory()->wehdah()->create();
        $ncs = Company::factory()->nasCeria()->create();
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'company_id' => $wehdah->id,
        ]);

        $request = \Illuminate\Http\Request::create('/');
        $request->setLaravelSession(app('session.store'));
        $request->session()->put('active_company_id', $ncs->id);

        $this->assertSame($ncs->id, ActiveCompanyResolver::resolve($superAdmin, $request));
    }

    public function test_resolver_ignores_session_override_for_regular_admin(): void
    {
        $wehdah = Company::factory()->wehdah()->create();
        $ncs = Company::factory()->nasCeria()->create();
        $admin = User::factory()->create([
            'role' => 'admin',
            'company_id' => $wehdah->id,
        ]);

        $request = \Illuminate\Http\Request::create('/');
        $request->setLaravelSession(app('session.store'));
        $request->session()->put('active_company_id', $ncs->id);

        $this->assertSame($wehdah->id, ActiveCompanyResolver::resolve($admin, $request));
    }

    public function test_clearing_switcher_reverts_to_user_company(): void
    {
        $wehdah = Company::factory()->wehdah()->create();
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'company_id' => $wehdah->id,
        ]);

        $this->actingAs($superAdmin)
            ->from('/dashboard')
            ->post('/active-company', ['company_id' => null])
            ->assertRedirect();

        $this->assertNull(session('active_company_id'));
        $this->assertSame($wehdah->id, ActiveCompanyResolver::resolve($superAdmin, request()));
    }

    public function test_documents_list_scoped_to_active_company_for_super_admin(): void
    {
        $wehdah = Company::factory()->wehdah()->create();
        $ncs = Company::factory()->nasCeria()->create();

        \App\Models\NumberingPolicy::create([
            'company_id' => $wehdah->id, 'document_type' => 'invoice',
            'prefix' => 'WS-INV', 'separator' => '-', 'year_token' => '{YYYY}',
            'sequence_padding' => 5, 'reset_policy' => 'yearly', 'is_active' => true,
        ]);
        \App\Models\NumberingPolicy::create([
            'company_id' => $ncs->id, 'document_type' => 'invoice',
            'prefix' => 'NCS-INV', 'separator' => '-', 'year_token' => '{YYYY}',
            'sequence_padding' => 5, 'reset_policy' => 'yearly', 'is_active' => true,
        ]);

        $workflow = app(DocumentWorkflowService::class);
        $wsDoc = $workflow->createDraft([
            'company_id' => $wehdah->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'WS item', 'quantity' => 1, 'unit_price' => 100]],
        ]);
        $ncsDoc = $workflow->createDraft([
            'company_id' => $ncs->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'NCS item', 'quantity' => 1, 'unit_price' => 200]],
        ]);

        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'company_id' => $wehdah->id,
        ]);

        // Default (no override) — sees Wehdah only.
        $this->actingAs($superAdmin);
        $this->withSession([])->get('/documents')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Documents/Index')
                ->where('documents.data.0.id', $wsDoc->id)
                ->etc()
            );

        // After switching, sees NCS only.
        $this->withSession(['active_company_id' => $ncs->id])->get('/documents')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Documents/Index')
                ->where('documents.data.0.id', $ncsDoc->id)
                ->etc()
            );
    }

    public function test_inertia_shared_props_include_active_company_and_available_companies(): void
    {
        $wehdah = Company::factory()->wehdah()->create();
        $ncs = Company::factory()->nasCeria()->create();
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'company_id' => $wehdah->id,
        ]);

        $response = $this->actingAs($superAdmin)->get('/documents');
        $response->assertOk()->assertInertia(fn ($p) => $p
            ->has('auth.active_company', fn ($a) => $a->where('code', 'WS')->etc())
            ->has('auth.available_companies', 2)
        );
    }
}
