<?php

declare(strict_types=1);

namespace Tests\SKrull\SyliusAdminPasskeyLoginPlugin\Unit\Entity;

use PHPUnit\Framework\TestCase;
use SKrull\SyliusAdminPasskeyLoginPlugin\Entity\AdminPasskey;
use Sylius\Component\Core\Model\AdminUser;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

final class AdminPasskeyTest extends TestCase
{
    public function testItConvertsFromAndToCredentialRecord(): void
    {
        $record = $this->createCredentialRecord(counter: 5);
        $adminUser = new AdminUser();
        $createdAt = new \DateTimeImmutable('2026-11-20 10:00:00');

        $passkey = AdminPasskey::fromCredentialRecord($adminUser, $record, $createdAt);

        self::assertSame($adminUser, $passkey->getAdminUser());
        self::assertSame($createdAt, $passkey->getCreatedAt());
        self::assertNull($passkey->getLastUsedAt());
        self::assertSame('user-handle', $passkey->getUserHandle());
        self::assertEquals($record, $passkey->toCredentialRecord());
    }

    public function testItUpdatesCounterAndMarksUsageAfterLogin(): void
    {
        $passkey = AdminPasskey::fromCredentialRecord(new AdminUser(), $this->createCredentialRecord(counter: 5), new \DateTimeImmutable());
        $lastUsedAt = new \DateTimeImmutable('2026-11-21 08:00:00');

        $passkey->updateFromCredentialRecord($this->createCredentialRecord(counter: 6, backupStatus: true));
        $passkey->markAsUsed($lastUsedAt);

        self::assertSame(6, $passkey->toCredentialRecord()->counter);
        self::assertTrue($passkey->toCredentialRecord()->backupStatus);
        self::assertSame($lastUsedAt, $passkey->getLastUsedAt());
    }

    public function testCredentialIdHashIsStableSha256(): void
    {
        self::assertSame(hash('sha256', 'abc'), AdminPasskey::hashCredentialId('abc'));
    }

    private function createCredentialRecord(int $counter, bool $backupStatus = false): CredentialRecord
    {
        return CredentialRecord::create(
            publicKeyCredentialId: 'credential-id',
            type: 'public-key',
            transports: ['internal', 'hybrid'],
            attestationType: 'none',
            trustPath: EmptyTrustPath::create(),
            aaguid: Uuid::fromString('00000000-0000-0000-0000-000000000000'),
            credentialPublicKey: 'public-key',
            userHandle: 'user-handle',
            counter: $counter,
            otherUI: null,
            backupEligible: true,
            backupStatus: $backupStatus,
            uvInitialized: true,
        );
    }
}
