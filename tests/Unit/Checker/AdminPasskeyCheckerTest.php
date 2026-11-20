<?php

declare(strict_types=1);

namespace Tests\SKrull\SyliusAdminPasskeyLoginPlugin\Unit\Checker;

use PHPUnit\Framework\TestCase;
use SKrull\SyliusAdminPasskeyLoginPlugin\Checker\AdminPasskeyChecker;
use SKrull\SyliusAdminPasskeyLoginPlugin\Repository\AdminPasskeyRepository;
use Sylius\Component\Core\Model\AdminUserInterface;

final class AdminPasskeyCheckerTest extends TestCase
{
    public function testItQueriesTheDatabaseOnlyOnceForManyAdmins(): void
    {
        $repository = $this->createMock(AdminPasskeyRepository::class);
        $repository->expects(self::once())->method('findAdminUserIdsWithPasskey')->willReturn([2, 5]);

        $checker = new AdminPasskeyChecker($repository);

        self::assertFalse($checker->hasPasskey($this->adminWithId(1)));
        self::assertTrue($checker->hasPasskey($this->adminWithId(2)));
        self::assertFalse($checker->hasPasskey($this->adminWithId(3)));
        self::assertTrue($checker->hasPasskey($this->adminWithId(5)));
    }

    public function testItQueriesAgainAfterReset(): void
    {
        $repository = $this->createMock(AdminPasskeyRepository::class);
        $repository->expects(self::exactly(2))->method('findAdminUserIdsWithPasskey')->willReturn([], [1]);

        $checker = new AdminPasskeyChecker($repository);

        self::assertFalse($checker->hasPasskey($this->adminWithId(1)));
        $checker->reset();
        self::assertTrue($checker->hasPasskey($this->adminWithId(1)));
    }

    public function testAnUnsavedAdminHasNoPasskey(): void
    {
        $repository = $this->createStub(AdminPasskeyRepository::class);
        $repository->method('findAdminUserIdsWithPasskey')->willReturn([1]);

        self::assertFalse((new AdminPasskeyChecker($repository))->hasPasskey($this->adminWithId(null)));
    }

    private function adminWithId(?int $id): AdminUserInterface
    {
        $adminUser = $this->createStub(AdminUserInterface::class);
        $adminUser->method('getId')->willReturn($id);

        return $adminUser;
    }
}
