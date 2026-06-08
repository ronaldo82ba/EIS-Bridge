<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/admin/{any?}', 'admin')->where('any', '.*');
