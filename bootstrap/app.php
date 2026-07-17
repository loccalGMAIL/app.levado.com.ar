<?php

use App\Http\Middleware\CheckTenantRole;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => SetTenantContext::class,
            'role' => CheckTenantRole::class,
            'super-admin' => EnsureSuperAdmin::class,
        ]);

        // El tenant debe quedar resuelto ANTES de que se resuelva el
        // route-model binding: así el global scope de BelongsToTenant aplica
        // también al binding y un recurso de otro tenant da 404 directo.
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            SetTenantContext::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
