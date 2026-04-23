<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive or pending approval.',
                'data' => null,
            ], 403);
        }

        $user->loadMissing('role');
        $role = $user->role?->slug ?? $user->role?->name;

        if (! $role) {
            return response()->json([
                'success' => false,
                'message' => 'No role is assigned to this account.',
                'data' => null,
            ], 403);
        }

        if ($role === 'manager' || in_array($role, $roles, true)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'You do not have permission to access this resource.',
            'data' => null,
        ], 403);
    }
}
