<?php

namespace App\Http\Middleware;

use App\Support\SmtpSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySmtpConfiguration
{
    public function handle(Request $request, Closure $next): Response
    {
        SmtpSettings::applySaved();

        return $next($request);
    }
}
