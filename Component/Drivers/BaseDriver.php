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
    /**
     * @var array Driver settings
     */
    protected $settings;

    /**
     * @var array Field to select from the table
     */
    protected $fields = array();

    /**
     * @var mixed Unique id field name
     */
    protected $uniqueId = 'id';

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

    /**
     * @param int $uniqueId
     */
    public function setUniqueId($uniqueId)
    {
        $this->uniqueId = $uniqueId;
    }

    /**
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @param array $fields
     */
    public function setFields(array $fields)
    {
        $this->fields = $fields;
    }

    /**
     * Get unique ID
     *
     * @return mixed unique ID
     */
    public function getUniqueId()
    {
        return $this->uniqueId;
    }
}