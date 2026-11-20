<?php

declare(strict_types=1);

namespace SKrull\SyliusAdminPasskeyLoginPlugin\Checker;

use SKrull\SyliusAdminPasskeyLoginPlugin\Repository\AdminPasskeyRepository;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Contracts\Service\ResetInterface;

final class AdminPasskeyChecker implements ResetInterface
{
    /** @var array<int, true>|null */
    private ?array $adminUserIdsWithPasskey = null;

    public function __construct(
        private readonly AdminPasskeyRepository $adminPasskeyRepository,
    ) {
    }

    public function hasPasskey(AdminUserInterface $adminUser): bool
    {
        if (null === $this->adminUserIdsWithPasskey) {
            $this->adminUserIdsWithPasskey = array_fill_keys($this->adminPasskeyRepository->findAdminUserIdsWithPasskey(), true);
        }

        $id = $adminUser->getId();

        return (is_int($id) || is_string($id)) && isset($this->adminUserIdsWithPasskey[$id]);
    }

    public function reset(): void
    {
        $this->adminUserIdsWithPasskey = null;
    }
}
