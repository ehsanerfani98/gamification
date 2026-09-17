<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    /** @var array<int, class-string<Seeder>> */
    protected $seeders = [
        GameSeeder::class,
        PlanSeeder::class,
    ];

    public function run(): void
    {
        foreach ($this->seeders as $seeder) {
            $this->call($seeder);
        }
    }
}
