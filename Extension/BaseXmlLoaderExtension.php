<?php
namespace Mapbender\DataSourceBundle\Extension;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Class BaseXmlLoaderExtension
 *
 * @package Mapbender\DataSourceBundle\Extension
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class BaseXmlLoaderExtension extends Extension
{

    protected $xmlFileName = 'services.xml';
    protected $xmlFilePath = '/../Resources/config';

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
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . $this->xmlFilePath));
        $loader->load($this->xmlFileName);
    }

    /**
     * @return string
     */
    public function getAlias()
    {
        list($prefix) = explode('Bundle\\', get_class($this));
        $alias = strtolower(preg_replace("/(.)([A-Z])/e", "'$1_'.strtolower('$2')", str_replace("\\", "", $prefix)));
        return $alias;
    }
}