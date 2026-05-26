<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectApiDocs
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('docs.auth_enabled', true)) {
            return $next($request);
        }

        $username = (string) config('docs.username', '');
        $password = (string) config('docs.password', '');

        if ($username === '' || $password === '') {
            return $this->unauthorized('Documentacion no configurada');
        }

        if (
            hash_equals($username, (string) $request->getUser())
            && hash_equals($password, (string) $request->getPassword())
        ) {
            return $next($request);
        }

        return $this->unauthorized();
    }

    private function unauthorized(string $message = 'Autenticacion requerida'): Response
    {
        return response($message, 401, [
            'WWW-Authenticate' => 'Basic realm="API Documentation"',
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
