<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;

use Mapbender\DataSourceBundle\Entity\DataItem;

/**
 * Class PostgreSQL
 *
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class PostgreSQL extends DoctrineBaseDriver
{
    /**
     * Insert data item
     *
     * @param array|DataItem $item
     * @return DataItem
     */
    public function insert($item)
    {
        $dataItem = parent::insert($item);
        if ($dataItem->getId() < 1) {
            $lastId = $this->connection->fetchColumn("SELECT
                currval(
                    pg_get_serial_sequence('" . $this->tableName
                . "','" . $this->getUniqueId() . "')
                )");
            $dataItem->setId($lastId);
        }
        return $dataItem;
    }
}