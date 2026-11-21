<?php

declare(strict_types=1);

namespace Tests\SKrull\SyliusAdminPasskeyLoginPlugin\Unit\Security;

use PHPUnit\Framework\TestCase;
use SKrull\SyliusAdminPasskeyLoginPlugin\Entity\AdminPasskey;
use SKrull\SyliusAdminPasskeyLoginPlugin\Repository\AdminPasskeyRepository;
use SKrull\SyliusAdminPasskeyLoginPlugin\Security\PasskeyUserLoaderListener;
use Sylius\Component\Core\Model\AdminUser;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CredentialsInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Uid\Uuid;
use Webauthn\AuthenticatorResponse;
use Webauthn\Bundle\Security\Http\Authenticator\Passport\Credentials\WebauthnCredentials;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\EmptyTrustPath;

final class PasskeyUserLoaderListenerTest extends TestCase
{
    private const CREDENTIAL_ID = 'credential-id';

    public function testItLoadsTheOwnerOfThePasskey(): void
    {
        $owner = self::createAdmin(1);
        $passport = $this->createPassport($this->createWebauthnCredentials(), providerUser: null);

        $this->createListener($this->createPasskey($owner))->loadPasskeyOwner($this->createEvent($passport));

        self::assertSame($owner, $passport->getUser());
    }

    public function testItIgnoresTheUserOfTheUserProvider(): void
    {
        // The username of the owner equals the email of another admin, so the provider would load that admin.
        $owner = self::createAdmin(1);
        $passport = $this->createPassport($this->createWebauthnCredentials(), providerUser: self::createAdmin(2));

        $this->createListener($this->createPasskey($owner))->loadPasskeyOwner($this->createEvent($passport));

        self::assertSame($owner, $passport->getUser());
    }

    public function testItLoadsNobodyForAnUnknownPasskey(): void
    {
        $passport = $this->createPassport($this->createWebauthnCredentials(), providerUser: self::createAdmin(2));

        $this->createListener(null)->loadPasskeyOwner($this->createEvent($passport));

        $this->expectException(UserNotFoundException::class);

        $passport->getUser();
    }

    public function testItIgnoresPassportsWithoutWebauthnCredentials(): void
    {
        $providerUser = self::createAdmin(2);
        $passport = $this->createPassport(new PasswordCredentials('password'), providerUser: $providerUser);

        $this->createListener($this->createPasskey(self::createAdmin(1)))->loadPasskeyOwner($this->createEvent($passport));

        self::assertSame($providerUser, $passport->getUser());
    }

    public function testItRunsBeforeTheUserProviderListener(): void
    {
        $subscribedEvents = PasskeyUserLoaderListener::getSubscribedEvents();

        self::assertSame(['loadPasskeyOwner', PasskeyUserLoaderListener::PRIORITY], $subscribedEvents[CheckPassportEvent::class]);
        self::assertGreaterThan(2048, PasskeyUserLoaderListener::PRIORITY);
    }

    private function createListener(?AdminPasskey $passkey): PasskeyUserLoaderListener
    {
        $repository = $this->createStub(AdminPasskeyRepository::class);
        $repository->method('findOneByPublicKeyCredentialId')->willReturnCallback(
            static fn (string $credentialId): ?AdminPasskey => self::CREDENTIAL_ID === $credentialId ? $passkey : null,
        );

        return new PasskeyUserLoaderListener($repository);
    }

    private function createPassport(CredentialsInterface $credentials, ?UserInterface $providerUser): Passport
    {
        return new Passport(new UserBadge('test@test.de', static fn (): ?UserInterface => $providerUser), $credentials);
    }

    private function createEvent(Passport $passport): CheckPassportEvent
    {
        return new CheckPassportEvent($this->createStub(AuthenticatorInterface::class), $passport);
    }

    private function createWebauthnCredentials(): WebauthnCredentials
    {
        return new WebauthnCredentials(
            $this->createStub(AuthenticatorResponse::class),
            PublicKeyCredentialRequestOptions::create('challenge'),
            PublicKeyCredentialUserEntity::create('test@test.de', 'user-handle', 'Owner'),
            self::createCredentialRecord(),
            'admin',
        );
    }

    private static function createAdmin(int $id): AdminUserInterface
    {
        $adminUser = new AdminUser();
        (new \ReflectionProperty($adminUser, 'id'))->setValue($adminUser, $id);

        return $adminUser;
    }

    private function createPasskey(AdminUserInterface $owner): AdminPasskey
    {
        return AdminPasskey::fromCredentialRecord($owner, self::createCredentialRecord(), new \DateTimeImmutable());
    }

    private static function createCredentialRecord(): CredentialRecord
    {
        return CredentialRecord::create(
            publicKeyCredentialId: self::CREDENTIAL_ID,
            type: 'public-key',
            transports: [],
            attestationType: 'none',
            trustPath: EmptyTrustPath::create(),
            aaguid: Uuid::fromString('00000000-0000-0000-0000-000000000000'),
            credentialPublicKey: 'public-key',
            userHandle: 'user-handle',
            counter: 0,
        );
    }
}
