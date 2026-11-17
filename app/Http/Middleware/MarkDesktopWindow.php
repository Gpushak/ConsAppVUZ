<?php

namespace App\Http\Middleware;

use App\Support\DesktopWindow;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class MarkDesktopWindow
{
    public function handle(Request $request, Closure $next): Response
    {
        $desktop = DesktopWindow::requested($request);
        View::share('desktopWindow', $desktop);
        View::share('desktopDialog', $desktop && $request->routeIs(...DesktopWindow::DIALOG_ROUTES));

        return $next($request);
    }
}
