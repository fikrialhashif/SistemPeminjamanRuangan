<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSuspension
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isSuspended() && $request->is('*/bookings') && $request->isMethod('POST')) {
            $suspension = $user->suspensions()
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->first();

            return response()->json([
                'message' => 'Your account is suspended.',
                'suspension' => [
                    'reason' => $suspension->reason,
                    'ends_at' => $suspension->ends_at,
                ],
            ], 403);
        }

        return $next($request);
    }
}
