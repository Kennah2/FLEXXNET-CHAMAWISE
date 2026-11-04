<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvitationRequest;
use App\Models\ChamaMembership;
use App\Models\Invitation;
use App\Models\Organization;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;

class InvitationController extends Controller
{
    public function store(
        StoreInvitationRequest $request,
        Organization $organization,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $plainToken = Invitation::generateToken();

        $invitation = Invitation::create([
            'organization_id' => $organization->id,
            'invited_by' => $request->user()->id,
            'email' => $request->validated('email'),
            'role' => $request->validated('role'),
            'token' => Invitation::hashToken($plainToken),
            'status' => Invitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $auditLogger->log(
            'invitation.created',
            $invitation,
            organizationId: $organization->id,
            after: $invitation->only(['email', 'role']),
        );

        // No transactional mail provider is wired up yet (Part 20 — notification channels are
        // a later phase). Until then the invite link is surfaced here for the inviter to share
        // manually, rather than silently claiming an email was sent when none was.
        $inviteUrl = route('invitations.show', $plainToken);

        return back()->with('status', 'Invitation created.')->with('invite_url', $inviteUrl);
    }

    public function destroy(Organization $organization, Invitation $invitation, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($invitation->organization_id === $organization->id, 404);
        $this->authorize('revoke', $invitation);

        $invitation->update(['status' => Invitation::STATUS_REVOKED]);

        $auditLogger->log('invitation.revoked', $invitation, organizationId: $organization->id);

        return back()->with('status', 'Invitation revoked.');
    }

    public function show(string $token): View|RedirectResponse
    {
        $invitation = Invitation::where('token', Invitation::hashToken($token))->first();

        if (! $invitation || ! $invitation->isPending()) {
            return redirect()->route('dashboard')->with('error', 'This invitation link is invalid or has expired.');
        }

        return view('invitations.show', ['invitation' => $invitation, 'token' => $token]);
    }

    public function accept(string $token, AuditLogger $auditLogger): RedirectResponse
    {
        $invitation = Invitation::where('token', Invitation::hashToken($token))->first();

        if (! $invitation || ! $invitation->isPending()) {
            return redirect()->route('dashboard')->with('error', 'This invitation link is invalid or has expired.');
        }

        $user = request()->user();

        if (mb_strtolower($user->email) !== mb_strtolower($invitation->email)) {
            return back()->with('error', 'This invitation was sent to a different email address.');
        }

        $organization = $invitation->organization;

        DB::transaction(function () use ($invitation, $organization, $user) {
            $membership = ChamaMembership::firstOrNew([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
            ]);

            if (! $membership->exists) {
                $membership->membership_number = ChamaMembership::nextMembershipNumber($organization->id);
            }

            $membership->status = ChamaMembership::STATUS_ACTIVE;
            $membership->joined_at ??= now();
            $membership->left_at = null;
            $membership->save();

            app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
            $user->assignRole($invitation->role);

            $invitation->update(['status' => Invitation::STATUS_ACCEPTED]);
        });

        $auditLogger->log('invitation.accepted', $invitation, organizationId: $organization->id, userId: $user->id);

        return redirect()->route('organizations.show', $organization)
            ->with('status', "Welcome to {$organization->name}!");
    }
}
