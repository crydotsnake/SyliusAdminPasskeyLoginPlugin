<?php

declare(strict_types=1);

namespace SKrull\SyliusAdminPasskeyLoginPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SKrull\SyliusAdminPasskeyLoginPlugin\Entity\AdminPasskey;
use SKrull\SyliusAdminPasskeyLoginPlugin\Security\UserHandleGenerator;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\ClockInterface;
use Webauthn\Bundle\Repository\CanSaveCredentialRecord;
use Webauthn\Bundle\Repository\CredentialRecordRepositoryInterface;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialUserEntity;

/** @extends ServiceEntityRepository<AdminPasskey> */
class AdminPasskeyRepository extends ServiceEntityRepository implements CredentialRecordRepositoryInterface, CanSaveCredentialRecord
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly Security $security,
        private readonly UserHandleGenerator $userHandleGenerator,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($registry, AdminPasskey::class);
    }

    public function findOneByCredentialId(string $publicKeyCredentialId): ?CredentialRecord
    {
        return $this->findOneByPublicKeyCredentialId($publicKeyCredentialId)?->toCredentialRecord();
    }

    /** @return list<CredentialRecord> */
    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        return array_map(
            static fn (AdminPasskey $passkey): CredentialRecord => $passkey->toCredentialRecord(),
            $this->findBy(['userHandle' => $publicKeyCredentialUserEntity->id]),
        );
    }

    public function saveCredentialRecord(CredentialRecord $credentialRecord): void
    {
        $now = $this->clock->now();
        $passkey = $this->findOneByPublicKeyCredentialId($credentialRecord->publicKeyCredentialId);

        if (null !== $passkey) {
            $passkey->updateFromCredentialRecord($credentialRecord);
            $passkey->markAsUsed($now);
        } else {
            $passkey = AdminPasskey::fromCredentialRecord($this->getAdminUserForNewPasskey($credentialRecord), $credentialRecord, $now);
            $this->getEntityManager()->persist($passkey);
        }

        $this->getEntityManager()->flush();
    }

    public function findOneByPublicKeyCredentialId(string $publicKeyCredentialId): ?AdminPasskey
    {
        return $this->findOneBy(['credentialIdHash' => AdminPasskey::hashCredentialId($publicKeyCredentialId)]);
    }

    /** @return list<AdminPasskey> */
    public function findByAdminUser(AdminUserInterface $adminUser): array
    {
        return $this->findBy(['adminUser' => $adminUser], ['createdAt' => 'ASC']);
    }

    /** @return list<int> */
    public function findAdminUserIdsWithPasskey(): array
    {
        /** @var list<int|string> $ids */
        $ids = $this->createQueryBuilder('passkey')
            ->select('DISTINCT IDENTITY(passkey.adminUser)')
            ->getQuery()
            ->getSingleColumnResult()
        ;

        return array_map(intval(...), $ids);
    }

    private function getAdminUserForNewPasskey(CredentialRecord $credentialRecord): AdminUserInterface
    {
        $adminUser = $this->security->getUser();
        if (!$adminUser instanceof AdminUserInterface) {
            throw new \LogicException('A new passkey can only be saved for a logged-in admin user.');
        }

        if (!hash_equals($this->userHandleGenerator->generate($adminUser), $credentialRecord->userHandle)) {
            throw new \LogicException('The passkey does not belong to the logged-in admin user.');
        }

        return $adminUser;
    }
}
