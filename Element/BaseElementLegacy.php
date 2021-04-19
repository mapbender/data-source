<?php


namespace Mapbender\DataSourceBundle\Element;

use Mapbender\CoreBundle\Component\Element;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Zumba\Util\JsonSerializer;

/**
 * Everything about BaseElement that is deprecated and scheduled for removal.
 * @internal
 */
abstract class BaseElementLegacy extends Element
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
     * @todo 0.2.0: remove this method
     */
    protected function handleHttpRequestMagically(Request $request)
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
     * Throws together _GET and _POST and json content and implicitly reorganizes form
     * field keys into nested arrays.
     *
     * @param Request|null $request
     * @return array|mixed
     * @throws \LogicException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @deprecated implement your own data extraction in action methods, use query / post as appropriate
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
     * Unfolds form data keys into nested arrays. May only support two levels of nesting.
     *
     * @param array $data
     * @return mixed
     * @deprecated if you want forms, use forms
     */
    public function decodeRequest(array $data)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->decodeRequest($value);
            } elseif (strpos($key, '[')) {
                preg_match('/(.+?)\[(.+?)\]/', $key, $matches);
                list($match, $name, $subKey) = $matches;

                if (!isset($data[$name])) {
                    $data[$name] = array();
                }

                $data[$name][$subKey] = $value;
                unset($data[$key]);
            }
        }
        return $data;
    }
}
