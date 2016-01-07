<?php
/**
 *
 * @author Andriy Oblivantsev <eslider@gmail.com>
 */

namespace Mapbender\DataSourceBundle\Element;

use Mapbender\CoreBundle\Component\Application;
use Mapbender\CoreBundle\Element\HTMLElement;
use Mapbender\CoreBundle\Entity\Element;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class DataStoreElement
 *
 * @package Mapbender\DataSourceBundle\Element
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class DataStoreElement extends HTMLElement
{
    /**
     * The constructor. Every element needs an application to live within and
     * the container to do useful things.
     *
     * @param Application        $application The application object
     * @param ContainerInterface $container   The container object
     * @param Element            $entity
     */
    public function __construct(Application $application, ContainerInterface $container, Element $entity)
    {
        parent::__construct($application, $container, $entity);
    }
}