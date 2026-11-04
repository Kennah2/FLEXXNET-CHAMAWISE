<?php

namespace App\Support;

/**
 * The platform-wide catalog of permission names a chama's roles can be built from (Part 10).
 * This is the fixed vocabulary of *capabilities*; which role gets which capability, and what
 * extra custom roles a chama defines, is configurable per chama — see ChamaRoleProvisioner.
 */
final class ChamaPermissions
{
    public const MANAGE_SETTINGS = 'chama.manage-settings';

    public const MANAGE_MEMBERS = 'chama.manage-members';

    public const MANAGE_OFFICIALS = 'chama.manage-officials';

    public const MANAGE_FINANCES = 'chama.manage-finances';

    public const MANAGE_MEETINGS = 'chama.manage-meetings';

    public const VIEW_REPORTS = 'chama.view-reports';

    public const VIEW_AUDIT_LOG = 'chama.view-audit-log';

    public const ALL = [
        self::MANAGE_SETTINGS,
        self::MANAGE_MEMBERS,
        self::MANAGE_OFFICIALS,
        self::MANAGE_FINANCES,
        self::MANAGE_MEETINGS,
        self::VIEW_REPORTS,
        self::VIEW_AUDIT_LOG,
    ];

    /**
     * Default role => permissions mapping used to provision a brand-new chama's roles
     * (Part 10's suggested defaults). A chama can rename roles, add custom ones, or adjust
     * permissions afterwards through authorized workflows — this is only the starting point.
     */
    public const DEFAULT_ROLE_PERMISSIONS = [
        'chairperson' => [
            self::MANAGE_SETTINGS,
            self::MANAGE_MEMBERS,
            self::MANAGE_OFFICIALS,
            self::MANAGE_FINANCES,
            self::MANAGE_MEETINGS,
            self::VIEW_REPORTS,
            self::VIEW_AUDIT_LOG,
        ],
        'treasurer' => [
            self::MANAGE_FINANCES,
            self::VIEW_REPORTS,
            self::VIEW_AUDIT_LOG,
        ],
        'secretary' => [
            self::MANAGE_MEMBERS,
            self::MANAGE_MEETINGS,
            self::VIEW_REPORTS,
        ],
        'member' => [],
        'auditor' => [
            self::VIEW_REPORTS,
            self::VIEW_AUDIT_LOG,
        ],
    ];
}
