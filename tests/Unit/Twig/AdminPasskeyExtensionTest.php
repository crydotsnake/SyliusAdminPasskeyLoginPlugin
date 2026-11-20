<?php

declare(strict_types=1);

namespace Tests\SKrull\SyliusAdminPasskeyLoginPlugin\Unit\Twig;

use PHPUnit\Framework\TestCase;
use SKrull\SyliusAdminPasskeyLoginPlugin\Checker\AdminPasskeyChecker;
use SKrull\SyliusAdminPasskeyLoginPlugin\Repository\AdminPasskeyRepository;
use SKrull\SyliusAdminPasskeyLoginPlugin\Twig\AdminPasskeyExtension;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AdminPasskeyExtensionTest extends TestCase
{
    public function testItReturnsTheLoginUrlsOfTheAdminFirewall(): void
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnMap([
            ['webauthn.controller.security.admin.request.options', [], UrlGeneratorInterface::ABSOLUTE_PATH, '/admin/login/passkey/options'],
            ['webauthn.controller.security.admin.request.result', [], UrlGeneratorInterface::ABSOLUTE_PATH, '/admin/login/passkey'],
        ]);

        self::assertSame(
            ['options' => '/admin/login/passkey/options', 'result' => '/admin/login/passkey'],
            $this->createExtension($urlGenerator)->getLoginUrls(),
        );
    }

    public function testItReturnsNullWhenTheFirewallIsNotConfigured(): void
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willThrowException(new RouteNotFoundException());

        self::assertNull($this->createExtension($urlGenerator)->getLoginUrls());
    }

    private function createExtension(UrlGeneratorInterface $urlGenerator): AdminPasskeyExtension
    {
        $repository = $this->createStub(AdminPasskeyRepository::class);

        return new AdminPasskeyExtension($repository, new AdminPasskeyChecker($repository), $urlGenerator);
    }
}
