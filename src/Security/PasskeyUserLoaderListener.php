<?php

declare(strict_types=1);

namespace SKrull\SyliusAdminPasskeyLoginPlugin\Security;

use SKrull\SyliusAdminPasskeyLoginPlugin\Repository\AdminPasskeyRepository;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Webauthn\Bundle\Security\Http\Authenticator\Passport\Credentials\WebauthnCredentials;

/**
 * The WebAuthn authenticator loads the user through the firewall user provider by username, and Sylius'
 * email_or_name_based provider looks up email-like usernames by email: the passkey would log in another admin
 * or nobody. This listener loads the owner of the passkey instead.
 */
final class PasskeyUserLoaderListener implements EventSubscriberInterface
{
    // Before Symfony's UserProviderListener (2048) and every listener that loads the user.
    public const PRIORITY = 4096;

    public function __construct(
        private readonly AdminPasskeyRepository $adminPasskeyRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [CheckPassportEvent::class => ['loadPasskeyOwner', self::PRIORITY]];
    }

    public function loadPasskeyOwner(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        $credentials = $passport->getBadge(WebauthnCredentials::class);
        $userBadge = $passport->getBadge(UserBadge::class);
        if (!$credentials instanceof WebauthnCredentials || !$userBadge instanceof UserBadge) {
            return;
        }

        $publicKeyCredentialId = $credentials->getPublicKeyCredentialSource()->publicKeyCredentialId;

        $userBadge->setUserLoader(
            fn (): ?AdminUserInterface => $this->adminPasskeyRepository->findOneByPublicKeyCredentialId($publicKeyCredentialId)?->getAdminUser(),
        );
    }
}
