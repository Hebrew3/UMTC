<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FieldworkController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/csrf-token', [AuthController::class, 'csrfToken']);
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

    Route::middleware(['auth', 'active'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/dashboard/stats', [MonitoringController::class, 'dashboard']);
        Route::get('/monitoring/no-updates', [MonitoringController::class, 'noUpdates']);
        Route::get('/monitoring/agent-by-cluster', [MonitoringController::class, 'agentByCluster']);
        Route::get('/monitoring/areas', [MonitoringController::class, 'areaMonitoring']);
        Route::get('/monitoring/filters', [MonitoringController::class, 'filters']);
        Route::get('/reports', [MonitoringController::class, 'reports']);
        Route::get('/locations/resolve', [LocationController::class, 'resolve']);

        Route::get('/portfolios', [PortfolioController::class, 'index']);
        Route::get('/portfolios/export', [PortfolioController::class, 'export']);
        Route::get('/portfolios/{portfolio}', [PortfolioController::class, 'show']);
        Route::get('/agents', [AgentController::class, 'index']);
        Route::get('/fieldwork', [FieldworkController::class, 'index']);
        Route::post('/fieldwork', [FieldworkController::class, 'store'])->middleware('role:admin,senior_agent');
        Route::put('/fieldwork/{schedule}', [FieldworkController::class, 'update'])->middleware('role:admin,senior_agent');
        Route::delete('/fieldwork/{schedule}', [FieldworkController::class, 'destroy'])->middleware('role:admin,senior_agent');

        Route::middleware('role:admin')->group(function () {
            Route::post('/portfolios', [PortfolioController::class, 'store']);
            Route::get('/imports', [ImportController::class, 'index']);
            Route::post('/imports/preview', [ImportController::class, 'preview']);
            Route::post('/imports/{import}/confirm', [ImportController::class, 'confirm']);
            Route::post('/agents', [AgentController::class, 'store']);
            Route::put('/agents/{agent}', [AgentController::class, 'update']);
            Route::get('/users', [UserController::class, 'index']);
            Route::post('/users', [UserController::class, 'store']);
            Route::put('/users/{user}/agents', [UserController::class, 'assignAgents']);
        });
    });
});
