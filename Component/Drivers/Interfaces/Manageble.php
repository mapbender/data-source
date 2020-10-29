<?php
namespace Mapbender\DataSourceBundle\Component\Drivers\Interfaces;

interface Manageble
{
    /**
     * Get database catalog names
     *
     * @return array
     */
    public function listDatabases();

    /**
     * Get database schema names
     *
     * @param string $dataBaseName
     * @return string[]
     */
    public function listSchemas($dataBaseName);

    /**
     * Get database table names
     *
     * @param string $schemaName
     * @return string[]
     */
    public function listTables($schemaName);

    /**
     * @param string $name
     * @param string $idColumn
     * @param bool   $dropBeforeCreate
     * @return int
     * @throws \Doctrine\DBAL\DBALException
     * @deprecated remove in 0.2.0 this is DBA work
     */
    public function createTable($name, $idColumn = 'id', $dropBeforeCreate = false);


    /**
     * @param string $name
     * @return int
     * @throws \Doctrine\DBAL\DBALException
     * @deprecated remove in 0.2.0 this is DBA work
     */
    public function dropTable($name);
}
