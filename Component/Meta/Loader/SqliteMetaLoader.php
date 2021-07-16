<?php


namespace Mapbender\DataSourceBundle\Component\Meta\Loader;


use Mapbender\DataSourceBundle\Component\Meta\Column;
use Mapbender\DataSourceBundle\Component\Meta\TableMeta;

class SqliteMetaLoader extends AbstractMetaLoader
{
    public function loadTableMeta($tableName)
    {
        // NOTE: cannot use Doctrine SchemaManager::listTableColumns. SchemaManager
        // destroys the distinction between a column with no default and a column
        // with a null default.
        $sql = $this->connection->getDatabasePlatform()->getListTableColumnsSQL($tableName);
        $columns = array();
        /** @see \Doctrine\DBAL\Platforms\SqlitePlatform::getListTableColumnsSQL */
        /** @see \Doctrine\DBAL\Schema\SqliteSchemaManager::_getPortableTableColumnDefinition */
        foreach ($this->connection->executeQuery($sql) as $row) {
            $isNullable = !$row['notnull'];
            $hasDefault = !empty($row['dflt_value']);
            $isNumeric = !!preg_match('#int|float|double|real|decimal|numeric#i', $row['type']);
            $columns[$row['name']] = new Column($isNullable, $hasDefault, $isNumeric);
        }
        $tableMeta = new TableMeta($columns);
        return $tableMeta;
    }
}
