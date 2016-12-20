<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;

use Eslider\Driver\SqliteExtended;
use Mapbender\DataSourceBundle\Component\Drivers\Interfaces\Base;
use Mapbender\DataSourceBundle\Entity\DataItem;

/**
 * Class SQLite
 *
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class SQLite extends SqliteExtended implements Base
{
    /**
     * @inheritdoc
     */
    public function getVersion()
    {
        $this->fetchColumn("select sqlite_version()");
    }

    /**
     * Get object by id, array or object himself
     *
     * @param mixed $id
     * @return array|void
     */
    public function get($id)
    {
        // TODO: Implement get() method.
    }

    /**
     * Get object by ID and field name
     *
     * @param mixed $id ID
     */
    public function getById($id)
    {
        // TODO: Implement getById() method.
    }

    /**
     * Cast DataItem by $args
     *
     * @param mixed $args
     * @return DataItem
     */
    public function create($args)
    {
        // TODO: Implement create() method.
    }

    /**
     * Save the data
     *
     * @param DataItem $data
     * @param boolean  $autoUpdate Create if item doesn't exits
     * @return mixed
     */
    public function save($data, $autoUpdate = true)
    {
        // TODO: Implement save() method.
    }

    /**
     * Remove by args
     *
     * @param $args
     * @return mixed
     */
    public function remove($args)
    {
        // TODO: Implement remove() method.
    }

    /**
     * Connect to the source
     *
     * @param $url
     * @return mixed
     */
    public function connect($url)
    {
        // TODO: Implement connect() method.
    }

    /**
     * Is the driver connected an ready to interact?
     *
     * @return bool
     */
    public function isReady()
    {
        // TODO: Implement isReady() method.
    }

    /**
     * Has permission to read?
     *
     * @return bool
     */
    public function canRead()
    {
        // TODO: Implement canRead() method.
    }

    /**
     * Has permission to write?
     *
     * @return bool
     */
    public function canWrite()
    {
        // TODO: Implement canWrite() method.
    }

    /**
     * Get platform name
     *
     * @return string
     */
    public function getPlatformName()
    {
        // TODO: Implement getPlatformName() method.
    }

    /**
     * @param array $criteria
     * @param bool  $autoUpdate
     * @return mixed
     */
    public function search(array $criteria, $autoUpdate = true)
    {
        // TODO: Implement search() method.
    }
}