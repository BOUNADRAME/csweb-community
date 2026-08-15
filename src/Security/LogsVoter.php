<?php

namespace App\Security;

use App\CSPro\User\User;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Psr\Log\LoggerInterface;

/**
 * Voter for the Community application logs viewer.
 *
 * Community layer addition. Follows the same shape as the upstream voters
 * (RolesVoter, SettingsVoter, ...) so that logs permissions are resolved
 * through the standard CSWeb 8.1 permission pipeline: a `logs.*` row in
 * `cspro_permissions` is turned into a `ROLE_LOGS_*` string by
 * ApiKeyUserProvider::getUserRoles(), which this voter then checks.
 */
class LogsVoter extends Voter {

    public const LOGS_ALL = 'logs';
    public const LOGS_READ = 'logs.read';
    public const LOGS_WRITE = 'logs.write';

    public function __construct(private Security $security, private LoggerInterface $logger) {
        $this->security = $security;
    }

    protected function supports($attribute, $subject): bool {
        if (!in_array($attribute, [self::LOGS_ALL, self::LOGS_READ, self::LOGS_WRITE])) {
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
            self::LOGS_READ => $this->canReadLogs($user, $attribute),
            self::LOGS_WRITE => $this->canWriteLogs($user, $attribute),
            default => throw new \LogicException('This code should not be reached!'),
        };
    }

    private function canReadLogs(User $user, $attribute) {
        if ($this->security->isGranted('ROLE_' . strtoupper(self::LOGS_ALL)) || $this->security->isGranted('ROLE_' . strtoupper(self::LOGS_READ))) {
            return true;
        } else {
            $username = $user->getUsername();
            $this->logger->debug("User {$username} does not have logs.read permission");
            return false;
        }
    }

    private function canWriteLogs(User $user, $attribute) {
        if ($this->security->isGranted('ROLE_' . strtoupper(self::LOGS_ALL)) || $this->security->isGranted('ROLE_' . strtoupper(self::LOGS_WRITE))) {
            return true;
        } else {
            $username = $user->getUsername();
            $this->logger->debug("User {$username} does not have logs.write permission");
            return false;
        }
    }
}
