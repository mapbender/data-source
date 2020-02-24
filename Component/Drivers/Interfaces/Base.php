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
     */
    public function get($id);

    /**
     * Get object by ID and field name
     *
     * @param mixed $id ID
     * @return DataItem
     * @deprecated only used by / only works for DataStore (doesn't pass srid to FeatureType)
     * @todo 0.2.0: remove this method
     */
    public function getById($id);

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
     * @param DataItem|array|int $args
     * @return integer
     */
    public function remove($args);

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
     * @todo: this information belongs in the DataStore or FeatureType, not here
     */
    public function canRead();

    /**
     * Has permission to write?
     *
     * @return bool
     * @todo: this information belongs in the DataStore or FeatureType, not here
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
