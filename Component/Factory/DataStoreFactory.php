<?php


namespace Mapbender\DataSourceBundle\Component\Factory;


use Mapbender\DataSourceBundle\Component\DataStore;
use Mapbender\DataSourceBundle\Component\EventProcessor;
use Mapbender\DataSourceBundle\Component\RepositoryRegistry;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Implementation for service id mbds.default_datastore_factory
 * @since 0.1.22
 */
class DataStoreFactory
{
    /** @var TokenStorageInterface */
    protected $tokenStorage;
    /** @var EventProcessor */
    protected $eventProcessor;

    public function __construct(TokenStorageInterface $tokenStorage,
                                EventProcessor $eventProcessor)
    {
        $this->tokenStorage = $tokenStorage;
        $this->eventProcessor = $eventProcessor;
    }

    public function fromConfig(RepositoryRegistry $registry, array $config)
    {
        $fakeContainer = $this->buildContainer($registry);
        return new DataStore($fakeContainer, $config, $registry);
    }

    protected function buildContainer(RepositoryRegistry $registry)
    {
        $container = new Container(new FrozenParameterBag());
        $container->set('security.token_storage', $this->tokenStorage);
        $container->set('mbds.default_event_processor', $this->eventProcessor);
        $container->set('data.source', $registry);
        return $container;
    }
}
