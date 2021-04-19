<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;

use Mapbender\DataSourceBundle\Component\DataStore;
use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Base;

/**
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
abstract class BaseDriver implements Base
{
    /**
     * Only used for inflecting misplaced methods
     * @todo 0.2.0: remove attribute and all usages
     * @var DataStore
     */
    protected $repository;

    /**
     * @var array Field to select from the table
     */
    protected $fields = array();

    /**
     * @param DataStore $repository
     *
     * @todo 0.2.0: remove repository binding and all methods requiring repository inflection
     */
    public function __construct(DataStore $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return array
     *
     * @todo 0.2.0: remove repository binding and all methods requiring repository inflection
     */
    public function getFields()
    {
        return $this->repository->getFields();
    }
}
