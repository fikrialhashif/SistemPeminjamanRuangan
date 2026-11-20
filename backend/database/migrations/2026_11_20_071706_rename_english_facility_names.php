<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $masterRenames = [
        'AC' => 'Pendingin Udara',
        'Whiteboard' => 'Papan Tulis',
    ];

    /**
     * @var array<string, string>
     */
    private array $roomRenames = [
        'ac' => 'Pendingin Udara',
        'whiteboard' => 'Papan Tulis',
        'projector' => 'Proyektor',
        'computers' => 'Komputer',
        'computer' => 'Komputer',
    ];

    public function up(): void
    {
        $this->renameMasterFacilities($this->masterRenames);
        $this->normalizeRoomFacilities($this->roomRenames);
        $this->ensureRoomFacilitiesExistInMaster();
    }

    public function down(): void
    {
        $this->normalizeRoomFacilities(array_flip($this->roomRenames));
        $this->renameMasterFacilities(array_flip($this->masterRenames));
    }

    /**
     * @param  array<string, string>  $renames
     */
    private function renameMasterFacilities(array $renames): void
    {
        foreach ($renames as $from => $to) {
            $old = DB::table('facilities')->where('name', $from)->first();

            if (! $old) {
                continue;
            }

            $new = DB::table('facilities')->where('name', $to)->first();

            if ($new) {
                DB::table('facilities')->where('id', $old->id)->delete();

                continue;
            }

            DB::table('facilities')->where('id', $old->id)->update(['name' => $to]);
        }
    }

    /**
     * @param  array<string, string>  $renames
     */
    private function normalizeRoomFacilities(array $renames): void
    {
        $lookup = [];

        foreach ($renames as $from => $to) {
            $lookup[strtolower($from)] = $to;
        }

        DB::table('rooms')->select('id', 'facilities')->orderBy('id')->chunkById(100, function ($rooms) use ($lookup) {
            foreach ($rooms as $room) {
                $facilities = json_decode($room->facilities ?? '[]', true);

                if (! is_array($facilities)) {
                    continue;
                }

                $normalized = array_map(
                    fn (string $facility) => $lookup[strtolower($facility)] ?? $facility,
                    $facilities,
                );

                $normalized = array_values(array_unique($normalized));

                if ($normalized === $facilities) {
                    continue;
                }

                DB::table('rooms')->where('id', $room->id)->update([
                    'facilities' => json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }
        });
    }

    private function ensureRoomFacilitiesExistInMaster(): void
    {
        $now = now();

        DB::table('rooms')->select('facilities')->orderBy('id')->chunk(100, function ($rooms) use ($now) {
            foreach ($rooms as $room) {
                $facilities = json_decode($room->facilities ?? '[]', true);

                if (! is_array($facilities)) {
                    continue;
                }

                foreach (array_unique($facilities) as $name) {
                    DB::table('facilities')->insertOrIgnore([
                        'name' => $name,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }
};
