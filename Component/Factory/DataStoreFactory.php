<?php


namespace Mapbender\DataSourceBundle\Component\Factory;


use Mapbender\DataSourceBundle\Component\DataStore;
use Mapbender\DataSourceBundle\Component\EventProcessor;
use Mapbender\DataSourceBundle\Component\RepositoryRegistry;
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

    /**
     * @param RepositoryRegistry $registry
     * @param array $config
     * @return DataStore
     */
    public function fromConfig(RepositoryRegistry $registry, array $config)
    {
        $config += $this->getConfigDefaults();
        $connection = $registry->getDbalConnectionByName($config['connection']);
        return new DataStore($connection, $this->tokenStorage, $this->eventProcessor, $config);
    }

    protected function getConfigDefaults()
    {
        return array(
            'uniqueId' => 'id',
            'connection' => 'default',
            'fields' => null,
        );
    }
}
