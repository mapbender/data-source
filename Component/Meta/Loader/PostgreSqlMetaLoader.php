<?php


namespace Mapbender\DataSourceBundle\Component\Meta\Loader;


use Mapbender\DataSourceBundle\Component\Meta\Column;
use Mapbender\DataSourceBundle\Component\Meta\TableMeta;

class PostgreSqlMetaLoader extends AbstractMetaLoader
{

    public function loadTableMeta($tableName)
    {
        // NOTE: cannot use Doctrine SchemaManager. SchemaManager will throw when encountering
        // geometry type columns. Internal SchemaManager Column metadata APIs are
        // closed to querying indivicial columns.
        $sql = $this->connection->getDatabasePlatform()->getListTableColumnsSQL($tableName);
        $columns = array();
        $aliases = array();
        /** @see \Doctrine\DBAL\Platforms\PostgreSqlPlatform::getListTableColumnsSQL */
        /** @see \Doctrine\DBAL\Schema\PostgreSqlSchemaManager::_getPortableTableColumnDefinition */
        foreach ($this->connection->executeQuery($sql) as $row) {
            $name = trim($row['field'], '"');   // Undo quote_ident
            if ($name !== $row['field']) {
                $aliases[$name] = $row['field'];
            }
            $notNull = !$row['isnotnull'];
            $hasDefault = !!$row['default'];
            $isNumeric = !!preg_match('#int|float|real|decimal|numeric#i', $row['complete_type']);
            $columns[$name] = new Column($notNull, $hasDefault, $isNumeric);
        }
        $tableMeta = new TableMeta($columns, $aliases);
        return $tableMeta;
    }
}
