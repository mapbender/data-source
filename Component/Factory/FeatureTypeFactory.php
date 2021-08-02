<?php


namespace Mapbender\DataSourceBundle\Component\Factory;


use Mapbender\DataSourceBundle\Component\FeatureType;
use Mapbender\DataSourceBundle\Component\RepositoryRegistry;

/**
 * Implementation for service id mbds.default_featuretype_factory
 * @since 0.1.22
 */
class FeatureTypeFactory extends DataStoreFactory
{
    public function fromConfig(RepositoryRegistry $registry, array $config)
    {
        $config += $this->getConfigDefaults();
        $connection = $registry->getDbalConnectionByName($config['connection']);
        return new FeatureType($connection, $this->tokenStorage, $this->eventProcessor, $config);
    }
}
