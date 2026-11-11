<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Models\ChamaMembership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Permission\PermissionRegistrar;

class ChamaController extends Controller
{
    /**
     * Every chama the authenticated user is currently an active member of, each annotated with
     * their roles in that specific chama — the same account can hold different roles in
     * different chamas (spatie team-scoped roles), so this can't be answered with a single
     * global role lookup.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $organizations = ChamaMembership::where('user_id', $request->user()->id)
            ->where('status', ChamaMembership::STATUS_ACTIVE)
            ->with('organization')
            ->get()
            ->map(fn (ChamaMembership $membership) => $this->withRoles($membership->organization, $request->user()));

        return OrganizationResource::collection($organizations);
    }

    public function show(Request $request, Organization $organization): OrganizationResource
    {
        $this->authorize('view', $organization);

        return new OrganizationResource($this->withRoles($organization, $request->user()));
    }

    private function withRoles(Organization $organization, User $user): Organization
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $organization->my_roles = User::find($user->id)->getRoleNames()->values()->all();

        return $organization;
    }
}
