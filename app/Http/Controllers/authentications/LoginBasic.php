<?php

namespace App\Http\Controllers\authentications;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginBasic extends Controller
{
  public function index()
  {
    $pageConfigs = ['myLayout' => 'blank'];
    return view('auth.login', ['pageConfigs' => $pageConfigs]);
  }


  public function signin(Request $request)
  {
    $credentials = $request->only('email', 'password');

    $this->validate($request, [
      'email' => 'required|string|email',
      'password' => 'required|string',
    ]);

    if (Auth::attempt($credentials, $request->filled('remember'))) {
      $request->session()->regenerate();

      return redirect()->route('home');
    }

    return back()->withErrors([
      'email' => 'The provided credentials do not match our records.',
    ]);
  }


  public function logout(Request $request)
  {
    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/');
  }
}
