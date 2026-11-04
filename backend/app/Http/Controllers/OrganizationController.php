<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationRequest;
use App\Models\ChamaMembership;
use App\Models\Contribution;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ChamaRoleProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;

class OrganizationController extends Controller
{
    public function index(): View
    {
        $organizations = request()->user()->organizations()->orderBy('name')->get();

        return view('organizations.index', compact('organizations'));
    }

    public function create(): View
    {
        $this->authorize('create', Organization::class);

        return view('organizations.create');
    }

    public function store(
        StoreOrganizationRequest $request,
        ChamaRoleProvisioner $provisioner,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $organization = DB::transaction(function () use ($request, $provisioner) {
            $organization = Organization::create([
                ...$request->validated(),
                'currency' => $request->validated('currency') ?? 'KES',
                'status' => Organization::STATUS_ACTIVE,
                'created_by' => $request->user()->id,
            ]);

            $provisioner->provision($organization, $request->user(), 'chairperson');

            ChamaMembership::create([
                'organization_id' => $organization->id,
                'user_id' => $request->user()->id,
                'membership_number' => ChamaMembership::nextMembershipNumber($organization->id),
                'status' => ChamaMembership::STATUS_ACTIVE,
                'joined_at' => now(),
            ]);

            return $organization;
        });

        $auditLogger->log('chama.created', $organization, organizationId: $organization->id, after: $organization->only(['name', 'group_type', 'currency']));

        return redirect()->route('organizations.show', $organization)
            ->with('status', "{$organization->name} has been created.");
    }

    public function show(Organization $organization): View
    {
        $this->authorize('view', $organization);

        $organization->load(['members' => fn ($query) => $query->orderBy('name')]);

        $pendingInvitationsCount = $organization->invitations()
            ->where('status', Invitation::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->count();

        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $currentUserRole = User::find(request()->user()->id)->getRoleNames()->first();

        $hasContributionSchedules = $organization->contributionSchedules()->exists();
        $totalVerifiedContributionsCents = $organization->contributions()
            ->where('status', Contribution::STATUS_VERIFIED)
            ->sum('amount_cents');

        return view('organizations.show', compact(
            'organization',
            'pendingInvitationsCount',
            'currentUserRole',
            'hasContributionSchedules',
            'totalVerifiedContributionsCents',
        ));
    }
}
