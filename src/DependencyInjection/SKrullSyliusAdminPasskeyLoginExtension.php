<?php

declare(strict_types=1);

namespace SKrull\SyliusAdminPasskeyLoginPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class SKrullSyliusAdminPasskeyLoginExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

        $loader->load('services.php');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependDoctrineMapping($container);
        $this->prependDoctrineMigrations($container);
        $this->prependWebauthn($container);
    }

    private function prependWebauthn(ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config/packages'), prepend: true);

        $loader->load('webauthn.yaml');
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
