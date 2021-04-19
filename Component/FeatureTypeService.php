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
     * @since 0.1.15
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
     * @return array
     */
    public function getFeatureTypeDeclarations()
    {
        if ($this->declarations === null) {
            if ($this->declarationPath && $this->container->hasParameter($this->declarationPath)) {
                $this->declarations = $this->container->getParameter($this->declarationPath);
            } else {
                $this->declarations = array();
            }

            if (!$this->declarationPath || false !== strpos($this->declarationPath, '/')) {
                $filePath = $this->declarationPath ?: $this->getDbPath();
                if (\is_file($filePath)) {
                    $this->declarations = array_merge($this->declarations, Yaml::parse(file_get_contents($filePath)));
                }
            }
        }
        return $this->declarations;
    }
}
