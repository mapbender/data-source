<?php
namespace Mapbender\DataSourceBundle\Component;

use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @author    Andriy Oblivantsev <eslider@gmail.com>
 *
 * @method DataStore getDataStoreByName(string $name)
 */
class DataStoreService extends RepositoryRegistry
{
    /** @var ContainerInterface */
    protected $container;

    /**
     * @param ContainerInterface $container
     * @param mixed[][]|string $declarations repository configs, or container param key for lookup
     */
    public function __construct(ContainerInterface $container, $declarations = 'dataStores')
    {
        /** @var RegistryInterface $registry */
        $registry = $container->get('doctrine');
        /** @var EventProcessor $eventProcessor */
        $eventProcessor = $container->get('mbds.default_event_processor');
        $declarations = $declarations ?: array();
        if ($declarations && \is_string($declarations)) {
            $declarations = $container->getParameter($declarations);
        }

        parent::__construct($registry, $eventProcessor, $declarations ?: array());

        $this->container = $container;
    }

    /**
     * Get store by name
     *
     * @param string $name
     * @return DataStore
     */
    public function get($name)
    {
        return $this->getDataStoreByName($name);
    }

    /**
     * @param mixed[] $config
     * @return DataStore
     * @since 0.1.15
     */
    public function dataStoreFactory(array $config)
    {
        // @todo: stop injecting full container into DataStore
        return new DataStore($this->container, $config, $this);
    }

    /**
     * @return array
     * @deprecated remove in 0.2.0; you can't really do anything with the return value anyway
     */
    public function listDrivers()
    {
        return array(
            'SQLite',
            'PostgreSQL',
            'YAML',
            'JSON'
        );
    }
}
