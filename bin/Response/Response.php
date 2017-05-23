<?php

namespace Bin\Response;

class Response
{
    //设置数据
    private $originContent = '';

    /**
     * @todo 之后要把该函数的实现改成工厂模式
     * @param $response
     * @throws \Exception
     */
    public function __construct($response = NULL)
    {
        if (NULL == $response) {
            return;
        }
        //根据response类型来获取数据
        if (is_string($response)) {
            return $this->originContent = $response;
        }

        if (is_array($response)) {
            return $this->originContent = json_encode($response, 1);
        }

        //todo 处理编译view的问题
        if ($response instanceof \Bin\View\View) {
            return $this->originContent = (new \Bin\View\Compiler($response))->getPHP();
        }

        //todo 处理active record
    }

    /**
     * @power 获取content
     * @return string
     */
    public function getContent()
    {
        return $this->originContent;
    }

    /**
     * @power 设置content
     * @param $string
     * @return mixed
     */
    public function setContent($string)
    {
        return $this->originContent = $string;
    }

    /**
     * @power 获取数据
     * @return string
     */
    public function __tostring()
    {
        return $this->getContent();
    }

    /**
     * @power 设置状态
     * @param $httpStatus
     * @param string $content
     * @return string
     * @throws \Exception
     */
    public function setStatus($httpStatus, $content = NULL)
    {
        $status = array(
            // Informational 1xx
            100 => 'Continue',
            101 => 'Switching Protocols',
            // Success 2xx
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            // Redirection 3xx
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Moved Temporarily ',  // 1.1
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            // 306 is deprecated but reserved
            307 => 'Temporary Redirect',
            // Client Error 4xx
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            // Server Error 5xx
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            509 => 'Bandwidth Limit Exceeded'
        );
        if (array_key_exists($httpStatus, $status)) {
            header('HTTP/1.1 ' . $httpStatus . ' ' . $status[$httpStatus]);
        }

        if (NULL == $content) {
            return $this->originContent = (string) \Bin\View\View::make($httpStatus);
        }

        return $this->setContent($content);
    }
}