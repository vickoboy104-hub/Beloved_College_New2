<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();

        if ($user && (! $user->last_seen_at || $user->last_seen_at->lt(now()->subMinutes(5)))) {
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $response;
    }
}
