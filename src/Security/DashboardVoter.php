<?php

namespace App\Security;

use App\CSPro\User\User;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Psr\Log\LoggerInterface;

/**
 * Voter for the Community breakout dashboard.
 *
 * Community layer addition. Follows the same shape as the upstream voters
 * (RolesVoter, SettingsVoter, ...) so that dashboard permissions are resolved
 * through the standard CSWeb 8.1 permission pipeline: a `dashboard.*` row in
 * `cspro_permissions` is turned into a `ROLE_DASHBOARD_*` string by
 * ApiKeyUserProvider::getUserRoles(), which this voter then checks.
 */
class DashboardVoter extends Voter {

    public const DASHBOARD_ALL = 'dashboard';
    public const DASHBOARD_READ = 'dashboard.read';
    public const DASHBOARD_WRITE = 'dashboard.write';

    public function __construct(private Security $security, private LoggerInterface $logger) {
        $this->security = $security;
    }

    protected function supports($attribute, $subject): bool {
        if (!in_array($attribute, [self::DASHBOARD_ALL, self::DASHBOARD_READ, self::DASHBOARD_WRITE])) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool {
        $user = $token->getUser();

        if (!$user instanceof User) {
            // the user must be logged in; if not, deny access
            return false;
        }
        return match ($attribute) {
            self::DASHBOARD_READ => $this->canReadDashboard($user, $attribute),
            self::DASHBOARD_WRITE => $this->canWriteDashboard($user, $attribute),
            default => throw new \LogicException('This code should not be reached!'),
        };
    }

    private function canReadDashboard(User $user, $attribute) {
        if ($this->security->isGranted('ROLE_' . strtoupper(self::DASHBOARD_ALL)) || $this->security->isGranted('ROLE_' . strtoupper(self::DASHBOARD_READ))) {
            return true;
        } else {
            $username = $user->getUsername();
            $this->logger->debug("User {$username} does not have dashboard.read permission");
            return false;
        }
    }

    private function canWriteDashboard(User $user, $attribute) {
        if ($this->security->isGranted('ROLE_' . strtoupper(self::DASHBOARD_ALL)) || $this->security->isGranted('ROLE_' . strtoupper(self::DASHBOARD_WRITE))) {
            return true;
        } else {
            $username = $user->getUsername();
            $this->logger->debug("User {$username} does not have dashboard.write permission");
            return false;
        }
    }
}
