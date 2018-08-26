<?php
namespace Mapbender\DataSourceBundle\Component;

use Symfony\Component\Yaml\Yaml;

/**
 * Features service handles feature types
 *
 * @author    Andriy Oblivantsev <eslider@gmail.com>
 * @copyright 18.03.2015 by WhereGroup GmbH & Co. KG
 * @package   Mapbender\CoreBundle\Component
 */
class FeatureTypeService extends DataStoreService
{
    /**
     * Feature type s defined in mapbebder.yml > parameters.featureTypes
     *
     * @var FeatureType[] feature types
     */
    protected $storeList = array();

    protected $declarations;

    /** @var FeatureType[] */
    protected $instances;

    /**
     * Get feature type by name
     *
     * @param $id
     * @return FeatureType
     */
    public function get($id)
    {
        if (empty($this->instances[$id])) {
            $declarations = $this->getFeatureTypeDeclarations();
            if (empty($declarations[$id])) {
                throw new \RuntimeException("No FeatureType with id " . var_export($id, true));
            }
            $this->instances[$id] = new FeatureType($this->container, $declarations[$id]);
        }
        return $this->instances[$id];
    }

    /**
     * Search feature types
     *
     * @return FeatureType[]
     */
    public function search()
    {
        foreach ($this->getFeatureTypeDeclarations() as $id => $declaration) {
            if (empty($this->instances[$id])) {
                $this->instances[$id] = new FeatureType($this->container, $declaration);
            }
        }
        return $this->instances;
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
            if ($this->container->hasParameter('featureTypes')) {
                $list = array_merge($list, $this->container->getParameter('featureTypes'));
            }

            if ($this->hasDb()) {
                $list = array_merge($list, Yaml::parse(file_get_contents($this->getDbPath())));
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