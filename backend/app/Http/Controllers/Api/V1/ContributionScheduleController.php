<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContributionScheduleResource;
use App\Models\ContributionSchedule;
use App\Models\Organization;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContributionScheduleController extends Controller
{
    public function index(Organization $organization): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [ContributionSchedule::class, $organization]);

        $schedules = $organization->contributionSchedules()->orderByDesc('status')->orderBy('name')->get();

        return ContributionScheduleResource::collection($schedules);
    }

    public function show(Organization $organization, ContributionSchedule $schedule): ContributionScheduleResource
    {
        abort_unless($schedule->organization_id === $organization->id, 404);
        $this->authorize('view', $schedule);

        return new ContributionScheduleResource($schedule);
    }
}
