<?php


namespace Mapbender\DataSourceBundle\Component\Meta\Loader;


use Doctrine\DBAL\DBALException;
use Mapbender\DataSourceBundle\Component\Meta\Column;
use Mapbender\DataSourceBundle\Component\Meta\TableMeta;

class PostgreSqlMetaLoader extends AbstractMetaLoader
{

    public function loadTableMeta($tableName)
    {
        // NOTE: cannot use Doctrine SchemaManager. SchemaManager will throw when encountering
        // geometry type columns. Internal SchemaManager Column metadata APIs are
        // closed to querying individual columns.
        $platform = $this->connection->getDatabasePlatform();
        $gcSql = 'SELECT f_geometry_column, srid, type FROM "public"."geometry_columns"'
               . ' WHERE f_table_name = ?'
        ;
        $gcParams = array();
        if (false !== strpos($tableName, ".")) {
            $tableNameParts = explode('.', $tableName, 2);
            $gcParams[] = $tableNameParts[1];
            $gcSql .= ' AND "f_table_schema" = ?';
            $gcParams[] = $tableNameParts[0];
        } else {
            $gcParams[] = $tableName;
            $gcSql .= ' AND "f_table_schema" = current_schema()';
        }
        $gcInfos = array();
        try {
            foreach ($this->connection->executeQuery($gcSql, $gcParams) as $row) {
                $gcInfos[$row['f_geometry_column']] = array($row['type'], $row['srid']);
            }
        } catch (DBALException $e) {
            // Ignore (DataStore on PostgreSQL / no Postgis)
        }

        $sql = $platform->getListTableColumnsSQL($tableName);
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
            if (!empty($gcInfos[$name])) {
                $geomType = $gcInfos[$name][0];
                $srid = $gcInfos[$name][1];
            } else {
                $geomType = $srid = null;
            }

            $columns[$name] = new Column($notNull, $hasDefault, $isNumeric, $geomType, $srid);
        }
        $tableMeta = new TableMeta($columns, $aliases);
        return $tableMeta;
    }
}
