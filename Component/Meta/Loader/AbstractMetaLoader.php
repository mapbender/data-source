<?php


namespace Mapbender\DataSourceBundle\Component\Meta\Loader;


use Doctrine\DBAL\Connection;
use Mapbender\DataSourceBundle\Component\Meta\TableMeta;

abstract class AbstractMetaLoader
{
    /** @var Connection */
    protected $connection;
    /** @var TableMeta[] */
    protected $tables = array();

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param string $tableName
     * @return TableMeta
     */
    public function getTableMeta($tableName)
    {
        if (empty($this->tables[$tableName])) {
            $this->tables[$tableName] = $this->loadTableMeta($tableName);
        }
        return $this->tables[$tableName];
    }

    /**
     * @param string $tableName
     * @return TableMeta
     */
    abstract protected function loadTableMeta($tableName);
}
