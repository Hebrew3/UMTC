<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'application' => 'UMTC Portfolio System',
        'status' => 'API ready',
    ]);
});
