<?php

namespace App\CSPro\Community;

use App\Security\BackupVoter;
use App\Security\DashboardVoter;
use App\Security\LogsVoter;

/**
 * Permission catalogue for the Community layer.
 *
 * The upstream CSWeb 8.1 permission model is left untouched: it owns
 * ids 1..100 in `cspro_permissions`, grouped by tens (10 = apps,
 * 11 = apps.read, 12 = apps.write, ...). The Community layer claims ids
 * from 110 upwards, following the same convention, so that a future
 * upstream release can extend its own range without colliding.
 *
 *   110 dashboard      120 backup       130 logs
 *   111 dashboard.read 121 backup.read  131 logs.read
 *   112 dashboard.write 122 backup.write 132 logs.write
 *
 * Permission names reach Symfony through the upstream pipeline unchanged:
 * ApiKeyUserProvider::getUserRoles() maps every `cspro_permissions.name`
 * row of the user's role to 'ROLE_' . strtoupper(name), which the matching
 * voter then checks. No upstream file is modified to support this.
 */
final class CommunityPermissions {

    public const DASHBOARD_ALL = 110;
    public const DASHBOARD_READ = 111;
    public const DASHBOARD_WRITE = 112;

    public const BACKUP_ALL = 120;
    public const BACKUP_READ = 121;
    public const BACKUP_WRITE = 122;

    public const LOGS_ALL = 130;
    public const LOGS_READ = 131;
    public const LOGS_WRITE = 132;

    /**
     * The first id claimed by the Community layer. Upstream owns everything
     * below this value.
     */
    public const RANGE_START = 110;

    /**
     * id => permission name, as stored in `cspro_permissions`.
     */
    public const PERMISSIONS = [
        self::DASHBOARD_ALL => DashboardVoter::DASHBOARD_ALL,
        self::DASHBOARD_READ => DashboardVoter::DASHBOARD_READ,
        self::DASHBOARD_WRITE => DashboardVoter::DASHBOARD_WRITE,
        self::BACKUP_ALL => BackupVoter::BACKUP_ALL,
        self::BACKUP_READ => BackupVoter::BACKUP_READ,
        self::BACKUP_WRITE => BackupVoter::BACKUP_WRITE,
        self::LOGS_ALL => LogsVoter::LOGS_ALL,
        self::LOGS_READ => LogsVoter::LOGS_READ,
        self::LOGS_WRITE => LogsVoter::LOGS_WRITE,
    ];

    /**
     * Group permission => the granular permissions it implies. Mirrors
     * RolePermissions::GROUPS for the Community range.
     */
    public const GROUPS = [
        self::DASHBOARD_ALL => [self::DASHBOARD_READ, self::DASHBOARD_WRITE],
        self::BACKUP_ALL => [self::BACKUP_READ, self::BACKUP_WRITE],
        self::LOGS_ALL => [self::LOGS_READ, self::LOGS_WRITE],
    ];

    /**
     * Default grants per built-in role id, using the upstream role ids
     * (1 = Standard User, 2 = Administrator, 3 = Developer).
     *
     * Administrators get the full Community feature set. Developers get
     * read access to the dashboard and the logs — enough to diagnose a
     * breakout they deployed — but no backup rights and no clearing.
     * Standard Users get none of it.
     */
    public const DEFAULT_ROLE_GRANTS = [
        2 => [self::DASHBOARD_ALL, self::BACKUP_ALL, self::LOGS_ALL],
        3 => [self::DASHBOARD_READ, self::LOGS_READ],
    ];

    private function __construct() {
    }
}
