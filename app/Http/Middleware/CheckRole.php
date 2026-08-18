<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (! $request->user()) {
            return redirect('login');
        }

        // Additional check for active status (just in case they were deactivated mid-session)
        if ($request->user()->status !== 'active') {
            auth()->guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect('login')->withErrors(['email' => 'Akun Anda berstatus nonaktif. Silakan hubungi Administrator Kominfo.']);
        }

        if (! in_array($request->user()->role, $roles)) {
            abort(403, 'Anda tidak memiliki hak akses untuk halaman ini.');
        }

        return $next($request);
    }
}

