<?php

namespace Database\Seeders;

use App\Enums\ResourceType;
use App\Models\Category;
use App\Models\Facility;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        collect(['Ruang rapat', 'Laboratorium', 'Aula'])
            ->each(fn (string $name) => Category::firstOrCreate(['name' => $name, 'type' => ResourceType::ROOM]));

        collect(['Kamera', 'Proyektor', 'Laptop'])
            ->each(fn (string $name) => Category::firstOrCreate(['name' => $name, 'type' => ResourceType::ASSET]));

        collect(['Pendingin Udara', 'Proyektor', 'Papan Tulis', 'Komputer'])
            ->each(fn (string $name) => Facility::firstOrCreate(['name' => $name]));
    }
}
