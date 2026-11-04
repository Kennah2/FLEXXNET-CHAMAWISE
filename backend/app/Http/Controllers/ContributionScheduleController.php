<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContributionScheduleRequest;
use App\Models\ContributionSchedule;
use App\Models\Organization;
use App\Services\AuditLogger;
use App\Services\Contributions\ObligationGenerator;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ContributionScheduleController extends Controller
{
    public function index(Organization $organization): View
    {
        $this->authorize('viewAny', [ContributionSchedule::class, $organization]);

        $schedules = $organization->contributionSchedules()->orderByDesc('created_at')->get();

        return view('organizations.contributions.schedules.index', compact('organization', 'schedules'));
    }

    public function create(Organization $organization): View
    {
        $this->authorize('create', [ContributionSchedule::class, $organization]);

        $members = $organization->members()->orderBy('name')->get();

        return view('organizations.contributions.schedules.create', compact('organization', 'members'));
    }

    public function store(
        StoreContributionScheduleRequest $request,
        Organization $organization,
        ObligationGenerator $generator,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $schedule = DB::transaction(function () use ($request, $organization) {
            $schedule = ContributionSchedule::create([
                'organization_id' => $organization->id,
                'name' => $request->validated('name'),
                'description' => $request->validated('description'),
                'frequency' => $request->validated('frequency'),
                'currency' => $organization->currency,
                'amount_cents' => Money::parseToCents($request->validated('amount')),
                'start_date' => $request->validated('start_date'),
                'end_date' => $request->validated('end_date'),
                'deadline_time' => $request->validated('deadline_time'),
                'timezone' => 'Africa/Nairobi',
                'grace_period_days' => $request->validated('grace_period_days'),
                'penalty_amount_cents' => Money::parseToCents($request->validated('penalty_amount') ?? '0'),
                'applies_to' => $request->validated('applies_to'),
                'status' => ContributionSchedule::STATUS_ACTIVE,
                'created_by' => $request->user()->id,
            ]);

            if ($schedule->applies_to === ContributionSchedule::APPLIES_TO_SELECTED) {
                $schedule->selectedMembers()->sync($request->validated('member_ids', []));
            }

            return $schedule;
        });

        $generator->generate($schedule, CarbonImmutable::now());

        $auditLogger->log(
            'contribution_schedule.created',
            $schedule,
            organizationId: $organization->id,
            after: $schedule->only(['name', 'frequency', 'amount_cents', 'start_date']),
        );

        return redirect()->route('organizations.contributions.schedules.show', [$organization, $schedule])
            ->with('status', "Contribution schedule \"{$schedule->name}\" created.");
    }

    public function show(Organization $organization, ContributionSchedule $schedule, ObligationGenerator $generator): View
    {
        abort_unless($schedule->organization_id === $organization->id, 404);
        $this->authorize('view', $schedule);

        $generator->generate($schedule, CarbonImmutable::now());

        $obligations = $schedule->obligations()->with('user')->orderByDesc('period_start')->paginate(30);

        $memberSummaries = $schedule->applicableMembers()->map(function ($member) use ($schedule) {
            $memberObligations = $schedule->obligations()->where('user_id', $member->id)->get();

            return [
                'user' => $member,
                'expected' => $memberObligations->sum('amount_due_cents'),
                'paid' => $memberObligations->sum('amount_paid_cents'),
                'overdue_count' => $memberObligations->where('status', 'overdue')->count(),
            ];
        });

        return view('organizations.contributions.schedules.show', compact('organization', 'schedule', 'obligations', 'memberSummaries'));
    }
}
