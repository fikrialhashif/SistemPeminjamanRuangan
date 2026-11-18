<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FaceChallengeTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_receives_a_server_bound_challenge(): void
    {
        Http::fake([
            '*/verify/challenge' => Http::response([
                'challenge_type' => 'turn',
                'instruction' => 'Tolehkan kepala ke kiri atau kanan',
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/face/challenge')
            ->assertOk()
            ->assertJsonPath('challenge_type', 'turn');

        $this->assertSame(
            'turn',
            Cache::get("face_challenge:{$user->id}"),
        );
    }
}
