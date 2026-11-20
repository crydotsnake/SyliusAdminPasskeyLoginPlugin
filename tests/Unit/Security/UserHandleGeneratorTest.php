<?php

declare(strict_types=1);

namespace Tests\SKrull\SyliusAdminPasskeyLoginPlugin\Unit\Security;

use PHPUnit\Framework\TestCase;
use SKrull\SyliusAdminPasskeyLoginPlugin\Security\UserHandleGenerator;
use Sylius\Component\Core\Model\AdminUserInterface;

final class UserHandleGeneratorTest extends TestCase
{
    public function testItIsStableForTheSameAdmin(): void
    {
        $generator = new UserHandleGenerator('secret');

        self::assertSame($generator->generate($this->adminWithId(1)), $generator->generate($this->adminWithId(1)));
    }

    public function testItDiffersPerAdminAndPerSecret(): void
    {
        $generator = new UserHandleGenerator('secret');

        self::assertNotSame($generator->generate($this->adminWithId(1)), $generator->generate($this->adminWithId(2)));
        self::assertNotSame(
            $generator->generate($this->adminWithId(1)),
            (new UserHandleGenerator('other-secret'))->generate($this->adminWithId(1)),
        );
    }

    public function testItFitsTheWebauthnLimitOf64Bytes(): void
    {
        self::assertSame(64, \strlen((new UserHandleGenerator('secret'))->generate($this->adminWithId(1))));
    }

    public function testItRequiresAPersistedAdmin(): void
    {
        $this->expectException(\LogicException::class);

        (new UserHandleGenerator('secret'))->generate($this->adminWithId(null));
    }

    private function adminWithId(?int $id): AdminUserInterface
    {
        $adminUser = $this->createStub(AdminUserInterface::class);
        $adminUser->method('getId')->willReturn($id);

        return $adminUser;
    }
}
