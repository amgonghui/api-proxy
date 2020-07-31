<?php

namespace Amada\HttpProxy;

class RemoteApiService
{
    /**
     * @var PlainClient
     */
    private $client;
    /**
     * @var string
     */
    private $method = 'get';

    /**
     * RemoteApiService constructor.
     * @param PlainClient $client
     */
    public function __construct(PlainClient $client)
    {
        $this->client = $client;
    }

    private $path = [];

    /**
     * @param $name
     * @param $args
     * @return mixed
     * @throws ApiException
     */
    public function __call($name, $args)
    {
        $this->path[] = $name;
        $url          = implode('/', $this->path);
        $this->path   = [];

        $options = [
            'debug'   => false,
            'headers' => [
                'accept'       => 'application/json',
                'X-Request-Id' => isset($_SERVER['X-REQUEST-ID']) ? $_SERVER['X-REQUEST-ID'] : uniqid(),
                'X-User-Id'    => isset($_SERVER['X-USER-ID']) ? $_SERVER['X-USER-ID'] : 0,
            ],
        ];
        if (isset($args[1])) {
            $options = array_merge($options, $args[1]);
        }
        $post = $args[0];
        try {
            list($requestKey, $start) = $this->beforeRequestLog($args, $url, $options);
            $resp = $this->client->{$this->method}($url, $post, $options);
            $this->afterRequestLog($requestKey, $start, $resp);
        } finally {
            $this->method = 'get';
        }

        $response = json_decode($resp, true);
        //php5和php7的兼容
        if (is_null($response) && (JSON_ERROR_NONE !== json_last_error() && strlen($resp) != 0)) {
            $response['url']     = $this->client->getRequestFullUrl($url);
            $response['code']    = json_last_error();
            $response['message'] = json_last_error_msg();
            throw new ApiException($response);
        } elseif ($response && (isset($response['code']) && $response['code'] != 0)) {//兼容code为字符串的格式
            $response['url'] = $this->client->getRequestFullUrl($url);
            throw new ApiException($response);
        }

        return $response;
    }

    /**
     * @param $name
     * @return $this
     */
    public function __get($name)
    {
        if (!in_array($name, $this->path)) {
            $this->path[] = $name;
        }
        return $this;
    }

    public function method($method)
    {
        $this->method = $method;
        return $this;
    }

    /**
     * 日志
     * @param $args
     * @param $url
     * @param $options
     * @return array
     */
    private function beforeRequestLog($args, $url, $options)
    {
        if (!is_null($this->client->logger)) {
            $requestKey = uniqid();
            $start      = microtime(true);
            $this->client->logger->info(
                $requestKey . ' 调用接口' . $this->method . ' ' . $this->client->getRequestFullUrl($url),
                [
                    'args'    => $args[0],
                    'options' => $options,
                ]
            );
            return [$requestKey, $start];
        }
        return ['', 0];
    }

    /**
     * 结束日志
     * @param $requestKey
     * @param $start
     * @param $response
     */
    private function afterRequestLog($requestKey, $start, $response)
    {
        if (!is_null($this->client->logger)) {
            $this->client->logger->info($requestKey . ' 接口返回.',
                [
                    'elapsed' => microtime(true) - $start,
                    'ret'     => $response,
                ]);
        }
    }
}
