<?php

declare(strict_types=1);

namespace Tests\SKrull\SyliusAdminPasskeyLoginPlugin\Unit\Repository;

use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use SKrull\SyliusAdminPasskeyLoginPlugin\Entity\AdminPasskey;
use SKrull\SyliusAdminPasskeyLoginPlugin\Repository\AdminPasskeyRepository;
use SKrull\SyliusAdminPasskeyLoginPlugin\Repository\AdminPasskeyUserEntityRepository;
use SKrull\SyliusAdminPasskeyLoginPlugin\Security\UserHandleGenerator;
use Sylius\Component\Core\Model\AdminUser;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Canonicalizer\Canonicalizer;
use Sylius\Component\User\Repository\UserRepositoryInterface;

final class AdminPasskeyUserEntityRepositoryTest extends TestCase
{
    /** @var UserRepositoryInterface<AdminUserInterface>&Stub */
    private UserRepositoryInterface&Stub $adminUserRepository;

    private AdminPasskeyRepository&Stub $adminPasskeyRepository;

    private UserHandleGenerator $userHandleGenerator;

    private AdminPasskeyUserEntityRepository $repository;

    protected function setUp(): void
    {
        $this->adminUserRepository = $this->createStub(UserRepositoryInterface::class);
        $this->adminPasskeyRepository = $this->createStub(AdminPasskeyRepository::class);
        $this->userHandleGenerator = new UserHandleGenerator('secret');

        $this->repository = new AdminPasskeyUserEntityRepository(
            $this->adminUserRepository,
            $this->adminPasskeyRepository,
            $this->userHandleGenerator,
            new Canonicalizer(),
        );
    }

    public function testItFindsTheAdminByCanonicalUsername(): void
    {
        $adminUser = $this->createAdmin(firstName: 'Ada', lastName: 'Lovelace');
        $this->adminUserRepository->method('findOneBy')->willReturnCallback(
            static fn (array $criteria): ?AdminUserInterface => ['usernameCanonical' => 'ada'] === $criteria ? $adminUser : null,
        );

        $userEntity = $this->repository->findOneByUsername('Ada');

        self::assertNotNull($userEntity);
        self::assertSame('ada', $userEntity->name);
        self::assertSame('Ada Lovelace', $userEntity->displayName);
        self::assertSame($this->userHandleGenerator->generate($adminUser), $userEntity->id);
    }

    public function testItFindsTheAdminByEmail(): void
    {
        $adminUser = $this->createAdmin();
        $this->adminUserRepository->method('findOneByEmail')->willReturnMap([
            ['ada@example.com', $adminUser],
        ]);

        self::assertSame('ada', $this->repository->findOneByUsername('ada@example.com')?->name);
    }

    public function testItFallsBackToTheUsernameAsDisplayName(): void
    {
        $this->adminUserRepository->method('findOneBy')->willReturn($this->createAdmin());

        self::assertSame('ada', $this->repository->findOneByUsername('ada')?->displayName);
    }

    public function testItReturnsNullForAnUnknownUsername(): void
    {
        $this->adminUserRepository->method('findOneBy')->willReturn(null);

        self::assertNull($this->repository->findOneByUsername('unknown'));
    }

    public function testItFindsTheAdminByUserHandleThroughItsPasskeys(): void
    {
        $adminUser = $this->createAdmin();
        $passkey = $this->createStub(AdminPasskey::class);
        $passkey->method('getAdminUser')->willReturn($adminUser);
        $this->adminPasskeyRepository->method('findOneBy')->willReturnMap([
            [['userHandle' => 'handle'], null, $passkey],
        ]);

        self::assertSame('ada', $this->repository->findOneByUserHandle('handle')?->name);
        self::assertNull($this->repository->findOneByUserHandle('unknown'));
    }

    private function createAdmin(?string $firstName = null, ?string $lastName = null): AdminUserInterface
    {
        $adminUser = new class() extends AdminUser {
            public function getId(): int
            {
                return 42;
            }
        };
        $adminUser->setUsername('Ada');
        $adminUser->setUsernameCanonical('ada');
        $adminUser->setEmail('ada@example.com');
        $adminUser->setFirstName($firstName);
        $adminUser->setLastName($lastName);

        return $adminUser;
    }
}
