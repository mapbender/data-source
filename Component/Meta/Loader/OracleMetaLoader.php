<?php


namespace Mapbender\DataSourceBundle\Component\Meta\Loader;


use Mapbender\DataSourceBundle\Component\Meta\Column;
use Mapbender\DataSourceBundle\Component\Meta\TableMeta;

class OracleMetaLoader extends AbstractMetaLoader
{
    protected function loadTableMeta($tableName)
    {
        // NOTE: cannot use Doctrine SchemaManager. SchemaManager will throw when encountering
        // geometry type columns. Internal SchemaManager Column metadata APIs are
        // closed to querying individual columns.
        $sql = $this->connection->getDatabasePlatform()->getListTableColumnsSQL($tableName);
        $columns = array();
        $aliases = array();
        /** @see \Doctrine\DBAL\Platforms\OraclePlatform::getListTableColumnsSQL */
        /** @see \Doctrine\DBAL\Schema\OracleSchemaManager::_getPortableTableColumnDefinition */
        foreach ($this->connection->executeQuery($sql) as $row) {
            $name = $row['column_name'];
            $aliases[$name] = strtolower($name);
            $notNull = $row['nullable'] === 'N';
            $hasDefault = !!$row['data_default'];
            $isNumeric = !!preg_match('#int|float|real|decimal|numeric#i', $row['data_type']);
            $columns[$name] = new Column($notNull, $hasDefault, $isNumeric);
        }
        $tableMeta = new TableMeta($columns, $aliases);
        return $tableMeta;
    }
}
