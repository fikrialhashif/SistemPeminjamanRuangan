<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('location_id')->constrained('categories')->nullOnDelete();
        });

        $now = now();

        $legacyNames = [
            'room' => ['meeting_room' => 'Ruang rapat', 'lab' => 'Laboratorium'],
            'asset' => ['electronics' => 'Elektronik'],
        ];

        $legacyCategories = DB::table('resources')
            ->select('type', 'category')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->get();

        foreach ($legacyCategories as $legacy) {
            $name = $legacyNames[$legacy->type][$legacy->category] ?? $legacy->category;

            $categoryId = DB::table('categories')->insertGetId([
                'name' => $name,
                'type' => $legacy->type,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('resources')
                ->where('type', $legacy->type)
                ->where('category', $legacy->category)
                ->update(['category_id' => $categoryId]);
        }

        Schema::table('resources', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->string('category')->nullable()->after('location_id');
        });

        $resources = DB::table('resources')
            ->whereNotNull('category_id')
            ->get(['id', 'category_id']);

        foreach ($resources as $resource) {
            $name = DB::table('categories')->where('id', $resource->category_id)->value('name');

            DB::table('resources')->where('id', $resource->id)->update(['category' => $name]);
        }

        Schema::table('resources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
