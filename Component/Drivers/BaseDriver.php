<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;

use Mapbender\DataSourceBundle\Component\DataStore;

/**
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
abstract class BaseDriver
{
    /**
     * Only used for inflecting misplaced methods
     * @todo 0.2.0: remove attribute and all usages
     * @var DataStore
     */
    protected $repository;

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
     * @param mixed $x
     * @return mixed
     * @deprecated returns unchanged input value; remove invocations
     * @todo: 0.2.0: remove this method (breaks mapbender/search)
     */
    final public static function roundGeometry($x)
    {
        return $x;
    }
}
