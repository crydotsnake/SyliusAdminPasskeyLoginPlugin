<?php

declare(strict_types=1);

namespace SKrull\SyliusAdminPasskeyLoginPlugin\Repository;

use SKrull\SyliusAdminPasskeyLoginPlugin\Security\UserHandleGenerator;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Canonicalizer\CanonicalizerInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Webauthn\Bundle\Repository\PublicKeyCredentialUserEntityRepositoryInterface;
use Webauthn\PublicKeyCredentialUserEntity;

final class AdminPasskeyUserEntityRepository implements PublicKeyCredentialUserEntityRepositoryInterface
{
    /** @param UserRepositoryInterface<AdminUserInterface> $adminUserRepository */
    public function __construct(
        private readonly UserRepositoryInterface $adminUserRepository,
        private readonly AdminPasskeyRepository $adminPasskeyRepository,
        private readonly UserHandleGenerator $userHandleGenerator,
        private readonly CanonicalizerInterface $canonicalizer,
    ) {
    }

    public function findOneByUsername(string $username): ?PublicKeyCredentialUserEntity
    {
        $adminUser = filter_var($username, \FILTER_VALIDATE_EMAIL)
            ? $this->adminUserRepository->findOneByEmail($username)
            : $this->adminUserRepository->findOneBy(['usernameCanonical' => $this->canonicalizer->canonicalize($username)]);

        return $adminUser instanceof AdminUserInterface ? $this->createUserEntity($adminUser) : null;
    }

    public function findOneByUserHandle(string $userHandle): ?PublicKeyCredentialUserEntity
    {
        $passkey = $this->adminPasskeyRepository->findOneBy(['userHandle' => $userHandle]);

        return null !== $passkey ? $this->createUserEntity($passkey->getAdminUser()) : null;
    }

    private function createUserEntity(AdminUserInterface $adminUser): PublicKeyCredentialUserEntity
    {
        $displayName = trim(sprintf('%s %s', $adminUser->getFirstName() ?? '', $adminUser->getLastName() ?? ''));

        return PublicKeyCredentialUserEntity::create(
            name: $adminUser->getUserIdentifier(),
            id: $this->userHandleGenerator->generate($adminUser),
            displayName: '' !== $displayName ? $displayName : $adminUser->getUserIdentifier(),
        );
    }
}
