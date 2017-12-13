<?php
namespace Mapbender\DataSourceBundle\Element;

use Doctrine\DBAL\Connection;
use Mapbender\CoreBundle\Element\HTMLElement;
use Symfony\Component\HttpFoundation\Response;
use Zumba\Util\JsonSerializer;

/**
 * Class BaseElement
 */
class BaseElement extends HTMLElement
{
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
     * Handles requests (API)
     *
     * Get request "action" variable and run defined action method.
     *
     * Example: if $action="feature/get", then convert name
     *          and run $this->getFeatureAction($request);
     *
     * @inheritdoc
     */
    public function httpAction($action)
    {
        $request     = $this->getRequestData();
        $names       = array_reverse(explode('/', $action));
        $namesLength = count($names);
        for ($i = 1; $i < $namesLength; $i++) {
            $names[ $i ][0] = strtoupper($names[ $i ][0]);
        }
        $action     = implode($names);
        $methodName = preg_replace('/[^a-z]+/si', null, $action) . 'Action';
        $result     = $this->{$methodName}($request);

        if (is_array($result)) {
            $serializer = new JsonSerializer();
            $responseBody = $serializer->serialize($result);
            $result     = new Response($responseBody, 200, array('Content-Type' => 'application/json'));
        }

        return $result;
    }


    /**
     * Prepare element by type
     *
     * @param $item
     * @return mixed
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
                if (isset($item['sql'])) {
                    $connectionName = isset($item['connection']) ? $item['connection'] : 'default';
                    $sql            = $item['sql'];
                    $options        = isset($item["options"]) ? $item["options"] : array();

                    unset($item['sql']);
                    unset($item['connection']);
                    /** @var Connection $connection */
                    $connection = $this->container->get("doctrine.dbal.{$connectionName}_connection");
                    $all        = $connection->fetchAll($sql);
                    foreach ($all as $option) {
                        $options[] = array(reset($option), end($option));
                    }
                    $item["options"] = $options;
                }

                if (isset($item['service'])) {
                    $serviceInfo = $item['service'];
                    $serviceName = isset($serviceInfo['serviceName']) ? $serviceInfo['serviceName'] : 'default';
                    $method      = isset($serviceInfo['method']) ? $serviceInfo['method'] : 'get';
                    $args        = isset($serviceInfo['args']) ? $item['args'] : '';
                    $service     = $this->container->get($serviceName);
                    $options     = $service->$method($args);

                    $item['options'] = $options;
                }

                if (isset($item['dataStore'])) {
                    $dataStoreInfo = $item['dataStore'];
                    $dataStore     = $this->container->get('data.source')->get($dataStoreInfo["id"]);
                    $options       = array();
                    foreach ($dataStore->search() as $dataItem) {
                        $options[ $dataItem->getId() ] = $dataItem->getAttribute($dataStoreInfo["text"]);
                    }
                    if (isset($item['dataStore']['popupItems'])) {
                        $item['dataStore']['popupItems'] = $this->prepareItems($item['dataStore']['popupItems']);
                    }
                    $item['options'] = $options;
                }
                break;
        }
        return $item;
    }

    /**
     *
     * Parse raw HTTP request data
     *
     * Pass in $a_data as an array. This is done by reference to avoid copying
     * the data around too much.
     *
     * Any files found in the request will be added by their field name to the
     * $data['files'] array.
     *
     * @see http://www.chlab.ch/blog/archives/webdevelopment/manually-parse-raw-http-data-php
     * @param   array  Empty array to fill with data
     * @return  array  Associative array of request data
     */
    public static function parseMultiPartRequest($content)
    {
        $result = array();
        // read incoming data

        // grab multipart boundary from content type header
        preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'], $matches);

        // content type is probably regular form-encoded
        if (!count($matches)) {
            // we expect regular puts to containt a query string containing data
            parse_str(urldecode($content), $result);
            return $result;
        }

        $boundary = $matches[1];

        // split content by boundary and get rid of last -- element
        $a_blocks = preg_split("/-+$boundary/", $content);
        array_pop($a_blocks);

        // loop data blocks
        foreach ($a_blocks as $id => $block) {
            if (empty($block)) {
                continue;
            }

            // you'll have to var_dump $block to understand this and maybe replace \n or \r with a visibile char

            // parse uploaded files
            if (strpos($block, 'application/octet-stream') !== false) {
                // match "name", then everything after "stream" (optional) except for prepending newlines
                preg_match("/name=\"([^\"]*)\".*stream[\n|\r]+([^\n\r].*)?$/s", $block, $matches);
                $keyName = preg_replace('/\[\]$/', '', $matches[1]);

                if (!isset($result[ $keyName ])) {
                    $result[ $keyName ] = array();
                }
                $result[ $keyName ][] = $matches[2];

            } // parse all other fields
            else {
                // match "name" and optional value in between newline sequences
                preg_match('/name=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $block, $matches);
                $result[ $matches[1] ] = $matches[2];
            }
        }

        return $result;
    }

    /**
     * @return array|mixed
     * @throws \LogicException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     */
    protected function getRequestData()
    {
        $content    = $this->container->get('request')->getContent();
        $request    = array_merge($_POST, $_GET);
        $hasContent = !empty($content);

        if ($hasContent) {
            $isMultipart = strpos($content, '-') === 0;
            if ($isMultipart) {
                $request = array_merge($request, static::parseMultiPartRequest($content));
            } else {
                $request = array_merge($request, json_decode($content, true));
            }
        }

        return $this->decodeRequest($request);
    }

    /**
     * @return int
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     */
    protected function getUserId()
    {
        return $this->container->get('security.context')->getUser()->getId();
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
}
