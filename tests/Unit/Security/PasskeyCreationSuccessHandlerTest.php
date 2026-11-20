<?php

declare(strict_types=1);

namespace Tests\SKrull\SyliusAdminPasskeyLoginPlugin\Unit\Security;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SKrull\SyliusAdminPasskeyLoginPlugin\Entity\AdminPasskey;
use SKrull\SyliusAdminPasskeyLoginPlugin\Repository\AdminPasskeyRepository;
use SKrull\SyliusAdminPasskeyLoginPlugin\Security\PasskeyCreationSuccessHandler;
use Sylius\Component\Core\Model\AdminUser;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

final class PasskeyCreationSuccessHandlerTest extends TestCase
{
    private const CREDENTIAL_ID = "\x01\x02\xfa\xfb credential";

    #[DataProvider('provideNames')]
    public function testItStoresTheNormalizedName(mixed $name, ?string $expected): void
    {
        $owner = self::createAdmin(1);
        $passkey = $this->createPasskey($owner);

        $response = $this->createHandler($passkey, loggedInUser: $owner, expectFlush: true)
            ->onSuccess($this->createRequest(['id' => self::base64Url(self::CREDENTIAL_ID), 'name' => $name]))
        ;

        self::assertSame(201, $response->getStatusCode());
        self::assertSame($expected, $passkey->getName());
    }

    /** @return iterable<string, array{mixed, ?string}> */
    public static function provideNames(): iterable
    {
        yield 'plain name' => ['MacBook', 'MacBook'];
        yield 'surrounding whitespace' => ['  iPhone  ', 'iPhone'];
        yield 'empty string' => ['   ', null];
        yield 'not a string' => [['evil'], null];
        yield 'too long' => [str_repeat('ä', 150), str_repeat('ä', 100)];
    }

    public function testItDoesNotTouchAPasskeyOfAnotherAdmin(): void
    {
        $passkey = $this->createPasskey(self::createAdmin(1));

        $this->createHandler($passkey, loggedInUser: self::createAdmin(2), expectFlush: false)
            ->onSuccess($this->createRequest(['id' => self::base64Url(self::CREDENTIAL_ID), 'name' => 'Hacked']))
        ;

        self::assertNull($passkey->getName());
    }

    public function testItIgnoresAMissingId(): void
    {
        $passkey = $this->createPasskey(self::createAdmin(1));

        $response = $this->createHandler($passkey, loggedInUser: self::createAdmin(1), expectFlush: false)
            ->onSuccess($this->createRequest(['name' => 'MacBook']))
        ;

        self::assertSame(201, $response->getStatusCode());
    }

    private function createHandler(AdminPasskey $passkey, AdminUserInterface $loggedInUser, bool $expectFlush): PasskeyCreationSuccessHandler
    {
        $repository = $this->createStub(AdminPasskeyRepository::class);
        $repository->method('findOneByPublicKeyCredentialId')->willReturnCallback(
            static fn (string $id): ?AdminPasskey => self::CREDENTIAL_ID === $id ? $passkey : null,
        );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($expectFlush ? self::once() : self::never())->method('flush');

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($loggedInUser);

        return new PasskeyCreationSuccessHandler($repository, $entityManager, $security);
    }

    /** @param array<string, mixed> $data */
    private function createRequest(array $data): Request
    {
        return new Request(content: json_encode($data, \JSON_THROW_ON_ERROR));
    }

    private function createPasskey(AdminUserInterface $owner): AdminPasskey
    {
        return AdminPasskey::fromCredentialRecord($owner, CredentialRecord::create(
            publicKeyCredentialId: self::CREDENTIAL_ID,
            type: 'public-key',
            transports: [],
            attestationType: 'none',
            trustPath: EmptyTrustPath::create(),
            aaguid: Uuid::fromString('00000000-0000-0000-0000-000000000000'),
            credentialPublicKey: 'public-key',
            userHandle: 'user-handle',
            counter: 0,
        ), new \DateTimeImmutable());
    }

    private static function createAdmin(int $id): AdminUserInterface
    {
        $adminUser = new AdminUser();
        (new \ReflectionProperty($adminUser, 'id'))->setValue($adminUser, $id);

        return $adminUser;
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
