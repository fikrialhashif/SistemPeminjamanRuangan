<?php

namespace Database\Factories;

use App\Enums\ResourceType;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'type' => fake()->randomElement([ResourceType::ROOM, ResourceType::ASSET]),
        ];
    }
}
