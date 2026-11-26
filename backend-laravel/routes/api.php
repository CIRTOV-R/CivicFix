<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CrewController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\IncidentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:120,1')->group(function (): void {
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::get('categories', [CategoryController::class, 'index']);

        Route::middleware('idempotent')->post('incidents', [IncidentController::class, 'store']);
        Route::get('incidents', [IncidentController::class, 'index']);
        Route::get('incidents/{incident}', [IncidentController::class, 'show'])->whereNumber('incident');

        Route::post('incidents/{incident}/assign', [IncidentController::class, 'assign'])
            ->whereNumber('incident')
            ->middleware('role:SUPERVISOR|ADMINISTRADOR');
        Route::post('incidents/{incident}/start', [IncidentController::class, 'start'])->whereNumber('incident');
        Route::post('incidents/{incident}/resolve', [IncidentController::class, 'resolve'])->whereNumber('incident');
        Route::post('incidents/{incident}/reject', [IncidentController::class, 'reject'])
            ->whereNumber('incident')
            ->middleware('role:SUPERVISOR|ADMINISTRADOR');

        Route::get('crews', [CrewController::class, 'index'])
            ->middleware('role:CUADRILLA|SUPERVISOR|ADMINISTRADOR');
        Route::patch('crews/{crew}/location', [CrewController::class, 'updateLocation'])
            ->whereNumber('crew')
            ->middleware('role:CUADRILLA|SUPERVISOR|ADMINISTRADOR');

        Route::get('dashboard', [DashboardController::class, 'index'])
            ->middleware('role:SUPERVISOR|ADMINISTRADOR');
        Route::get('audit-logs', [AuditLogController::class, 'index'])
            ->middleware('role:ADMINISTRADOR');
    });
});
