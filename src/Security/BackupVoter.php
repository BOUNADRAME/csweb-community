<?php

namespace App\Security;

use App\CSPro\User\User;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Psr\Log\LoggerInterface;

/**
 * Voter for the Community backup feature.
 *
 * Community layer addition. Follows the same shape as the upstream voters
 * (RolesVoter, SettingsVoter, ...) so that backup permissions are resolved
 * through the standard CSWeb 8.1 permission pipeline: a `backup.*` row in
 * `cspro_permissions` is turned into a `ROLE_BACKUP_*` string by
 * ApiKeyUserProvider::getUserRoles(), which this voter then checks.
 */
class BackupVoter extends Voter {

    public const BACKUP_ALL = 'backup';
    public const BACKUP_READ = 'backup.read';
    public const BACKUP_WRITE = 'backup.write';

    public function __construct(private Security $security, private LoggerInterface $logger) {
        $this->security = $security;
    }

    protected function supports($attribute, $subject): bool {
        if (!in_array($attribute, [self::BACKUP_ALL, self::BACKUP_READ, self::BACKUP_WRITE])) {
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
            self::BACKUP_READ => $this->canReadBackup($user, $attribute),
            self::BACKUP_WRITE => $this->canWriteBackup($user, $attribute),
            default => throw new \LogicException('This code should not be reached!'),
        };
    }

    private function canReadBackup(User $user, $attribute) {
        if ($this->security->isGranted('ROLE_' . strtoupper(self::BACKUP_ALL)) || $this->security->isGranted('ROLE_' . strtoupper(self::BACKUP_READ))) {
            return true;
        } else {
            $username = $user->getUsername();
            $this->logger->debug("User {$username} does not have backup.read permission");
            return false;
        }
    }

    private function canWriteBackup(User $user, $attribute) {
        if ($this->security->isGranted('ROLE_' . strtoupper(self::BACKUP_ALL)) || $this->security->isGranted('ROLE_' . strtoupper(self::BACKUP_WRITE))) {
            return true;
        } else {
            $username = $user->getUsername();
            $this->logger->debug("User {$username} does not have backup.write permission");
            return false;
        }
    }
}
