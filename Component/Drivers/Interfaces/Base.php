<?php
namespace Mapbender\DataSourceBundle\Component\Drivers\Interfaces;

use Mapbender\DataSourceBundle\Entity\DataItem;

interface Base
{
    /**
     * Get object by id, array or object himself
     *
     * @param mixed $id
     * @return array
     * @internal param string $fieldName
     */
    public function get($id);

    /**
     * Get object by ID and field name
     *
     * @param mixed $id ID
     * @return DataItem
     */
    public function getById($id);

    /**
     * Cast DataItem by $args
     *
     * @param mixed $args
     * @return DataItem
     */
    public function create($args);

    /**
     * Save the data
     *
     * @param DataItem $data
     * @param boolean  $autoUpdate Create if item doesn't exits
     * @return mixed
     */
    public function save($data, $autoUpdate = true);

    /**
     * Remove by args
     *
     * @param $args
     * @return mixed
     */
    public function remove($args);

    /**
     * Connect to the source
     *
     * @param $url
     * @return mixed
     */
    public function connect($url);

    /**
     * Is the driver connected an ready to interact?
     *
     * @return bool
     */
    public function isReady();

    /**
     * Has permission to read?
     *
     * @return bool
     */
    public function canRead();

    /**
     * Has permission to write?
     *
     * @return bool
     */
    public function canWrite();

    /**
     * Get platform name
     *
     * @return string
     */
    public function getPlatformName();

    /**
     * @param array $criteria
     * @return mixed
     */
    public function search(array $criteria);
}