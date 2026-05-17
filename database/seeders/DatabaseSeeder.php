<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Seed canonical companies
        $wehdah = Company::factory()->wehdah()->create();
        $nasCeria = Company::factory()->nasCeria()->create();
        $persada = Company::factory()->persada()->create();

        // Super admin — can see all companies
        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'super@example.com',
            'role' => 'super_admin',
            'company_id' => null,
        ]);

        // Wehdah admin
        User::factory()->create([
            'name' => 'Wehdah Admin',
            'email' => 'admin@wehdah.test',
            'role' => 'admin',
            'company_id' => $wehdah->id,
        ]);

        // Wehdah user
        User::factory()->create([
            'name' => 'Wehdah User',
            'email' => 'user@wehdah.test',
            'role' => 'user',
            'company_id' => $wehdah->id,
        ]);

        // Nas Ceria admin
        User::factory()->create([
            'name' => 'NAS Admin',
            'email' => 'admin@nasceria.test',
            'role' => 'admin',
            'company_id' => $nasCeria->id,
        ]);

        // Persada admin
        User::factory()->create([
            'name' => 'Persada Admin',
            'email' => 'admin@persada.test',
            'role' => 'admin',
            'company_id' => $persada->id,
        ]);
    }
}
