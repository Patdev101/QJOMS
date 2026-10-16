<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** pages that are switched off for now answer "not found" */
class Disabled
{
    public function handle(Request $request, Closure $next)
    {
        abort(404);
    }
}
