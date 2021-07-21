<?php
namespace Mapbender\DataSourceBundle\Component\Drivers\Interfaces;

use Mapbender\DataSourceBundle\Entity\DataItem;

interface Base
{
    /**
     * Is the driver connected an ready to interact?
     *
     * @return bool
     */
    public function isReady();

    /**
     * Has permission to read?
     *
     * @return bool
     * @todo: this information belongs in the DataStore or FeatureType, not here
     */
    public function canRead();

    /**
     * Has permission to write?
     *
     * @return bool
     * @todo: this information belongs in the DataStore or FeatureType, not here
     */
    public function canWrite();

    /**
     * Get platform name
     *
     * @return string
     */
    public function getPlatformName();
}
