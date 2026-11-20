<?php

declare(strict_types=1);

namespace SKrull\SyliusAdminPasskeyLoginPlugin\Grid;

use Sylius\Bundle\GridBundle\Builder\Field\TwigField;
use Sylius\Component\Grid\Builder\GridBuilderInterface;
use Sylius\Component\Grid\Mutator\GridMutatorInterface;

final class AdminUserGridMutator implements GridMutatorInterface
{
    public const GRID = 'sylius_admin_admin_user';

    public function __invoke(GridBuilderInterface $gridBuilder): void
    {
        $gridBuilder->addField(
            TwigField::create('has_passkey', '@SKrullSyliusAdminPasskeyLoginPlugin/admin/grid/field/has_passkey.html.twig')
                ->setPath('.')
                ->setLabel('skrull_sylius_admin_passkey_login.ui.passkey')
                ->addOptions([
                    'vars' => [
                        'th_class' => 'w-1 text-center',
                        'td_class' => 'text-center',
                    ],
                ]),
        );
    }
}
