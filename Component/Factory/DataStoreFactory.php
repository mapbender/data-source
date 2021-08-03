<?php


namespace Mapbender\DataSourceBundle\Component\Factory;


use Mapbender\CoreBundle\Component\UploadsManager;
use Mapbender\DataSourceBundle\Component\DataStore;
use Mapbender\DataSourceBundle\Component\EventProcessor;
use Mapbender\DataSourceBundle\Component\RepositoryRegistry;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Implementation for service id mbds.default_datastore_factory
 * @since 0.1.22
 */
class DataStoreFactory
{
    /** @var TokenStorageInterface */
    protected $tokenStorage;
    /** @var RequestStack */
    /** @todo 0.2.0: remove (used only by getFileUri et al) */
    protected $requestStack;
    /** @var EventProcessor */
    protected $eventProcessor;
    /** @todo 0.2.0: remove (used only by getFileUri et al) */
    /** @var UploadsManager */
    protected $uploadsManager;

    public function __construct(TokenStorageInterface $tokenStorage,
                                RequestStack $requestStack,
                                EventProcessor $eventProcessor,
                                UploadsManager $uploadsManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->requestStack = $requestStack;
        $this->eventProcessor = $eventProcessor;
        $this->uploadsManager = $uploadsManager;
    }

    /**
     * @param RepositoryRegistry $registry
     * @param array $config
     * @return DataStore
     */
    public function fromConfig(RepositoryRegistry $registry, array $config)
    {
        $fakeContainer = $this->buildContainer($registry);
        return new DataStore($fakeContainer, $config, $registry);
    }

    protected function buildContainer(RepositoryRegistry $registry)
    {
        $container = new Container(new FrozenParameterBag());
        $container->set('mapbender.uploads_manager.service', $this->uploadsManager);
        $container->set('security.token_storage', $this->tokenStorage);
        $container->set('request_stack', $this->requestStack);
        $container->set('mbds.default_event_processor', $this->eventProcessor);
        $container->set('data.source', $registry);
        return $container;
    }
}
