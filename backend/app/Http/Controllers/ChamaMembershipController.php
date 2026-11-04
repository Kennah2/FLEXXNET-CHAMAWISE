<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMembershipStatusRequest;
use App\Models\ChamaMembership;
use App\Models\Organization;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ChamaMembershipController extends Controller
{
    public function index(Organization $organization): View
    {
        $this->authorize('viewAny', [ChamaMembership::class, $organization]);

        $memberships = $organization->memberships()
            ->with('user')
            ->orderBy('status')
            ->orderBy('membership_number')
            ->get();

        return view('organizations.members.index', compact('organization', 'memberships'));
    }

    public function updateStatus(
        UpdateMembershipStatusRequest $request,
        Organization $organization,
        ChamaMembership $membership,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        abort_unless($membership->organization_id === $organization->id, 404);

        $before = $membership->only('status');
        $newStatus = $request->validated('status');

        $membership->status = $newStatus;
        if ($newStatus !== ChamaMembership::STATUS_ACTIVE && blank($membership->left_at)) {
            $membership->left_at = now();
        } elseif ($newStatus === ChamaMembership::STATUS_ACTIVE) {
            $membership->left_at = null;
        }
        $membership->save();

        $auditLogger->log(
            'membership.status_changed',
            $membership,
            organizationId: $organization->id,
            before: $before,
            after: $membership->only('status'),
        );

        return back()->with('status', 'Membership status updated.');
    }
}
