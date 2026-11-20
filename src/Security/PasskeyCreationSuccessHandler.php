<?php

declare(strict_types=1);

namespace SKrull\SyliusAdminPasskeyLoginPlugin\Security;

use Doctrine\ORM\EntityManagerInterface;
use SKrull\SyliusAdminPasskeyLoginPlugin\Repository\AdminPasskeyRepository;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Webauthn\Bundle\Security\Handler\SuccessHandler;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialOptions;
use Webauthn\PublicKeyCredentialUserEntity;

final class PasskeyCreationSuccessHandler implements SuccessHandler
{
    public const NAME_MAX_LENGTH = 100;

    public function __construct(
        private readonly AdminPasskeyRepository $adminPasskeyRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function onSuccess(
        Request $request,
        ?PublicKeyCredential $publicKeyCredential = null,
        ?PublicKeyCredentialOptions $publicKeyCredentialOptions = null,
        ?PublicKeyCredentialUserEntity $userEntity = null,
    ): Response {
        /** @var array{id?: mixed, name?: mixed} $data */
        $data = json_decode($request->getContent(), true) ?: [];

        $credentialId = is_string($data['id'] ?? null) ? base64_decode(strtr($data['id'], '-_', '+/'), true) : false;
        $passkey = false !== $credentialId ? $this->adminPasskeyRepository->findOneByPublicKeyCredentialId($credentialId) : null;

        $adminUser = $this->security->getUser();
        if (
            null !== $passkey &&
            $adminUser instanceof AdminUserInterface &&
            $passkey->getAdminUser()->getId() === $adminUser->getId()
        ) {
            $passkey->setName($this->normalizeName($data['name'] ?? null));
            $this->entityManager->flush();
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('success', 'skrull_sylius_admin_passkey_login.passkey_added');
        }

        return new JsonResponse(['status' => 'ok', 'errorMessage' => ''], Response::HTTP_CREATED);
    }

    private function normalizeName(mixed $name): ?string
    {
        if (!is_string($name)) {
            return null;
        }

        $name = trim($name);

        return '' !== $name ? mb_substr($name, 0, self::NAME_MAX_LENGTH) : null;
    }
}
