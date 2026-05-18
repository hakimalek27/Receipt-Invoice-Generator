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
            'address' => 'Unit No: 15-13A, Jalan Wangsa, Kuala Lumpur',
            'registration_number' => 'PG0514579-H',
        ]);
    }

    public function nasCeria(): static
    {
        return $this->state(fn () => [
            'name' => 'Nas Ceria Services',
            'code' => 'NCS',
            'address' => '14-1, 1st Floor, Jalan Wangsa Budi 1, Taman Wangsa Melawati, 53300 Kuala Lumpur',
            'registration_number' => '003035718-X',
        ]);
    }

    public function persada(): static
    {
        return $this->state(fn () => [
            'name' => 'Persada Gemilang Global',
            'code' => 'PGG',
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
