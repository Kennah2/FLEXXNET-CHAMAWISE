<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContributionRequest;
use App\Http\Resources\ContributionObligationResource;
use App\Http\Resources\ContributionResource;
use App\Models\Contribution;
use App\Models\ContributionObligation;
use App\Models\ContributionSchedule;
use App\Models\Organization;
use App\Models\User;
use App\Services\Contributions\ContributionService;
use App\Services\Contributions\ObligationGenerator;
use App\Support\ChamaPermissions;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

class ContributionController extends Controller
{
    /**
     * Mirrors the web contributions page's data (Part 15 — same backend, same rules, a
     * mobile-shaped response instead of a Blade view): the member's own obligations and payment
     * history, plus — for an official — the chama's pending-verification queue.
     */
    public function index(Organization $organization, ObligationGenerator $generator): JsonResponse
    {
        $this->authorize('viewAny', [ContributionSchedule::class, $organization]);

        $user = request()->user();

        $activeSchedules = $organization->contributionSchedules()->where('status', ContributionSchedule::STATUS_ACTIVE)->get();
        foreach ($activeSchedules as $schedule) {
            $generator->generate($schedule, CarbonImmutable::now());
        }

        $myObligations = ContributionObligation::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->with('schedule')
            ->orderByDesc('period_start')
            ->limit(30)
            ->get();

        $myPayments = Contribution::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->orderByDesc('paid_at')
            ->limit(20)
            ->get();

        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $canManageFinances = User::find($user->id)->can(ChamaPermissions::MANAGE_FINANCES);

        $pendingVerifications = collect();
        if ($canManageFinances) {
            $pendingVerifications = Contribution::query()
                ->where('organization_id', $organization->id)
                ->where('status', Contribution::STATUS_PENDING_VERIFICATION)
                ->with('user')
                ->orderBy('paid_at')
                ->get();
        }

        $myOutstandingObligations = ContributionObligation::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->whereIn('status', [ContributionObligation::STATUS_PENDING, ContributionObligation::STATUS_PARTIAL, ContributionObligation::STATUS_OVERDUE])
            ->get();

        return response()->json([
            'data' => [
                'outstanding_cents' => $myOutstandingObligations->sum(fn (ContributionObligation $o) => $o->outstandingCents()),
                'outstanding_formatted' => Money::format($myOutstandingObligations->sum(fn (ContributionObligation $o) => $o->outstandingCents()), $organization->currency),
                'obligations' => ContributionObligationResource::collection($myObligations),
                'payments' => ContributionResource::collection($myPayments),
                'pending_verifications' => ContributionResource::collection($pendingVerifications->loadMissing('user')),
            ],
        ]);
    }

    public function store(StoreContributionRequest $request, Organization $organization, ContributionService $service): JsonResponse
    {
        $forUserId = (int) ($request->validated('user_id') ?: $request->user()->id);
        $scheduleId = $request->validated('contribution_schedule_id');

        $contribution = $service->record(
            $organization,
            $forUserId,
            $scheduleId !== null ? (int) $scheduleId : null,
            Money::parseToCents($request->validated('amount')),
            $request->validated('payment_method'),
            $request->validated('transaction_reference'),
            $request->validated('paid_at'),
            $request->validated('notes'),
            $request->user(),
        );

        return response()->json(['data' => new ContributionResource($contribution)], 201);
    }

    public function verify(Request $request, Organization $organization, Contribution $contribution, ContributionService $service): JsonResponse
    {
        abort_unless($contribution->organization_id === $organization->id, 404);
        $this->authorize('verify', $contribution);

        $validated = $request->validate([
            'financial_account_id' => ['required', 'integer'],
        ]);

        $contribution = $service->verify($organization, $contribution, (int) $validated['financial_account_id'], $request->user());

        return response()->json(['data' => new ContributionResource($contribution)]);
    }

    public function reject(Organization $organization, Contribution $contribution, ContributionService $service): JsonResponse
    {
        abort_unless($contribution->organization_id === $organization->id, 404);
        $this->authorize('reject', $contribution);

        $contribution = $service->reject($organization, $contribution, request()->user());

        return response()->json(['data' => new ContributionResource($contribution)]);
    }

    public function reverse(Organization $organization, Contribution $contribution, ContributionService $service): JsonResponse
    {
        abort_unless($contribution->organization_id === $organization->id, 404);
        $this->authorize('reverse', $contribution);

        $contribution = $service->reverse($organization, $contribution, request()->user());

        return response()->json(['data' => new ContributionResource($contribution)]);
    }
}
