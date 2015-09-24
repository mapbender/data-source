<?php
namespace Mapbender\DataSourceBundle\Component;

use Mapbender\DataSourceBundle\Component\Driver\IDriver;
use Mapbender\DataSourceBundle\Entity\DataItem;
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
     * @var IDriver $driver
     */
    protected $driver;

    /**
     * @param ContainerInterface $container
     * @param null               $args
     */
    public function __construct(ContainerInterface $container, $args = null)
    {
        $this->driver = new $args["driver"];
    }

    /**
     * @param $url
     * @return mixed
     */
    public function connect($url)
    {
        return $this->getDriver()->connect($url);
    }

    /**
     * @param $id
     * @return DataItem
     */
    public function getById($id)
    {
        return $this->getDriver()->get($id);
    }

    /**
     * Get parent by child ID
     *
     * @param $id
     * @return DataItem
     */
    public function getParent($id)
    {
        return new DataItem();
    }

    /**
     * Get children ID
     *
     * @param $id
     * @return DataItem[]
     */
    public function getChildren($id)
    {
        return new DataItem();
    }

    /**
     * Convert array to DataItem object
     *
     * @param $data
     * @return DataItem
     */
    public function create($data)
    {
        return new DataItem($data);
    }

    /**
     * Save data item
     *
     * @param DataItem $dataItem
     * @return DataItem
     * @internal param DataItem $data
     */
    public function save(DataItem $dataItem)
    {
        return $this->getDriver()->save($dataItem);
    }

    /**
     * Remove data item
     *
     * @param $id
     * @return bool
     */
    public function remove($id)
    {
        return $this->getDriver()->remove($id);
    }

    /**
     * Search by criteria
     *
     * @param array $criteria
     * @return DataItem[]
     */
    public function search(array $criteria)
    {
        return $this->getDriver()->search($criteria);
    }

    /**
     * Get current driver instance
     *
     * @return IDriver
     */
    public function getDriver()
    {
        return $this->driver;
    }

}