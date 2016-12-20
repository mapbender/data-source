<?php
/**
 * Created by PhpStorm.
 * User: ransomware
 * Date: 04/10/16
 * Time: 08:04
 */

namespace Mapbender\DataSourceBundle\Entity;

use FOM\UserBundle\Entity\User;
use Mapbender\SearchBundle\Entity\QueryCondition;

class Query extends BaseConfiguration
{


    /* @var string*/
    protected $name;

    /* @var QueryCondition[]*/
    protected $conditions;

    /* @var User*/
    protected $user;

    /* @Var StyleMap */
    protected $styleMap;

    /* @var string*/
    protected $connectionName;

    /* @var string*/
    protected $schemaName;

    /* @var string*/
    protected $tableName;

    /* @var string*/
    protected $sql;

    /* @var string*/
    protected $where;






    /***
     * Getters and Setters
     */


    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return QueryCondition[]
     */
    public function getConditions()
    {
        return $this->conditions;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @return StyleMap
     */
    public function getStyleMap()
    {
        return $this->styleMap;
    }

    /**
     * @return string
     */
    public function getConnectionName()
    {
        return $this->connectionName;
    }

    /**
     * @return string
     */
    public function getSchemaName()
    {
        return $this->schemaName;
    }

    /**
     * @return string
     */
    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     * @return string
     */
    public function getWhere()
    {
        return $this->where;
    }

    /**
     * @return string
     */
    public function getSql()
    {
        return $this->sql;
    }

    /**
     * @param mixed $user
     * @return Query
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @param mixed $styleMap
     * @return Query
     */
    public function setStyleMap($styleMap)
    {
        $this->styleMap = $styleMap;
        return $this;
    }

    /**
     * @param mixed $connectionName
     * @return Query
     */
    public function setConnectionName($connectionName)
    {
        $this->connectionName = $connectionName;
        return $this;
    }

    /**
     * @param mixed $schemaName
     * @return Query
     */
    public function setSchemaName($schemaName)
    {
        $this->schemaName = $schemaName;
        return $this;
    }

    /**
     * @param mixed $tableName
     * @return Query
     */
    public function setTableName($tableName)
    {
        $this->tableName = $tableName;
        return $this;
    }

    /**
     * @param mixed $sql
     * @return Query
     */
    public function setSql($sql)
    {
        $this->sql = $sql;
        return $this;
    }

    /**
     * @param mixed $where
     * @return Query
     */
    public function setWhere($where)
    {
        $this->where = $where;
        return $this;
    }

    /**
     * @param mixed $name
     * @return Query
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @param mixed $conditions
     * @return Query
     */
    public function setConditions($conditions)
    {
        $this->conditions = $conditions;
        return $this;
    }

}