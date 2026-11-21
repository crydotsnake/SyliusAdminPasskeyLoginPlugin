<?php

declare(strict_types=1);

namespace Tests\SKrull\SyliusAdminPasskeyLoginPlugin\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SKrull\SyliusAdminPasskeyLoginPlugin\Controller\RenamePasskeyAction;
use SKrull\SyliusAdminPasskeyLoginPlugin\Entity\AdminPasskey;
use SKrull\SyliusAdminPasskeyLoginPlugin\Repository\AdminPasskeyRepository;
use Sylius\Component\Core\Model\AdminUser;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

final class RenamePasskeyActionTest extends TestCase
{
    private const VALID_TOKEN = 'valid-token';

    private EntityManagerInterface&MockObject $entityManager;

    /** @var array<string, mixed>|null */
    private ?array $renderedContext = null;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
    }

    public function testItShowsTheRenameFormWithTheCurrentName(): void
    {
        $owner = self::createAdmin(1);
        $passkey = $this->createPasskey($owner);
        $passkey->setName('MacBook');
        $this->entityManager->expects(self::never())->method('flush');

        $response = $this->createAction($passkey, loggedInUser: $owner)(new Request(), 7);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($passkey, $this->renderedContext['passkey'] ?? null);
        $form = $this->renderedContext['form'] ?? null;
        self::assertInstanceOf(FormView::class, $form);
        self::assertSame('MacBook', $form['name']->vars['value']);
    }

    public function testItRenamesTheOwnPasskeyAndRedirectsToTheEditPage(): void
    {
        $owner = self::createAdmin(1);
        $passkey = $this->createPasskey($owner);
        $session = new Session(new MockArraySessionStorage());
        $request = $this->createPostRequest(self::VALID_TOKEN, '  MacBook  ');
        $request->setSession($session);

        $this->entityManager->expects(self::once())->method('flush');

        $response = $this->createAction($passkey, loggedInUser: $owner)($request, 7);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/users/1/edit', $response->getTargetUrl());
        self::assertSame('MacBook', $passkey->getName());
        self::assertSame(['skrull_sylius_admin_passkey_login.passkey_renamed'], $session->getFlashBag()->get('success'));
    }

    public function testItResetsAnEmptyNameToNull(): void
    {
        $owner = self::createAdmin(1);
        $passkey = $this->createPasskey($owner);
        $passkey->setName('MacBook');

        $this->createAction($passkey, loggedInUser: $owner)($this->createPostRequest(self::VALID_TOKEN, '   '), 7);

        self::assertNull($passkey->getName());
    }

    public function testItShowsTheFormAgainForAnInvalidCsrfToken(): void
    {
        $owner = self::createAdmin(1);
        $passkey = $this->createPasskey($owner);
        $this->entityManager->expects(self::never())->method('flush');

        $response = $this->createAction($passkey, loggedInUser: $owner)($this->createPostRequest('wrong-token', 'Hacked'), 7);

        self::assertSame(422, $response->getStatusCode());
        self::assertNull($passkey->getName());
    }

    public function testItAnswersAPasskeyOfAnotherAdminWithNotFound(): void
    {
        $passkey = $this->createPasskey(self::createAdmin(1));
        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(NotFoundHttpException::class);

        try {
            $this->createAction($passkey, loggedInUser: self::createAdmin(2))($this->createPostRequest(self::VALID_TOKEN, 'Hacked'), 7);
        } finally {
            self::assertNull($passkey->getName());
        }
    }

    public function testItAnswersAPasskeyOfAnotherAdminWithNotFoundOnGet(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createAction($this->createPasskey(self::createAdmin(1)), loggedInUser: self::createAdmin(2))(new Request(), 7);
    }

    public function testItAnswersAnUnknownPasskeyWithNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createAction(null, loggedInUser: self::createAdmin(1))(new Request(), 7);
    }

    private function createAction(?AdminPasskey $passkey, AdminUserInterface $loggedInUser): RenamePasskeyAction
    {
        $repository = $this->createStub(AdminPasskeyRepository::class);
        $repository->method('find')->willReturnCallback(static fn (mixed $id): ?AdminPasskey => 7 === $id ? $passkey : null);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($loggedInUser);

        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('getToken')->willReturnCallback(static fn (string $id): CsrfToken => new CsrfToken($id, self::VALID_TOKEN));
        $csrfTokenManager->method('isTokenValid')->willReturnCallback(static fn (CsrfToken $token): bool => self::VALID_TOKEN === $token->getValue());

        $formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension())
            ->addExtension(new CsrfExtension($csrfTokenManager))
            ->getFormFactory()
        ;

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $route, array $parameters): string => sprintf('/admin/users/%s/edit', $parameters['id']),
        );

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(function (string $template, array $context): string {
            $this->renderedContext = $context;

            return $template;
        });

        return new RenamePasskeyAction($repository, $this->entityManager, $security, $formFactory, $urlGenerator, $twig);
    }

    private function createPostRequest(string $csrfToken, string $name): Request
    {
        return new Request(
            request: ['skrull_admin_passkey_rename' => ['name' => $name, '_token' => $csrfToken]],
            server: ['REQUEST_METHOD' => 'POST'],
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
