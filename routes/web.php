<?php

use App\Models\Company;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // return view('welcome');
    $companies = Company::all();
    dd($companies);
});
