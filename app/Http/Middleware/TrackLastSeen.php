<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class TrackLastSeen
{
    private const THROTTLE_MINUTES = 15;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $this->shouldTrack($request)) {
            $cacheKey = "user:last-seen:{$user->id}";

            if (Cache::add($cacheKey, true, now()->addMinutes(self::THROTTLE_MINUTES))) {
                $user->forceFill(['last_seen_at' => now()])->saveQuietly();
            }
        }

        return $next($request);
    }

    private function shouldTrack(Request $request): bool
    {
        return $request->isMethod('GET')
            && $request->acceptsHtml()
            && ! $request->expectsJson();
    }
}
