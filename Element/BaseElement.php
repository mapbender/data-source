<?php
namespace Mapbender\DataSourceBundle\Element;

use Doctrine\DBAL\Connection;
use FOM\UserBundle\Entity\User;
use Mapbender\CoreBundle\Component\Element;
use Mapbender\DataSourceBundle\Component\DataStoreService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Zumba\Util\JsonSerializer;

/**
 * Class BaseElement
 */
abstract class BaseElement extends Element
{
    /**
     * Legacy mechanism to provide Element description for backend display and filtering  via static attribute.
     * Mapbender will remove support for this mechanism.
     * Preferred method is to override getClassDescription.
     * Class descriptions are subject to translation.
     *
     * @var string
     * @deprecated
     */
    protected static $description  = '';

    /**
     * Legacy mechanism to provide Element title for backend display and filtering via static attribute.
     * Mapbender will remove support for this mechanism.
     * Preferred method is to override getClassTitle.
     * Class titles are subject to translation.
     *
     * @var string
     * @deprecated
     */
    protected static $title = '';

    /**
     * Returns the element class title for backend Element selection and filtering.
     * Subject to translation.
     *
     * @return string
     * @todo: remove method, require child implementation.
     *        This will break digitizer >=1.1.50, <=1.1.71.
     *        Safe in any version: data-manager, query-builder, search
     */
    public static function getClassTitle()
    {
        return static::$title;
    }

    /**
     * Returns the element class description for backend Element selection and filtering.
     * Subject to translation.
     *
     * @return string
     * @todo: remove method, require child implementation.
     *        This will break digitizer >=1.1.50, <=1.1.71.
     *        Safe in any version: data-manager, query-builder, search
     */
    public static function getClassDescription()
    {
        return static::$description;
    }

    /**
     * Returns the JavaScript widget constructor name, magically auto-calculated from the component
     * class name.
     *
     * @return string
     * @deprecated every Element component should return its widget constructor name explicitly
     *  unless it wants to inherit a parent value.
     * @todo: remove method, require child implementation.
     *        This will break digitizer >=1.1.50, <=1.1.71.
     *        This will break search >=bfab127, <=96d917a.
     *        Safe in any version: data-manager, query-builder
     */
    public function getWidgetName()
    {
        @trigger_error("Deprecated: " . get_called_class() . " relies on automatically calculated widget constructor name. Please implement getWidgetName in your Element class", E_USER_DEPRECATED);
        $classNameParts = explode('\\', get_called_class());
        return 'mapbender.mb' . end($classNameParts);
    }

    /**
     * Prepare elements recursive.
     *
     * @param $items
     * @return array
     */
    public function prepareItems($items)
    {
        if (!is_array($items)) {
            return $items;
        } elseif (self::isAssoc($items)) {
            $items = $this->prepareItem($items);
        } else {
            foreach ($items as $key => $item) {
                $items[ $key ] = $this->prepareItem($item);
            }
        }
        return $items;
    }

    /**
     * @param Request $request
     * @return Response
     * @deprecated do not rely on method name inflection magic; use your own implementation supporting valid actions explicitly
     */
    public function handleHttpRequest(Request $request)
    {
        $r = new \ReflectionMethod($this, 'httpAction');
        if ($r->getDeclaringClass()->name !== __CLASS__) {
            return $this->httpAction($request->attributes->get('action'));
        }
        return $this->handleHttpRequestMagically($request);
    }

    /**
     * Magically invoke action method with implicit data merging and some Zumba json.
     * Action method names are reversed words from action, split at dashes, uc'ed, and
     * appended with "Action".
     *
     * Example: if action == "feature/get", invokes method getFeatureAction.
     *   If getFeatureAction returns a Response, return it as is
     *   If getFeatureAction returns an array, run it through Zumba JsonSerializer and wrap it in a Response
     *   If getFeatureAction returns anything else, we cross fingers and hope for controller
     *   handling.
     * @param Request $request
     * @return Response
     * @deprecated you really don't want any of this to happen; write a handleHttpRequest
     *         method with a big switch / case handling each supported Ajax action explicitly
     * @internal
     */
    private function handleHttpRequestMagically(Request $request)
    {
        @trigger_error('DEPRECATED: ' . get_class($this) . ' should not rely on BaseElement to handle Ajax requests, write your own implementation', E_USER_DEPRECATED);
        $requestData = $this->getRequestData();
        $action = $request->attributes->get('action');
        $names = array_reverse(explode('/', $action));
        $names = array_merge(array($names[0]), array_map('ucfirst', array_slice($names, 1)));

        $action     = implode($names);
        $methodName = preg_replace('/[^a-z]+/si', null, $action) . 'Action';
        $result     = $this->{$methodName}($requestData);

        if (is_array($result)) {
            /** @todo: remove Zumba serializer; use JsonResponse, like the rest of the world */
            $serializer = new JsonSerializer();
            $responseBody = $serializer->serialize($result);
            $result     = new Response($responseBody, 200, array('Content-Type' => 'application/json'));
        }

        return $result;
    }

