<?php
namespace Mapbender\DataSourceBundle\Component;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class DataSource
 *
 * Data source manager.
 *
 * @package Mapbender\DataSourceBundle
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class DataStore extends ContainerAware
{

    /**
     * @var \IDriver $driver
     */
    protected $driver;

    /**
     * @param ContainerInterface $container
     * @param null               $args
     */
    public function __construct(ContainerInterface $container, $args = null)
    {
    }

    /**
     * @param $url
     */
    public function connect($url)
    {

    }
}