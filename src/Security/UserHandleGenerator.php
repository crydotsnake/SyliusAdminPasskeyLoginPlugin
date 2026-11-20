<?php

declare(strict_types=1);

namespace SKrull\SyliusAdminPasskeyLoginPlugin\Security;

use Sylius\Component\Core\Model\AdminUserInterface;

final class UserHandleGenerator
{
    public function __construct(
        #[\SensitiveParameter]
        private readonly string $secret,
    ) {
    }

    public function generate(AdminUserInterface $adminUser): string
    {
        $id = $adminUser->getId();
        if (!is_int($id) && !is_string($id)) {
            throw new \LogicException('The admin user must be persisted before a user handle can be generated.');
        }

        return hash_hmac('sha256', 'admin_user:' . $id, $this->secret);
    }
}
