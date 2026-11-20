<?php

declare(strict_types=1);

namespace Tests\SKrull\SyliusAdminPasskeyLoginPlugin\Unit\Security;

use PHPUnit\Framework\TestCase;
use SKrull\SyliusAdminPasskeyLoginPlugin\Security\PasskeyAuthenticationSuccessHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class PasskeyAuthenticationSuccessHandlerTest extends TestCase
{
    public function testItRedirectsToTheDashboardByDefault(): void
    {
        $response = $this->createHandler()->onAuthenticationSuccess($this->createRequest(), $this->createStub(TokenInterface::class));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['status' => 'ok', 'redirect' => '/admin/'], json_decode((string) $response->getContent(), true));
    }

    public function testItRedirectsToThePageTheAdminWantedToOpenAndForgetsIt(): void
    {
        $request = $this->createRequest();
        $request->getSession()->set('_security.admin.target_path', 'https://shop.test/admin/orders/');

        $response = $this->createHandler()->onAuthenticationSuccess($request, $this->createStub(TokenInterface::class));

        self::assertSame('https://shop.test/admin/orders/', json_decode((string) $response->getContent(), true)['redirect']);
        self::assertFalse($request->getSession()->has('_security.admin.target_path'));
    }

    private function createHandler(): PasskeyAuthenticationSuccessHandler
    {
        $security = $this->createStub(Security::class);
        $security->method('getFirewallConfig')->willReturn(new FirewallConfig('admin', 'security.user_checker'));

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnMap([['sylius_admin_dashboard', [], UrlGeneratorInterface::ABSOLUTE_PATH, '/admin/']]);

        return new PasskeyAuthenticationSuccessHandler($security, $urlGenerator);
    }

    private function createRequest(): Request
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
