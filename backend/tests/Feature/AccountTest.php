<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_account_button_opens_the_account_dialog(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-open-dialog="accountDialog"', false)
            ->assertSee('id="accountDialog"', false);
    }

    public function test_name_can_be_updated_from_the_account_dialog(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.profile.update'), [
                'account_popup' => '1',
                'name' => 'Nama Baru',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Nama berhasil diperbarui.');

        $this->assertSame('Nama Baru', $user->fresh()->name);
    }

    public function test_name_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.profile.update'), [
                'account_popup' => '1',
                'name' => '',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('name')
            ->assertSessionHasInput('account_popup', '1');
    }

    public function test_password_can_be_changed_from_the_account_dialog(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.password.update'), [
                'account_popup' => '1',
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Password berhasil diperbarui.');

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.password.update'), [
                'account_popup' => '1',
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('current_password')
            ->assertSessionHasInput('account_popup', '1');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_new_password_must_be_confirmed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.password.update'), [
                'account_popup' => '1',
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'different-password',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('password')
            ->assertSessionHasInput('account_popup', '1');
    }

    public function test_new_password_must_be_at_least_eight_characters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.password.update'), [
                'account_popup' => '1',
                'current_password' => 'password',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('password')
            ->assertSessionHasInput('account_popup', '1');
    }

    public function test_guest_cannot_update_the_account(): void
    {
        $this->put(route('admin.profile.update'), ['name' => 'Nama Baru'])
            ->assertRedirect(route('admin.login'));
    }
}
