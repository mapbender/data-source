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
    /** @var mixed */
    protected $declarations;
    /** @var FeatureType[] */
    protected $featureTypes;

    /**
     * @param ContainerInterface $container
     * @param string $declarationPath container param key or file name; treated as file name if it contains slash(es)
     */
    public function __construct(ContainerInterface $container, $declarationPath = 'featureTypes')
    {
        parent::__construct($container, $declarationPath);
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
     */
    public function getFeatureTypeByName($name)
    {
        if (empty($this->featureTypes[$name])) {
            $declarations = $this->getFeatureTypeDeclarations();
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
     */
    public function search()
    {
        foreach ($this->getFeatureTypeDeclarations() as $id => $declaration) {
            if (empty($this->featureTypes[$id])) {
                $this->featureTypes[$id] = $this->featureTypeFactory($declaration);
            }
        }
        return $this->featureTypes;
    }

    /**
     * @param FeatureType $featureType
     * @return int
     */
    public function save(FeatureType $featureType)
    {
        $dbPath      = $this->getDbPath();
        $definitions = array();

        $this->getFeatureTypeDeclarations();
        $declaration   = $featureType->toArray();
        $definitions[] = $declaration;

        return file_put_contents(
            $dbPath,
            Yaml::dump($definitions)
        );
    }

    /**
     * @return string
     */
    protected function getConfigurationPath()
    {
        $kernel     = $this->container->get("kernel");
        $configPath = $kernel->getRootDir() . "/config";
        return $configPath;
    }

    /**
     * @return string
     */
    protected function getDbPath()
    {
        return $this->getConfigurationPath() . "/featureTypes.yaml";
    }

    /**
     * Has db file?
     *
     * @return bool
     */
    protected function hasDb()
    {
        return is_file($this->getDbPath());
    }

    /**
     * @return array
     */
    public function getFeatureTypeDeclarations()
    {
        if ($this->declarations === null) {
            $list = array();
            $paramKey = $this->declarationPath;
            if ($this->declarationPath) {
                if (false !== strpos($paramKey, '/')) {
                    $filePath = $paramKey;
                    $paramKey = null;
                } else {
                    $filePath = false;
                }
            } else {
                if ($this->hasDb()) {
                    $filePath = $this->getDbPath();
                } else {
                    $filePath = false;
                }
            }

            if ($this->container->hasParameter($paramKey)) {
                $list = array_merge($list, $this->container->getParameter($paramKey));
            }

            if ($filePath !== false) {
                $list = array_merge($list, Yaml::parse(file_get_contents($filePath)));
            } else {
                foreach ($list as $id => &$item) {
                    $item['id'] = $id;
                }
            }
            $this->declarations = $list;
        }
        return $this->declarations;
    }
}
