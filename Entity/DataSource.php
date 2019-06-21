<?php
    
    
    namespace Mapbender\DataSourceBundle\Entity;
    use Doctrine\ORM\Mapping as ORM;
    
    class DataSource
    {
        protected $id;
        protected $host;
        protected $port;
        protected $table;
        protected $geomField;
    }