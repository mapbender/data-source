<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;

use Mapbender\DataSourceBundle\Entity\DataItem;

/**
 * Class PostgreSQL
 *
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class Oracle extends DoctrineBaseDriver
{
    /**
     * Transform result column names from lower case to upper
     *
     * @param        $rows         array Two dimensional array link
     * @param string $functionName function name to call for each field name
     */
    public static function transformColumnNames(&$rows)
    {
        $columnNames = array_keys(current($rows));
        foreach ($rows as &$row) {
            foreach ($columnNames as $name) {
                $row[strtolower($name)] = &$row[$name];
                unset($row[$name]);
            }
        }
    }

    /**
     * Convert results to Feature objects
     *
     * @param DataItem[] $rows
     * @return DataItem[]
     */
    public function prepareResults(&$rows, $srid = null)
    {
        // Transform Oracle result column names from upper to lower case
        self::transformColumnNames($rows);

        foreach ($rows as $key => &$row) {
            $row = $this->create($row, $srid);
        }

        return $rows;
    }
}