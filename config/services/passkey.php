<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use SKrull\SyliusAdminPasskeyLoginPlugin\Checker\AdminPasskeyChecker;
use SKrull\SyliusAdminPasskeyLoginPlugin\Controller\DeletePasskeyAction;
use SKrull\SyliusAdminPasskeyLoginPlugin\Controller\RenamePasskeyAction;
use SKrull\SyliusAdminPasskeyLoginPlugin\Form\Type\PasskeyRenameType;
use SKrull\SyliusAdminPasskeyLoginPlugin\Grid\AdminUserGridMutator;
use SKrull\SyliusAdminPasskeyLoginPlugin\Repository\AdminPasskeyRepository;
use SKrull\SyliusAdminPasskeyLoginPlugin\Repository\AdminPasskeyUserEntityRepository;
use SKrull\SyliusAdminPasskeyLoginPlugin\Security\PasskeyAuthenticationSuccessHandler;
use SKrull\SyliusAdminPasskeyLoginPlugin\Security\PasskeyCreationSuccessHandler;
use SKrull\SyliusAdminPasskeyLoginPlugin\Security\PasskeyUserLoaderListener;
use SKrull\SyliusAdminPasskeyLoginPlugin\Security\UserHandleGenerator;
use SKrull\SyliusAdminPasskeyLoginPlugin\Twig\AdminPasskeyExtension;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('skrull_sylius_admin_passkey_login.security.user_handle_generator', UserHandleGenerator::class)
        ->args([
            param('kernel.secret'),
        ])
    ;
    $services->alias(UserHandleGenerator::class, 'skrull_sylius_admin_passkey_login.security.user_handle_generator');

    $services->set(AdminPasskeyRepository::class)
        ->args([
            service('doctrine'),
            service('security.helper'),
            service('skrull_sylius_admin_passkey_login.security.user_handle_generator'),
            service('clock'),
        ])
        ->tag('doctrine.repository_service')
    ;
    $services->alias('skrull_sylius_admin_passkey_login.repository.admin_passkey', AdminPasskeyRepository::class);

    $services->set('skrull_sylius_admin_passkey_login.repository.admin_passkey_user_entity', AdminPasskeyUserEntityRepository::class)
        ->args([
            service('sylius.repository.admin_user'),
            service('skrull_sylius_admin_passkey_login.repository.admin_passkey'),
            service('skrull_sylius_admin_passkey_login.security.user_handle_generator'),
            service('sylius.canonicalizer'),
        ])
    ;
    $services->alias(AdminPasskeyUserEntityRepository::class, 'skrull_sylius_admin_passkey_login.repository.admin_passkey_user_entity');

    $services->set('skrull_sylius_admin_passkey_login.security.passkey_creation_success_handler', PasskeyCreationSuccessHandler::class)
        ->args([
            service('skrull_sylius_admin_passkey_login.repository.admin_passkey'),
            service('doctrine.orm.entity_manager'),
            service('security.helper'),
        ])
    ;

    $services->set('skrull_sylius_admin_passkey_login.security.passkey_authentication_success_handler', PasskeyAuthenticationSuccessHandler::class)
        ->args([
            service('security.helper'),
            service('router'),
        ])
    ;

    $services->set('skrull_sylius_admin_passkey_login.security.passkey_user_loader_listener', PasskeyUserLoaderListener::class)
        ->args([
            service('skrull_sylius_admin_passkey_login.repository.admin_passkey'),
        ])
        ->tag('kernel.event_subscriber')
    ;

    $services->set('skrull_sylius_admin_passkey_login.controller.delete_passkey', DeletePasskeyAction::class)
        ->args([
            service('skrull_sylius_admin_passkey_login.repository.admin_passkey'),
            service('doctrine.orm.entity_manager'),
            service('security.helper'),
            service('security.csrf.token_manager'),
        ])
        ->tag('controller.service_arguments')
    ;

    $services->set('skrull_sylius_admin_passkey_login.controller.rename_passkey', RenamePasskeyAction::class)
        ->args([
            service('skrull_sylius_admin_passkey_login.repository.admin_passkey'),
            service('doctrine.orm.entity_manager'),
            service('security.helper'),
            service('form.factory'),
            service('router'),
            service('twig'),
        ])
        ->tag('controller.service_arguments')
    ;

    $services->set('skrull_sylius_admin_passkey_login.form.type.passkey_rename', PasskeyRenameType::class)
        ->tag('form.type')
    ;

    $services->set('skrull_sylius_admin_passkey_login.checker.admin_passkey', AdminPasskeyChecker::class)
        ->args([
            service('skrull_sylius_admin_passkey_login.repository.admin_passkey'),
        ])
        ->tag('kernel.reset', ['method' => 'reset'])
    ;
    $services->alias(AdminPasskeyChecker::class, 'skrull_sylius_admin_passkey_login.checker.admin_passkey');

    $services->set('skrull_sylius_admin_passkey_login.grid.admin_user_grid_mutator', AdminUserGridMutator::class)
        ->tag('sylius.grid_mutator', ['grid' => AdminUserGridMutator::GRID])
    ;

    $services->set('skrull_sylius_admin_passkey_login.twig.admin_passkey_extension', AdminPasskeyExtension::class)
        ->args([
            service('skrull_sylius_admin_passkey_login.repository.admin_passkey'),
            service('skrull_sylius_admin_passkey_login.checker.admin_passkey'),
            service('router'),
        ])
        ->tag('twig.extension')
    ;
};
