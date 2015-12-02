<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class BaseDriver
 *
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
abstract class BaseDriver extends ContainerAware
{
    /** @var array Driver settings */
    public $settings;

    /**
     * BaseDriver constructor.
     *
     * @param ContainerInterface $container
     * @param array              $settings
     */
    public function __construct(ContainerInterface $container, array $settings = array())
    {
        $this->setContainer($container);
        $this->settings = $settings;
    }
}