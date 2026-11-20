<?php

declare(strict_types=1);

namespace SKrull\SyliusAdminPasskeyLoginPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /**
     * @psalm-suppress UnusedVariable
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('s_krull_sylius_admin_passkey_login');
        $rootNode = $treeBuilder->getRootNode();

        return $treeBuilder;
    }
}
