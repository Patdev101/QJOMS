<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

/** the signed-in user's own account: change password */
class AccountController extends Controller
{
    public function editPassword()
    {
        return Inertia::render('Account/Password');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)],
        ], [
            'current_password.current_password' => 'The current password is not correct.',
            'password.different' => 'The new password must be different from the current one.',
            'password.confirmed' => 'The new passwords do not match.',
        ]);

        $request->user()->update(['password' => $data['password']]); // hashed by the User model
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Password changed.');
    }
}
