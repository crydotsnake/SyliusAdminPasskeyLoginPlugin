<?php

declare(strict_types=1);

namespace SKrull\SyliusAdminPasskeyLoginPlugin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use SKrull\SyliusAdminPasskeyLoginPlugin\Entity\AdminPasskey;
use SKrull\SyliusAdminPasskeyLoginPlugin\Form\Type\PasskeyRenameType;
use SKrull\SyliusAdminPasskeyLoginPlugin\Repository\AdminPasskeyRepository;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class RenamePasskeyAction
{
    public function __construct(
        private readonly AdminPasskeyRepository $adminPasskeyRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $generator,
        private readonly Environment $twig,
    ) {
    }

    public function __invoke(Request $request, int $id): Response
    {
        $adminUser = $this->security->getUser();
        $passkey = $this->adminPasskeyRepository->find($id);
        if (
            null === $passkey ||
            !$adminUser instanceof AdminUserInterface ||
            $passkey->getAdminUser()->getId() !== $adminUser->getId()
        ) {
            throw new NotFoundHttpException('Passkey not found.');
        }

        $form = $this->formFactory->create(PasskeyRenameType::class, ['name' => $passkey->getName()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $passkey->setName(AdminPasskey::normalizeName($form->get('name')->getData()));
            $this->entityManager->flush();

            $session = $request->hasSession() ? $request->getSession() : null;
            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('success', 'skrull_sylius_admin_passkey_login.passkey_renamed');
            }

            return new RedirectResponse($this->generator->generate('sylius_admin_admin_user_update', ['id' => $adminUser->getId()]));
        }

        return new Response(
            $this->twig->render('@SKrullSyliusAdminPasskeyLoginPlugin/admin/passkey/rename.html.twig', [
                'passkey' => $passkey,
                'form' => $form->createView(),
            ]),
            $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
        );
    }
}
