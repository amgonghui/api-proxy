<?php

namespace Amada\HttpProxy;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use Psr\Log\LoggerInterface;

class PlainClient extends Client
{
    protected $baseConf;
    /**
     * @var LoggerInterface|null
     */
    public $logger;

    /**
     * PlainClient constructor.
     * @param array                $config
     * @param LoggerInterface|null $logger
     */
    public function __construct(array $config, LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        if (!isset($config['handler']) || !$config['handler'] instanceof HandlerStack) {
            $stack = new HandlerStack();
            $stack->setHandler(new CurlHandler());
            $stack->push(Middleware::httpErrors(), 'http_errors');
            $stack->push(Middleware::prepareBody(), 'prepare_body');
        } else {
            /** @var HandlerStack $stack */
            $stack = $config['handler'];
        }
        $stack->push(Middleware::httpErrors(), 'http_errors');

        if (!is_null($logger)) {
            $stack->push(Middleware::log(
                $logger,
                new MessageFormatter("\">>>{method} {uri} HTTP/{version}\" {code} {req_header_X-Request-Id} <<<{res_body}")
            ));
        }
        $this->baseConf    = $config;
        $config['handler'] = $stack;
        parent::__construct($config);
    }

    public function get($uri, $data = [], $options = [])
    {
        $options['query'] = $data;
        return $this->_request('GET', $uri, $options);
    }

    public function post($uri, $data = [], $options = [])
    {
        $options['form_params'] = $data;
        return $this->_request('POST', $uri, $options);
    }

    public function multiPost($uri, $params = [], $options = [])
    {
        $options['multipart'] = [];

        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $options['multipart'][] = [
                    'name'     => $key,
                    'contents' => $value,
                ];
            }
        }
        return $this->_request('POST', $uri, $options);
    }

    /**
     * JSON request.
     *
     * @param string       $url
     * @param array        $params
     * @param string|array $options
     * @param int          $encodeOption
     * @return string
     */
    public function json($url, $params = [], $options = [], $encodeOption = JSON_UNESCAPED_UNICODE)
    {
        is_array($params) && $params = json_encode($params, $encodeOption);

        $options['body']                    = $params;
        $options['headers']['content-type'] = 'application/json';

        return $this->_request('POST', $url, $options);
    }

    /**
     * 返回全路径url
     * @param $uri
     * @return string
     */
    public function getRequestFullUrl($uri)
    {
        return  !empty($this->baseConf['base_uri'] ) ? $this->baseConf['base_uri'] . $uri : $uri;
    }

    /**
     * @param $method
     * @param $uri
     * @param $options
     * @return string
     * @throws ApiException|ConnectionException
     */
    private function _request($method, $uri, $options)
    {
        $requestUri = $this->getRequestFullUrl($uri);
        try {
            $response = parent::request($method, $uri, $options);
            $body     = $response->getBody();
            $body->rewind();
            return $body->getContents();
        } catch (BadResponseException $e) {
            throw new ApiException([
                'code'    => $e->getCode(),
                'url'     => $requestUri,
                'message' => $e->getMessage(),
            ], $e);
        } catch (ConnectException $connectException) {
            $context        = $connectException->getHandlerContext();
            $timeoutMessage = 'request timeout ' . $requestUri . ' after ' . $context['total_time'] . 's';

            if (isset($context['errno']) &&
                (
                    $context['errno'] == CURLE_OPERATION_TIMEOUTED ||
                    $context['errno'] == CURLE_GOT_NOTHING
                )
            ) {
                throw new TimeoutException($timeoutMessage, 20001);
            } else {
                throw new ConnectionException($timeoutMessage, 20002);
            }
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $errorResponse = $e->getResponse();
                $error         = json_decode($errorResponse->getBody());
                if (JSON_ERROR_NONE !== json_last_error()) {
                    $response['url']     = $requestUri;
                    $response['code']    = json_last_error();
                    $response['message'] = json_last_error_msg();
                    throw new ApiException($response, $e);
                } else {
                    throw new ApiException($error, $e);
                }
            } else {
                throw new ApiException([
                    'url'     => $requestUri,
                    'code'    => 100,
                    'message' => $e->getMessage(),
                ], $e);
            }
        }
    }
}
