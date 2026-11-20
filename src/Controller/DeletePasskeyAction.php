<?php

declare(strict_types=1);

namespace SKrull\SyliusAdminPasskeyLoginPlugin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use SKrull\SyliusAdminPasskeyLoginPlugin\Repository\AdminPasskeyRepository;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class DeletePasskeyAction
{
    public const CSRF_TOKEN_PREFIX = 'skrull_admin_passkey_delete_';

    public function __construct(
        private readonly AdminPasskeyRepository $adminPasskeyRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request, int $id): JsonResponse
    {
        $token = new CsrfToken(self::CSRF_TOKEN_PREFIX . $id, (string) $request->request->get('_csrf_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $adminUser = $this->security->getUser();
        $passkey = $this->adminPasskeyRepository->find($id);
        if (
            null === $passkey ||
            !$adminUser instanceof AdminUserInterface ||
            $passkey->getAdminUser()->getId() !== $adminUser->getId()
        ) {
            throw new NotFoundHttpException('Passkey not found.');
        }

        $this->entityManager->remove($passkey);
        $this->entityManager->flush();

        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('success', 'skrull_sylius_admin_passkey_login.passkey_deleted');
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
