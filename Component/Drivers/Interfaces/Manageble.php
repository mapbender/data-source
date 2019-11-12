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
     * @param $dataBaseName
     * @return array
     */
    public function listSchemas($dataBaseName);

    /**
     * Get database table names
     *
     * @param $schemaName
     * @return array
     */
    public function listTables($schemaName);

    /**
     * @param        $name
     * @param string $idColumn
     * @param bool   $dropBeforeCreate
     * @return bool
     * @throws \Doctrine\DBAL\DBALException
     */
    public function createTable($name, $idColumn = 'id', $dropBeforeCreate = false);


    /**
     * @param $name
     * @return int
     * @throws \Doctrine\DBAL\DBALException
     */
    public function dropTable($name);

    /**
     * Get last insert id
     *
     * @return int
     */
    public function getLastInsertId();
}
