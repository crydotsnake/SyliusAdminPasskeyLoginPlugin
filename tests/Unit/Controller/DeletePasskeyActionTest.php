<?php

declare(strict_types=1);

namespace Tests\SKrull\SyliusAdminPasskeyLoginPlugin\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SKrull\SyliusAdminPasskeyLoginPlugin\Controller\DeletePasskeyAction;
use SKrull\SyliusAdminPasskeyLoginPlugin\Entity\AdminPasskey;
use SKrull\SyliusAdminPasskeyLoginPlugin\Repository\AdminPasskeyRepository;
use Sylius\Component\Core\Model\AdminUser;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

final class DeletePasskeyActionTest extends TestCase
{
    private const VALID_TOKEN = 'valid-token';

    private EntityManagerInterface&MockObject $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
    }

    public function testItDeletesTheOwnPasskeyAndAddsAFlashMessage(): void
    {
        $owner = self::createAdmin(1);
        $passkey = $this->createPasskey($owner);
        $session = new Session(new MockArraySessionStorage());
        $request = $this->createRequest(self::VALID_TOKEN);
        $request->setSession($session);

        $this->entityManager->expects(self::once())->method('remove')->with($passkey);
        $this->entityManager->expects(self::once())->method('flush');

        $response = $this->createAction($passkey, loggedInUser: $owner)($request, 7);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['skrull_sylius_admin_passkey_login.passkey_deleted'], $session->getFlashBag()->get('success'));
    }

    public function testItRejectsAnInvalidCsrfToken(): void
    {
        $owner = self::createAdmin(1);
        $this->entityManager->expects(self::never())->method('remove');

        $this->expectException(AccessDeniedHttpException::class);

        $this->createAction($this->createPasskey($owner), loggedInUser: $owner)($this->createRequest('wrong-token'), 7);
    }

    public function testItAnswersAPasskeyOfAnotherAdminWithNotFound(): void
    {
        $this->entityManager->expects(self::never())->method('remove');

        $this->expectException(NotFoundHttpException::class);

        $this->createAction($this->createPasskey(self::createAdmin(1)), loggedInUser: self::createAdmin(2))(
            $this->createRequest(self::VALID_TOKEN),
            7,
        );
    }

    public function testItAnswersAnUnknownPasskeyWithNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createAction(null, loggedInUser: self::createAdmin(1))($this->createRequest(self::VALID_TOKEN), 7);
    }

    private function createAction(?AdminPasskey $passkey, AdminUserInterface $loggedInUser): DeletePasskeyAction
    {
        $repository = $this->createStub(AdminPasskeyRepository::class);
        $repository->method('find')->willReturnCallback(static fn (mixed $id): ?AdminPasskey => 7 === $id ? $passkey : null);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($loggedInUser);

        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturnCallback(
            static fn (CsrfToken $token): bool => DeletePasskeyAction::CSRF_TOKEN_PREFIX . '7' === $token->getId() && self::VALID_TOKEN === $token->getValue(),
        );

        return new DeletePasskeyAction($repository, $this->entityManager, $security, $csrfTokenManager);
    }

    private function createRequest(string $csrfToken): Request
    {
        return new Request(request: ['_csrf_token' => $csrfToken]);
    }

    private static function createAdmin(int $id): AdminUserInterface
    {
        $adminUser = new AdminUser();
        (new \ReflectionProperty($adminUser, 'id'))->setValue($adminUser, $id);

        return $adminUser;
    }

    private function createPasskey(AdminUserInterface $owner): AdminPasskey
    {
        return AdminPasskey::fromCredentialRecord($owner, CredentialRecord::create(
            publicKeyCredentialId: 'credential-id',
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
}
