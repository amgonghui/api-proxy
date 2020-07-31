<?php

namespace Ada\HttpProxy;

use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\MultipartStream;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

class AuthorizedClient extends PlainClient
{
    private $key;
    private $secret;
    private $ttl;

    public function __construct(array $config, LoggerInterface $logger = null)
    {
        if (empty($config['app_key']) || empty($config['app_secret'])) {
            throw  new \RuntimeException('need app_key and app_secret');
        }
        $this->key    = $config['app_key'];
        $this->secret = $config['app_secret'];
        $this->ttl    = isset($config['ttl']) ? $config['ttl'] : 60;
        $stack        = new HandlerStack();

        $stack->setHandler(new CurlHandler());
        $stack->push(Middleware::mapRequest(function (RequestInterface $request) {
            //对于multipart的表单提交的签名进行特殊处理
            $body = $request->getBody();
            if ($body instanceof MultipartStream) {
                return $request;
            }
            return $this->signRequest($request);
        }));

        $config['handler'] = $stack;
        parent::__construct($config, $logger);
    }

    public function multiPost($uri, $params = [], $options = [])
    {
        $toSign = [];
        foreach ($params as $key => $param) {
            if (!is_resource($param)) {
                $toSign[$key] = $param;
            }
        }
        $signs            = $this->buildSign($toSign);
        $query            = isset($options['query']) ? $options['query'] : [];
        $options['query'] = array_merge($query, $signs);
        return parent::multiPost($uri, $params, $options);
    }

    /**
     * @param RequestInterface $request
     * @return RequestInterface
     */
    private function signRequest(RequestInterface $request)
    {
//        $uri     = $request->getUri()->getPath();
        $query   = $request->getUri()->getQuery();
        $body    = $request->getBody();
        $params  = [];
        $queries = [];

        if (!empty($query)) {
            parse_str($query, $queries);
            $params = array_merge($params, $queries);
        }
        if ($body->getSize()) {
            if (strpos(implode(' ', $request->getHeader('Content-Type')), '/json') !== false) {
                $params = json_decode($body, true);
            } else {
                parse_str($body, $params);
            }
        }
//        $params['request_uri'] = $uri;

        $params = empty($params) ? [] : $params;

        $signParams = $this->buildSign($params);

        $queries = array_merge($queries, $signParams);
        $query   = http_build_query($queries);
        $oUri    = $request->getUri()->withQuery($query);
        $request = $request->withUri($oUri);
        return $request;
    }

    private function md_hmac($secret, $params)
    {
        return substr(md5(base64_encode(hash_hmac('sha256', $params, $secret, true))), 5, 10);
    }

    /**
     * @param $params
     * @return array
     */
    private function buildSign($params)
    {
        $signParams            = [];
        $signParams['appkey']  = $this->key;
        $signParams['nonce']   = uniqid();
        $signParams['expires'] = $this->ttl + time();

        $params = array_merge($params, $signParams);
        //按照key进行排序
        ksort($params);
        $paramsToSign            = http_build_query($params);
        $signature               = $this->md_hmac($this->secret, $paramsToSign);
        $signParams['signature'] = $signature;
        return $signParams;
    }
}
