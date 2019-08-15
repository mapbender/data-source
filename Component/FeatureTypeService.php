<?php
namespace Mapbender\DataSourceBundle\Component;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Features service handles feature types
 *
 * @author    Andriy Oblivantsev <eslider@gmail.com>
 * @copyright 18.03.2015 by WhereGroup GmbH & Co. KG
 * @package   Mapbender\CoreBundle\Component
 *
 * @property FeatureType[] $storeList
 */
class FeatureTypeService extends DataStoreService
{
    /** @var mixed */
    protected $declarations;

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
     * @param $id
     * @return FeatureType
     */
    public function get($id)
    {
        if (empty($this->storeList[$id])) {
            $declarations = $this->getFeatureTypeDeclarations();
            if (empty($declarations[$id])) {
                throw new \RuntimeException("No FeatureType with id " . var_export($id, true));
            }
            $this->storeList[$id] = new FeatureType($this->container, $declarations[$id]);
        }
        return $this->storeList[$id];
    }

    /**
     * Search feature types
     *
     * @return FeatureType[]
     */
    public function search()
    {
        foreach ($this->getFeatureTypeDeclarations() as $id => $declaration) {
            if (empty($this->storeList[$id])) {
                $this->storeList[$id] = new FeatureType($this->container, $declaration);
            }
        }
        return $this->storeList;
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