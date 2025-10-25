<?php

use Illuminate\Support\Facades\Route;
use App\Models\Company;

Route::get('/', function () {
    // return view('welcome');
    $companies = Company::all();
    dd($companies);
});
