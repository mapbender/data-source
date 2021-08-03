<?php
namespace Mapbender\DataSourceBundle\Component;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Features service handles feature types
 *
 * @author    Andriy Oblivantsev <eslider@gmail.com>
 * @copyright 18.03.2015 by WhereGroup GmbH & Co. KG
 *
 * @method FeatureType getDataStoreByName(string $name)
 * @method FeatureType get(string $name)
 * @method FeatureType dataStoreFactory(array $config)
 * @property FeatureType[] $repositories
 */
class FeatureTypeService extends DataStoreService
{
    protected $factoryId = 'mbds.default_featuretype_factory';

    /**
     * @param ContainerInterface $container
     * @param mixed[][]|string $declarations array of feature type configs OR container param key OR file name
     */
    public function __construct(ContainerInterface $container, $declarations)
    {
        if ((!$declarations && !\is_array($declarations)) || (\is_string($declarations) && false !== strpos($declarations, '/'))) {
            if (!$declarations) {
                $declarations = $container->getParameter('kernel.root_dir') . '/config/featureTypes.yaml';
            }
            if (@\file_exists($declarations)) {
                @trigger_error("DEPRECATED: Loading featureType config from a standalone file ({$declarations}) is deprecated; pass the config array.", E_USER_DEPRECATED);
                $declarations = Yaml::parse(\file_get_contents($declarations));
            } else {
                throw new \RuntimeException("Cannot access file {$declarations}");
            }
        }
        parent::__construct($container, $declarations);
    }

    /**
     * Alias for getDataStoreByName
     *
     * @param string $name
     * @return FeatureType
     * @since 0.1.15
     * @deprecated use aliased method directly
     * aliased @since 0.1.22
     */
    public function getFeatureTypeByName($name)
    {
        return $this->getDataStoreByName($name);
    }

    /**
     * Alias for dataStoreFactory
     *
     * @param mixed[] $config
     * @return FeatureType
     * @since 0.1.15
     * @deprecated use aliased method directly
     * aliased @since 0.1.22
     */
    public function featureTypeFactory(array $config)
    {
        return $this->dataStoreFactory($config);
    }

    /**
     * Search feature types
     *
     * @return FeatureType[]
     * @deprecated
     * @todo 0.2.0: remove this method
     */
    public function search()
    {
        foreach ($this->repositoryConfigs as $id => $declaration) {
            if (empty($this->repositories[$id])) {
                $this->repositories[$id] = $this->dataStoreFactory($declaration);
            }
        }
        return $this->repositories;
    }

    /**
     * Alias for getDataStoreDeclarations
     *
     * @return array
     * @deprecated use aliased method directly
     * aliased @since 0.1.22
     */
    public function getFeatureTypeDeclarations()
    {
        return $this->getDataStoreDeclarations();
    }
}
