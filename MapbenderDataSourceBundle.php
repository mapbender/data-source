<?php
namespace Mapbender\DataSourceBundle;

use Mapbender\CoreBundle\Component\MapbenderBundle;

/**
 * DataSource Bundle.
 * y
 * @author Andriy Oblivantsev
 */
class MapbenderDataSourceBundle extends MapbenderBundle
{
    /**
     * @inheritdoc
     */
    public function getElements()
    {
        return array(
            'Mapbender\DataSourceBundle\Element\DataManagerElement',
            'Mapbender\DataSourceBundle\Element\QueryBuilderElement'
        );
    }
}
