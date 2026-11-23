<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back()->with('success', 'Nama berhasil diperbarui.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! Hash::check($request->validated('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Password saat ini tidak sesuai.',
            ]);
        }

        $user->update(['password' => $request->validated('password')]);
        $request->session()->regenerate();

        return back()->with('success', 'Password berhasil diperbarui.');
    }
}
