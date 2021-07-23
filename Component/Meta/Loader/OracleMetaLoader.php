<?php


namespace Mapbender\DataSourceBundle\Component\Meta\Loader;


use Doctrine\DBAL\DBALException;
use Mapbender\DataSourceBundle\Component\Meta\Column;
use Mapbender\DataSourceBundle\Component\Meta\TableMeta;

class OracleMetaLoader extends AbstractMetaLoader
{
    protected function loadTableMeta($tableName)
    {
        // NOTE: cannot use Doctrine SchemaManager. SchemaManager will throw when encountering
        // geometry type columns. Internal SchemaManager Column metadata APIs are
        // closed to querying individual columns.
        $platform = $this->connection->getDatabasePlatform();
        $sql = $platform->getListTableColumnsSQL($tableName);

        $gmdSql = 'SELECT COLUMN_NAME, SRID FROM ALL_SDO_GEOM_METADATA'
                . ' WHERE TABLE_NAME = ' . $platform->quoteIdentifier($tableName)
        ;
        $srids = array();
        try {
            foreach ($this->connection->executeQuery($gmdSql) as $row) {
                $srids[$row['COLUMN_NAME']] = $row['SRID'];
            }
        } catch (DBALException $e) {
            // Ignore (no spatial support?)
        }

        $columns = array();
        $aliases = array();
        /** @see \Doctrine\DBAL\Platforms\OraclePlatform::getListTableColumnsSQL */
        /** @see \Doctrine\DBAL\Schema\OracleSchemaManager::_getPortableTableColumnDefinition */
        foreach ($this->connection->executeQuery($sql) as $row) {
            $name = $row['column_name'];
            if (!empty($srids[\strtoupper($name)])) {
                $srid = $srids[\strtoupper($name)];
            } else {
                $srid = null;
            }

            $aliases[$name] = strtolower($name);
            $notNull = $row['nullable'] === 'N';
            $hasDefault = !!$row['data_default'];
            $isNumeric = !!preg_match('#int|float|real|decimal|numeric#i', $row['data_type']);
            $columns[$name] = new Column($notNull, $hasDefault, $isNumeric, null, $srid);
        }
        $tableMeta = new TableMeta($columns, $aliases);
        return $tableMeta;
    }
}
