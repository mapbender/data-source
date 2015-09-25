<?php

namespace Mapbender\DigitizerBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Class MapbenderDataSourceExtension
 *
 * @package Mapbender\DigitizerBundle\DependencyInjection
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class MapbenderDataSourceExtension extends Extension
{

    /**
     * Loads a specific configuration.
     *
     * @param array            $configs
     * @param ContainerBuilder $container A ContainerBuilder instance
     *
     * @internal param array $config An array of configuration values
     * @api
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');
    }

    /**
     * @return string
     */
    public function getAlias()
    {
        return  'mapbender_data_source_extension_loader';
    }
}
