<?php
use Illuminate\Support\Facades\Route;

include "template.php";

//Test
Route::get('/home', function () {
  return view('pages/ambulance');
});
