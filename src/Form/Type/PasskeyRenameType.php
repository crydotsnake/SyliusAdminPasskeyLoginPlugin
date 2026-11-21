<?php

declare(strict_types=1);

namespace SKrull\SyliusAdminPasskeyLoginPlugin\Form\Type;

use SKrull\SyliusAdminPasskeyLoginPlugin\Entity\AdminPasskey;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class PasskeyRenameType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => 'sylius.ui.name',
            'required' => false,
            'help' => 'skrull_sylius_admin_passkey_login.ui.rename_hint',
            'attr' => [
                'maxlength' => AdminPasskey::NAME_MAX_LENGTH,
                'placeholder' => 'skrull_sylius_admin_passkey_login.ui.name_placeholder',
                'autofocus' => true,
            ],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'skrull_admin_passkey_rename';
    }
}
