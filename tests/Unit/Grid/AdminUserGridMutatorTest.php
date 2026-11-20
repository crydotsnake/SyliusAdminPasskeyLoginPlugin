<?php

declare(strict_types=1);

namespace Tests\SKrull\SyliusAdminPasskeyLoginPlugin\Unit\Grid;

use PHPUnit\Framework\TestCase;
use SKrull\SyliusAdminPasskeyLoginPlugin\Grid\AdminUserGridMutator;
use Sylius\Component\Grid\Builder\GridBuilder;

final class AdminUserGridMutatorTest extends TestCase
{
    public function testItAddsThePasskeyColumn(): void
    {
        $gridBuilder = GridBuilder::create(AdminUserGridMutator::GRID);

        (new AdminUserGridMutator())($gridBuilder);

        $field = $gridBuilder->toArray()['fields']['has_passkey'] ?? null;

        self::assertIsArray($field);
        self::assertSame('twig', $field['type']);
        self::assertSame('.', $field['path']);
        self::assertSame('skrull_sylius_admin_passkey_login.ui.passkey', $field['label']);
        self::assertSame('@SKrullSyliusAdminPasskeyLoginPlugin/admin/grid/field/has_passkey.html.twig', $field['options']['template']);
    }
}
