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
        $fakeContainer = $this->buildContainer($registry);
        return new FeatureType($fakeContainer, $config, $registry);
    }
}
