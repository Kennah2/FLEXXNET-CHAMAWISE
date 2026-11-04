<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the chama ("team", in spatie/laravel-permission terms) that the current request is
 * scoped to, from the route-bound {organization}, and makes it the active context for every
 * role/permission check made during this request.
 *
 * This middleware only sets *context* — it grants nothing by itself. Every controller action
 * still runs its own policy check, which re-verifies the authenticated user's active membership
 * in that specific organization before allowing anything (see docs/TENANCY.md).
 */
class SetPermissionsTeamFromOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->route('organization');

        if ($organization instanceof Organization) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        }

        return $next($request);
    }
}
