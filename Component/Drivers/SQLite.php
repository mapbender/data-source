<?php
namespace Mapbender\DataSourceBundle\Component\Drivers;

/**
 * @package Mapbender\DataSourceBundle\Component\Drivers
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class SQLite extends DoctrineBaseDriver
{
    /**
     * Get table fields
     *
     * Info: $schemaManager->listTableColumns($this->tableName) doesn't work if fields are geometries!
     *
     * @throws \Doctrine\DBAL\DBALException
     * @return array field names
     */
    public function getStoreFields()
    {
        $schemaManager = $this->connection->getDriver()->getSchemaManager($this->connection);
        $columns       = array();
        $sql = $schemaManager->getDatabasePlatform()->getListTableColumnsSQL($this->repository->getTableName(), $this->connection->getDatabase());
        $all           = $this->connection->fetchAll($sql);

        foreach ($all as $fieldInfo) {
            $columns[] = $fieldInfo["name"];
        }
        return $columns;
    }

    protected function loadColumnsMetaData($table)
    {
        // NOTE: cannot use Doctrine SchemaManager::listTableColumns. SchemaManager
        // destroys the distinction between a column with no default and a column
        // with a null default.
        $connection = $this->getConnection();
        $sql = $connection->getDatabasePlatform()->getListTableColumnsSQL($table);
        $columnMeta = array();
        /** @see \Doctrine\DBAL\Platforms\SqlitePlatform::getListTableColumnsSQL */
        /** @see \Doctrine\DBAL\Schema\SqliteSchemaManager::_getPortableTableColumnDefinition */
        foreach ($connection->executeQuery($sql) as $row) {
            $columnMeta[$row['name']] = array(
                'is_nullable' => !$row['notnull'],
                'has_default' => !empty($row['dflt_value']),
                'is_numeric' => !!preg_match('#int|float|double|real|decimal|numeric#i', $row['type']),
            );
        }
    }
}
