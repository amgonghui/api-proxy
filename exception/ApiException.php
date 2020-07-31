<?php

namespace Ada\HttpProxy;

class ApiException extends \RuntimeException
{
    private $_errorInfo;

    public function __construct($errorInfo, $previous = null)
    {
        $this->_errorInfo = $errorInfo;
        parent::__construct(
            isset($errorInfo['message']) ? $errorInfo['message'] : (isset($errorInfo['msg']) ? $errorInfo['msg'] : '接口错误'),
            isset($errorInfo['code']) ? $errorInfo['code'] : '999999',
            $previous
        );
    }

    /**
     * @return string
     */
    public function getErrorInfo()
    {
        return $this->_errorInfo;
    }

    /**
     * 日志中动态使用，保留
     * @return string
     */
    public function getCustomMessage()
    {
        return parent::getMessage() . ' ' . $this->getUri();
    }

    private function getUri()
    {
        return (isset($this->_errorInfo['url']) ? $this->_errorInfo['url'] : '');
    }

    public function __toString()
    {
        return sprintf('%s uri: %s in %s(%s)\nStack trace:\n%s'
            , $this->getMessage()
            , $this->getUri()
            , parent::getFile()
            , parent::getLine()
            , parent::getTraceAsString());
    }
}
