<?php
/**
 * Created by PhpStorm.
 * User: ransomware
 * Date: 04/10/16
 * Time: 10:56
 */

namespace Mapbender\DataSourceBundle\Element;

use Mapbender\DataSourceBundle\Entity\Query;

class GeoQueryBuilder
{

    public function getQueries($userId)
    {
        //TODO: implement me
    }


    public function saveQuery($query)
    {
        //TODO: implement me

    }


    public function removeQuery($queryId)
    {        //TODO: implement me

    }

    public function executeQuery($query)
    {
        //TODO: implement me

    }

    private function isValidQuery($query){
        //TODO: implement me
    }

    public function exportQuery($queryId, $formatType)
    {
        //TODO: implement me
    }


}