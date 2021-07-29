<?php


namespace Mapbender\DataSourceBundle\Component\Factory;


use Mapbender\DataSourceBundle\Component\FeatureType;
use Mapbender\DataSourceBundle\Component\RepositoryRegistry;

class FeatureTypeFactory extends DataStoreFactory
{
    public function fromConfig(RepositoryRegistry $registry, array $config)
    {
        $fakeContainer = $this->buildContainer($registry);
        return new FeatureType($fakeContainer, $config, $registry);
    }
}
