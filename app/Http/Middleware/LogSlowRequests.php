<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deja en el log los requests que tardan más del umbral (app.slow_request_ms).
 *
 * En hosting compartido las demoras no dejan rastro: el servidor responde 200
 * tarde y el service worker ya mostró "Sin conexión". Esto dice qué páginas se traban.
 */
class LogSlowRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        $response = $next($request);

        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($elapsedMs >= (int) config('app.slow_request_ms')) {
            Log::warning('slow request', [
                'method' => $request->method(),
                'path' => $request->path(),
                'ms' => $elapsedMs,
                'status' => $response->getStatusCode(),
                'tenant_id' => App::bound(Tenant::class) ? App::make(Tenant::class)->id : null,
            ]);
        }

        return $response;
    }
}
