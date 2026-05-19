<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => fake()->unique()->lexify('???'),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'registration_number' => fake()->numerify('##########'),
            'is_active' => true,
        ];
    }

    public function wehdah(): static
    {
        return $this->state(fn () => [
            'name' => 'Wehdah Solution',
            'code' => 'WS',
            'address' => 'Wisma UOA II, Unit No: 15-13A,',
            'address_line_2' => 'UOA Business Centre, Jalan Pinang,',
            'city' => 'Kuala Lumpur',
            'state' => 'Wilayah Persekutuan',
            'postcode' => '50450',
            'country' => 'MY',
            'phone' => '+6017-3123415',
            'email' => 'wehdahsolution@gmail.com',
            'registration_number' => '202103190949 (PG0514579-H)',
            'brand_primary' => '#1a3a5c',
            'brand_secondary' => '#f0f4f8',
            'brand_accent' => '#16427a',
        ]);
    }

    public function nasCeria(): static
    {
        return $this->state(fn () => [
            'name' => 'Nas Ceria Services',
            'code' => 'NCS',
            'address' => '14-1, 1st Floor, Jalan Wangsa Budi 1, Taman Wangsa Melawati, 53300 Kuala Lumpur',
            'registration_number' => '003035718-X',
            'brand_primary' => '#1F3A5F',
            'brand_secondary' => '#F4ECD8',
            'brand_accent' => '#C0A062',
        ]);
    }

    public function persada(): static
    {
        return $this->state(fn () => [
            'name' => 'Persada Gemilang Global',
            'code' => 'PGG',
            'is_active' => true,
            'brand_primary' => '#5d3a9b',
            'brand_secondary' => '#efeaf7',
            'brand_accent' => '#3f2872',
        ]);
    }

    public function virtueDamsel(): static
    {
        return $this->state(fn () => [
            'name' => 'Virtue Damsel',
            'code' => 'VD',
            'is_active' => true,
            'brand_primary' => '#E67E22',
            'brand_secondary' => '#FBEEE6',
            'brand_accent' => '#16A085',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
