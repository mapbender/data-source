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
     * Handles requests (API)
     *
     * Get request "action" variable and run defined action method.
     *
     * Example: if $action="feature/get", then convert name
     *          and run $this->getFeatureAction($request);
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
     * @internal param $type
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
     * @param mixed[] $item
     * @return mixed[]
     * @todo on release: update since
     * @since post-0.1.14
     */
    protected function prepareSelectItem($item)
    {
        if (isset($item['sql'])) {
            $item = $this->prepareSqlSelectItem($item);
        }

        if (isset($item['service'])) {
            $item = $this->prepareServiceSelectItem($item);
        }

        if (isset($item['dataStore'])) {
            $item = $this->prepareDataStoreSelectItem($item);
        }
        return $item;
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
        /** @todo; mix and match of static options and sql options is broken; either use a format where it works for both, or complain / throw */
        $options = isset($item["options"]) ? $item["options"] : array();

        unset($item['sql']);
        unset($item['connection']);
        /** @var Connection $connection */
        $connection = $this->container->get("doctrine.dbal.{$connectionName}_connection");
        $all = $connection->fetchAll($sql);
        foreach ($all as $option) {
            $options[] = array(reset($option), end($option));
        }
        $item['options'] = $options;
        return $item;
    }

    /**
     * @param mixed[] $item
     * @return mixed[]
     * @todo on release: update since
     * @since post-0.1.14
     */
    protected function prepareServiceSelectItem($item)
    {
        $serviceInfo = $item['service'];
        $serviceName = isset($serviceInfo['serviceName']) ? $serviceInfo['serviceName'] : 'default';
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
     */
    public function getFrontendTemplatePath($suffix = '.html.twig')
    {
        return static::autoTemplate('Element', $suffix);
    }

    /**
     * @param array $arr
     * @return bool
     */
    protected static function isAssoc(&$arr)
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    public function getAssets()
    {
        return $this->listAssets();
    }

    public static function listAssets()
    {
        return array(
            'js'  => array(
                '/bundles/mapbenderdatasource/mapbender.element.datasource.base.js',
            ),
            'css' => array(
                '/bundles/mapbendercore/sass/element/htmlelement.scss',
            ),
        );
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
