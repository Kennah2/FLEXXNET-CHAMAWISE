<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChamaMembershipResource;
use App\Models\ChamaMembership;
use App\Models\Organization;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MemberController extends Controller
{
    public function index(Organization $organization): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [ChamaMembership::class, $organization]);

        $members = ChamaMembership::where('organization_id', $organization->id)
            ->with('user')
            ->orderBy('membership_number')
            ->get();

        return ChamaMembershipResource::collection($members);
    }
}
