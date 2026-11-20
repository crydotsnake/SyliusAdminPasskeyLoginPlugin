<?php

declare(strict_types=1);

namespace SKrull\SyliusAdminPasskeyLoginPlugin\Twig;

use SKrull\SyliusAdminPasskeyLoginPlugin\Checker\AdminPasskeyChecker;
use SKrull\SyliusAdminPasskeyLoginPlugin\Entity\AdminPasskey;
use SKrull\SyliusAdminPasskeyLoginPlugin\Repository\AdminPasskeyRepository;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AdminPasskeyExtension extends AbstractExtension
{
    public function __construct(
        private readonly AdminPasskeyRepository $adminPasskeyRepository,
        private readonly AdminPasskeyChecker $adminPasskeyChecker,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $firewallName = 'admin',
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('skrull_admin_passkeys', $this->getPasskeys(...)),
            new TwigFunction('skrull_admin_has_passkey', $this->adminPasskeyChecker->hasPasskey(...)),
            new TwigFunction('skrull_admin_passkey_login_urls', $this->getLoginUrls(...)),
        ];
    }

    /** @return list<AdminPasskey> */
    public function getPasskeys(AdminUserInterface $adminUser): array
    {
        return $this->adminPasskeyRepository->findByAdminUser($adminUser);
    }

    /** @return array{options: string, result: string}|null */
    public function getLoginUrls(): ?array
    {
        $prefix = sprintf('webauthn.controller.security.%s.request.', $this->firewallName);

        try {
            return [
                'options' => $this->urlGenerator->generate($prefix . 'options'),
                'result' => $this->urlGenerator->generate($prefix . 'result'),
            ];
        } catch (RouteNotFoundException) {
            return null;
        }
    }
}
