<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuditUserActions
{
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();
        $response = $next($request);

        if (! $actor || $request->isMethodSafe()) {
            return $response;
        }

        try {
            if (! Schema::hasTable('audit_logs')) {
                return $response;
            }

            $subject = collect($request->route()?->parameters() ?? [])
                ->first(fn (mixed $parameter) => $parameter instanceof Model);

            AuditLog::query()->create([
                'user_id' => $actor->getKey(),
                'route' => $request->route()?->getName(),
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
                'action' => $request->route()?->getActionMethod(),
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'status_code' => $response->getStatusCode(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                'metadata' => [
                    'successful' => $response->getStatusCode() < 400,
                ],
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $response;
    }
}
