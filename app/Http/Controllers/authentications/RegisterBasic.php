<?php

namespace App\Http\Controllers\authentications;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class RegisterBasic extends Controller
{
  public function index()
  {
    $pageConfigs = ['myLayout' => 'blank'];
    return view('auth.register', ['pageConfigs' => $pageConfigs]);
  }

  public function signup(Request $request)
  {

    // Check if the email already exists
    $existingUser = User::where('email', $request->email)->first();

    if ($existingUser) {
      return back()->withErrors(['email' => 'This email is already registered.']);
    }

    User::create([
      'name' => $request->name,
      'email' => $request->email,
      'password' => Hash::make($request->password),
    ]);

    return redirect()->route('login')->with('success', 'Signup successful, attempt to login now.');
  }
}
