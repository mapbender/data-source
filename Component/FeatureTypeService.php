<?php
namespace Mapbender\DataSourceBundle\Component;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Features service handles feature types
 *
 * @author    Andriy Oblivantsev <eslider@gmail.com>
 * @copyright 18.03.2015 by WhereGroup GmbH & Co. KG
 */
class FeatureTypeService extends DataStoreService
{
    /** @var FeatureType[] */
    protected $featureTypes;

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
     * Get feature type by name
     *
     * @param string $name
     * @return FeatureType
     */
    public function get($name)
    {
        return $this->getFeatureTypeByName($name);
    }

    /**
     * @param string $name
     * @return FeatureType
     * @since 0.1.15
     */
    public function getFeatureTypeByName($name)
    {
        if (empty($this->featureTypes[$name])) {
            $declarations = $this->repositoryConfigs;
            if (empty($declarations[$name])) {
                throw new \RuntimeException("No FeatureType with id " . var_export($name, true));
            }
            $this->featureTypes[$name] = $this->featureTypeFactory($declarations[$name]);
        }
        return $this->featureTypes[$name];
    }

    /**
     * @param mixed[] $config
     * @return FeatureType
     * @since 0.1.15
     */
    public function featureTypeFactory(array $config)
    {
        // @todo: stop injecting full container into FeatureType
        return new FeatureType($this->container, $config);
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
            if (empty($this->featureTypes[$id])) {
                $this->featureTypes[$id] = $this->featureTypeFactory($declaration);
            }
        }
        return $this->featureTypes;
    }

    /**
     * @param FeatureType $featureType
     * @return int
     * @deprecated perform your file editing with file editing tools
     * @todo 0.2.0: remove this method
     */
    public function save(FeatureType $featureType)
    {
        $dbPath = $this->container->getParameter('kernel.root_dir') . '/config/featureTypes.yaml';

        return file_put_contents(
            $dbPath,
            Yaml::dump(array($featureType->toArray()))
        );
    }

    /**
     * @return array
     * @deprecated same as getDataStoreDeclarations
     */
    public function getFeatureTypeDeclarations()
    {
        return $this->getDataStoreDeclarations();
    }
}
