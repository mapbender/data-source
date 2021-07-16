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
}
