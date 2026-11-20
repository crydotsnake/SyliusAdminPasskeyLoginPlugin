<?php

declare(strict_types=1);

namespace SKrull\SyliusAdminPasskeyLoginPlugin\DependencyInjection;

use SKrull\SyliusAdminPasskeyLoginPlugin\Repository\AdminPasskeyRepository;
use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Webauthn\Bundle\Security\Guesser\CurrentUserEntityGuesser;

final class SKrullSyliusAdminPasskeyLoginExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

        $loader->load('services.php');
    }

    public const WEBAUTHN_PROFILE = 'skrull_admin_passkey';

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependDoctrineMapping($container);
        $this->prependDoctrineMigrations($container);
        $this->prependWebauthn($container);
    }

    private function prependWebauthn(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('webauthn', [
            'credential_repository' => AdminPasskeyRepository::class,
            'user_repository' => 'skrull_sylius_admin_passkey_login.repository.admin_passkey_user_entity',

            'creation_profiles' => [
                self::WEBAUTHN_PROFILE => [
                    'rp' => ['name' => 'Sylius Administration'],
                    'authenticator_selection_criteria' => [
                        'resident_key' => 'required',
                        'user_verification' => 'required',
                    ],
                    'attestation_conveyance' => 'none',
                    'public_key_credential_parameters' => [-7, -257],
                ],
            ],
            'request_profiles' => [
                self::WEBAUTHN_PROFILE => [
                    'user_verification' => 'required',
                ],
            ],

            'controllers' => [
                'enabled' => true,
                'creation' => [
                    self::WEBAUTHN_PROFILE => [
                        'options_path' => '/%sylius_admin.path_name%/passkeys/options',
                        'result_path' => '/%sylius_admin.path_name%/passkeys',
                        'profile' => self::WEBAUTHN_PROFILE,
                        'user_entity_guesser' => CurrentUserEntityGuesser::class,
                        'success_handler' => 'skrull_sylius_admin_passkey_login.security.passkey_creation_success_handler',
                    ],
                ],
            ],
        ]);
    }

    private function prependDoctrineMapping(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'SKrullSyliusAdminPasskeyLoginPlugin' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => \dirname(__DIR__) . '/Entity',
                        'prefix' => 'SKrull\SyliusAdminPasskeyLoginPlugin\Entity',
                        'alias' => 'SKrullSyliusAdminPasskeyLoginPlugin',
                    ],
                ],
            ],
        ]);
    }

    protected function getMigrationsNamespace(): string
    {
        return 'SKrull\SyliusAdminPasskeyLoginPlugin\Migrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@SKrullSyliusAdminPasskeyLoginPlugin/src/Migrations';
    }

    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }
}