    /**
     *
     * @inheritdoc
     * @deprecated
     */
    public function httpAction($action)
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        return $this->handleHttpRequest($request);
    }

    /**
     * Prepare element by type
     *
     * @param mixed[] $item
     * @return mixed[]
     */
    protected function prepareItem($item)
    {
        if (!isset($item["type"])) {
            return $item;
        }

        if (isset($item["children"])) {
            $item["children"] = $this->prepareItems($item["children"]);
        }

        switch ($item['type']) {
            case 'select':
                return $this->prepareSelectItem($item);
            default:
                return $item;

        }
    }

    /**
     * Reformat statically defined select item options for vis-ui
     * consumption.
     *
     * @param mixed[] $item
     * @return array
     * @todo on release: update since
     * @since post-0.1.14
     */
    protected function formatStaticSelectItemOptions($item)
    {
        if (empty($item['options'])) {
            return array();
        } elseif (!is_array($item['options'])) {
            throw new \RuntimeException("Invalid type " . gettype($item['options']) . " in select item options. Expected array. Item: " . print_r($item, true));
        } else {
            // bring options into same format as generated by
            // SQL path, so mix and match works.
            $options = array();
            foreach ($item['options'] as $value => $label) {
                $options[] = array(
                    // Force object emission to bypass vis-ui's "isValuePack" path
                    // use fancy underscores to reinforce key
                    // order, in case json encoding / json parsing
                    // performs any sorting
                    '___value' => $value,
                    '__label' => $label,
                );
            }
            return $options;
        }
    }

    /**
     * @param mixed[] $item
     * @return mixed[]
     * @todo on release: update since
     * @since post-0.1.14
     */
    protected function prepareSelectItem($item)
    {
        $paths = array(
            'dataStore' => null,
            'service' => null,
            'sql' => null,
        );
        $configuredPaths = array_keys(array_intersect_key($paths, array_filter($item)));
        if (count($configuredPaths) > 1) {
            $message
                = 'Select item has option configurations for ' . implode(', ', $configuredPaths) . '.'
                . ' Executing only ' . $configuredPaths[0] . ' path.'
            ;
            // NOTE: E_USER_DEPRECATED is the only error level currently guaranteed to end up in logs
            @trigger_error("WARNING: {$message}", E_USER_DEPRECATED);
        }

        if (!empty($item['dataStore'])) {
            return $this->prepareDataStoreSelectItem($item);
        } elseif (!empty($item['service'])) {
            return $this->prepareServiceSelectItem($item);
        } elseif (!empty($item['sql'])) {
            return $this->prepareSqlSelectItem($item);
        } else {
            $item['options'] = $this->formatStaticSelectItemOptions($item);
            return $item;
        }
    }

    /**
     * Reformat single select option loaded from 'sql' path for
     * vis-ui consumption.
     *
     * @param array $row
     * @param mixed[] $selectItem
     * @return mixed[]
     * @todo on release: update since
     * @since post-0.1.14
     */
    protected function formatSqlSelectItemOption($row, $selectItem)
    {
        // Legacy quirk: reset / end allows using a single-column
        // select where each option's submit value is the same as its
        // label.
        // When processing a multi-column row, the submit value
        // is taken from the first column, and the displayed label
        // from the _last_ column.
        return array(
            // Force object emission to bypass vis-ui's "isValuePack" path
            // use fancy underscores to reinforce key
            // order, in case json encoding / json parsing
            // performs any sorting
            '___value' => reset($row),
            '__label' => end($row),
            // emit entire associative row array as well, minus nonserializable
            // resources (e.g. certain Oracle types)
            'properties' => array_filter($row, function($column) {
                return !is_resource($column);
            }),
        );
    }

    /**
     * @param mixed[] $item
     * @return mixed[]
     * @todo on release: update since
     * @since post-0.1.14
     */
    protected function prepareSqlSelectItem($item)
    {
        $connectionName = isset($item['connection']) ? $item['connection'] : 'default';
        $sql = $item['sql'];
        $item['options'] = $this->formatStaticSelectItemOptions($item);

        unset($item['sql']);
        unset($item['connection']);
        $connection = $this->getDbalConnectionByName($connectionName);
        foreach ($connection->fetchAll($sql) as $row) {
            $item['options'][] = $this->formatSqlSelectItemOption($row, $item);
        }
        return $item;
    }

    /**
     * @param mixed[] $item
     * @return mixed[]
     * @todo on release: update since
     * @since post-0.1.14
     * @deprecated for being untestable and unmaintainable; override prepareSelectItem for
     *    project-specific customization
     */
    protected function prepareServiceSelectItem($item)
    {
        @trigger_error("Taking deprecated 'service' path to generate select item options. Extend prepareSelectItem to customize your project instead. Item: " . print_r($item, true), E_USER_DEPRECATED);
        $serviceInfo = $item['service'];
        if (empty($serviceInfo['serviceName'])) {
            throw new \RuntimeException("Invalid 'service' select item configuration, missing required serviceName. Item: " . print_r($item, true));
        }

        $serviceName = $serviceInfo['serviceName'];
        $method = isset($serviceInfo['method']) ? $serviceInfo['method'] : 'get';
        $args = isset($serviceInfo['args']) ? $item['args'] : '';
        $service = $this->container->get($serviceName);
        $options = $service->$method($args);

        $item['options'] = $options;
        return $item;
    }

    /**
     * @param mixed[] $item
     * @return mixed[]
     * @todo on release: update since
     * @since post-0.1.14
     */
    protected function prepareDataStoreSelectItem($item)
    {
        $dataStoreInfo = $item['dataStore'];
        $dataStore = $this->getDataStoreService()->get($dataStoreInfo['id']);
        $options = array();
        foreach ($dataStore->search() as $dataItem) {
            $options[$dataItem->getId()] = $dataItem->getAttribute($dataStoreInfo["text"]);
        }
        if (isset($item['dataStore']['popupItems'])) {
            $item['dataStore']['popupItems'] = $this->prepareItems($item['dataStore']['popupItems']);
        }
        $item['options'] = $options;
        return $item;
    }

    /**
     * Override point for child classes
     *
     * @return DataStoreService
     * @todo on release: update since
     * @since post-0.1.14
     */
    protected function getDataStoreService()
    {
        /** @var DataStoreService $service */
        $service = $this->container->get('data.source');
        return $service;
    }

    /**
     * @param string $name
     * @return Connection
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @todo on release: update since
     * @since post-0.1.14
     */
    protected function getDbalConnectionByName($name)
    {
        /** @var Connection $connection */
        $connection = $this->container->get("doctrine.dbal.{$name}_connection");
        return $connection;
    }

    /**
     * @param Request|null $request
     * @return array|mixed
     * @throws \LogicException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @deprecated implement your own data extraction in action methods
     */
    protected function getRequestData(Request $request = null)
    {
        @trigger_error('DEPRECATED: ' . get_class($this) . '::getRequestData will be removed in a future release (version TBD).', E_USER_DEPRECATED);
        if (!$request) {
            $request = $this->container->get('request_stack')->getCurrentRequest();
        }
        $content = $request->getContent();
        $requestData = array_merge($_POST, $_GET);

        if (!empty($content)) {
            $requestData = array_merge($requestData, json_decode($content, true));
        }

        return $this->decodeRequest($requestData);
    }

    /**
     * @return int|string
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     */
    protected function getUserId()
    {
        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = $this->container->get('security.token_storage');
        $token = $tokenStorage->getToken();
        if ($token instanceof AnonymousToken) {
            return 0;
        }
        $user = $token->getUser();
        if (is_object($user) && $user instanceof User) {
            return $user->getId();
        } else {
            return $token->getUsername();
        }
    }

    /**
     * Decode request array variables
     *
     * @param array $request
     * @return mixed
     */
    public function decodeRequest(array $request)
    {
        foreach ($request as $key => $value) {
            if (is_array($value)) {
                $request[ $key ] = $this->decodeRequest($value);
            } elseif (strpos($key, '[')) {
                preg_match('/(.+?)\[(.+?)\]/', $key, $matches);
                list($match, $name, $subKey) = $matches;

                if (!isset($request[ $name ])) {
                    $request[ $name ] = array();
                }

                $request[ $name ][ $subKey ] = $value;
                unset($request[ $key ]);
            }
        }
        return $request;
    }

    /**
     * Auto-calculation of AdminType class from Element class name.
     * Bare-bones reimplementation of deprecated upstream method.
     *
     * @return string fully qualified class name
     * @todo: remove method, require child implementation. This will break
     *      digitizer >= 1.1.50, <= 1.1.71
     *      search < d61945be4183b6ed208f322c2e3e775f2b45fd9b
     *      safe in all versions: data-manager, query-builder
     */
    public static function getType()
    {
        $clsInfo = explode('\\', get_called_class());
        $namespaceParts = array_slice($clsInfo, 0, -1);
        // convention: AdminType classes are placed into the "<bundle>\Element\Type" namespace
        $namespaceParts[] = "Type";
        $bareClassName = implode('', array_slice($clsInfo, -1));
        // convention: AdminType class name is the same as the element class name suffixed with AdminType
        return implode('\\', $namespaceParts) . '\\' . $bareClassName . 'AdminType';
    }

    /**
     * Auto-calculation of template reference from class name.
     * Bare-bones reimplementation of deprecated upstream method.
     *
     * @param string $section 'Element' or 'ElementAdmin'
     * @param string $suffix '.html.twig' (default) or '.json.twig'
     * @return string twig-style template resource reference
     */
    private static function autoTemplate($section, $suffix = '.html.twig')
    {
        $cls = get_called_class();
        $bundleName = str_replace('\\', '', preg_replace('/^([\w]+\\\\)*?(\w+\\\\\w+Bundle).*$/', '\2', $cls));
        $elementName = implode('', array_slice(explode('\\', $cls), -1));
        $elementSnakeCase = strtolower(preg_replace('/([^A-Z])([A-Z])/', '\\1_\\2', $elementName));
        return "{$bundleName}:{$section}:{$elementSnakeCase}{$suffix}";
    }

    /**
     * Auto-calculation of admin template reference from class name.
     * Bare-bones reimplementation of deprecated upstream method.
     *
     * @return string twig-style template resource reference
     * @todo: remove method, require child implementation. This will break
     *      query-builder < 1.0.3
     *      digitizer >= 1.1.50, <= 1.1.71
     *      search < d61945be4183b6ed208f322c2e3e775f2b45fd9b
     *      safe in all versions: data-manager
     */
    public static function getFormTemplate()
    {
        return static::autoTemplate('ElementAdmin');
    }

    /**
     * Auto-calculation of frontend template reference from class name.
     * Bare-bones reimplementation of deprecated upstream method.
     *
     * @param string $suffix '.html.twig' (default) or '.json.twig'
     * @return string twig-style template resource reference
     * @todo: remove method, require child implementation. This will break
     *      query-builder < 1.0.2
     *      digitizer >= 1.1.50, <= 1.1.71
     *      data-manager < 1.0.6.2
     *      search <= 86e8fd24b910bcbd5093e207d2afb12cedff8bd4
     */
    public function getFrontendTemplatePath($suffix = '.html.twig')
    {
        return static::autoTemplate('Element', $suffix);
    }

    /**
     * @param array $arr
     * @return bool
     */
    protected static function isAssoc($arr)
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    public function getFrontendTemplateVars()
    {
        // The default fallback getConfiguration call (see below) can be outrageously expensive.
        // This can make a default inherited render() call very slow. BaseElement child classes
        // generally have pretty trivial templates, accessing only id and title of the Element
        // entity, so this is completely appropriate here.
        return $this->entity->getConfiguration();
    }

    public function getConfiguration()
    {
        $configuration = $this->entity->getConfiguration();
        if (isset($configuration['children'])) {
            $configuration['children'] = $this->prepareItems($configuration['children']);
        }
        return $configuration;
    }
}
