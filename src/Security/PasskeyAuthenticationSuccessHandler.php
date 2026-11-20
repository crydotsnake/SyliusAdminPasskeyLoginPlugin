<?php

declare(strict_types=1);

namespace SKrull\SyliusAdminPasskeyLoginPlugin\Security;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

final class PasskeyAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    use TargetPathTrait;

    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $defaultTargetRoute = 'sylius_admin_dashboard',
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'redirect' => $this->popTargetPath($request) ?? $this->urlGenerator->generate($this->defaultTargetRoute),
        ], Response::HTTP_OK);
    }

    private function popTargetPath(Request $request): ?string
    {
        $firewallName = $this->security->getFirewallConfig($request)?->getName();
        if (null === $firewallName || !$request->hasSession()) {
            return null;
        }

        $session = $request->getSession();
        $targetPath = $this->getTargetPath($session, $firewallName);
        $this->removeTargetPath($session, $firewallName);

        return $targetPath;
    }
}
