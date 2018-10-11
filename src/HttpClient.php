<?php

namespace Http;

class HttpClient
{
    const HTTP_METHOD = [
        'GET','POST','PUT','DELETE','PATCH','OPTIONS'
    ];

    /** @var resource $client */
    protected static $client;

    /** @var  self $curl */
    protected static $instance;

    /** @var string $url */
    protected $url;

    /** @var bool $returntransfer */
    protected $returntransfer = true;

    /** @var string $encoding */
    protected $encoding = "";

    /** @var int $maxredirs */
    protected $maxredirs = 10;

    /** @var int $timeout */
    protected $timeout = 30;

    /** @var int $httpVersion */
    protected $httpVersion = CURL_HTTP_VERSION_1_1;

    /** @var array|false $headers */
    protected $headers = [

    ];

    /** @var 'GET'|'POST'|'PUT'|'DELETE'|'PATCH'|'OPTIONS' $customrequest */
    protected $customrequest = 'GET';

    /** @var array $extraCurlOpt */
    protected $extraCurlOpt = [];


    protected function __construct()
    {
    }

    /** @return self */
    public static function getInstance()
    {
        if (empty(self::$client))
            self::$client = curl_init();
        if (empty(self::$instance))
            self::$instance = new self();
        return self::$instance;
    }

    /**
     * @param string $url 请求地址 <required>
     * @param array $request 请求
     * @param string $request['method'] 请求方法 <not required> 'GET'|'POST'|'PUT'|'DELETE'|'PATCH'|'OPTIONS'
     * @param array $request['headers'] 请求头 <not required>
     * @param array $request['data'] 请求body <not required>
     */
    public function request(string $url, array $request = [])
    {
        $this->url = $url;
        if (!empty($request['method']) && ($method = strtoupper($request['method'])) && in_array($method, self::HTTP_METHOD)) {
            $this->customrequest = $method;
        }
        $data = null;
        if (!empty($request['data']) && is_array($request['data'])) {
            $data = $request['data'];
        }
        if (!empty($request['headers'])) {
            $this->headers = array_merge($this->headers, $request['headers']);
        }
        if ($this->customrequest !== 'GET') {
            $this->extraCurlOpt = $this->extraCurlOpt + [
                    CURLOPT_POST => 1,
                    CURLOPT_POSTFIELDS => $data
                ];
        }
        return $this->_getResult();
    }

    public function post($url, array $body = [], array $headers = [])
    {
        $this->url = $url;
        $this->customrequest = 'POST';
        $this->extraCurlOpt = $this->extraCurlOpt + [
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => $body
            ];
        if (!empty($headers)) {
            $this->headers = array_merge($this->headers, $headers);
        }

        return $this->_getResult();
    }

    public function get($url, array $headers = [])
    {
        $this->url = $url;
        if (!empty($headers)) {
            $this->headers = array_merge($this->headers, $headers);
        }
        return $this->_getResult();
    }

    protected function _getResult()
    {
        try {
            $this->_buildContent();
            if (($result = curl_exec(self::$client)) === false) {
                throw  new \Exception(curl_error(self::$client));
            }
            curl_close(self::$client);
            $this->extraCurlOpt = []; //每次执行完清空额外参数
            return json_decode($result,true);
        } catch (\Exception $e) {
            curl_close(self::$client);
            return [
                'err_code' => 400,
                'msg' => $e->getMessage(),
                'trace' => $e->getTrace()
            ];
        }
    }


    /**
     * @throws \Exception
     */
    private function _buildContent()
    {
        $opts = [
                CURLOPT_URL => $this->url,
                CURLOPT_RETURNTRANSFER => $this->returntransfer,
                CURLOPT_ENCODING => $this->encoding,
                CURLOPT_MAXREDIRS => $this->maxredirs,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_HTTP_VERSION => $this->httpVersion,
                CURLOPT_CUSTOMREQUEST => $this->customrequest,
                CURLOPT_HEADER => false,
                CURLOPT_SSL_VERIFYPEER => false
            ] + $this->extraCurlOpt;
        $headers = array_unique($this->headers);
        $this->headers = [];
        foreach ($headers as $k => $header) {
            if (is_numeric($k) && ($index = strpos(trim($header), ':')) !== false) {
                $k = trim(substr($header, 0, $index));
                $header = trim(substr($header, $index + 1));
            }
            if (!is_numeric($k)) {
                $this->headers[] = $k . ': ' . trim($header);
            }
            if (strtolower($k) == 'content-type' && strpos($header, 'application/json') !== false) {
                if(is_array($opts[CURLOPT_POSTFIELDS] ?? '')){
                    $opts[CURLOPT_POSTFIELDS] = json_encode($opts[CURLOPT_POSTFIELDS]);
                }
                $this->headers[] = "Content-Length: " . strlen($opts[CURLOPT_POSTFIELDS] ?? '');
            }
        }
        if(is_array($opts[CURLOPT_POSTFIELDS] ?? '')){
            $opts[CURLOPT_POSTFIELDS] = http_build_query($opts[CURLOPT_POSTFIELDS]);
        }

        if(!empty($this->headers)){
            $opts = $opts + [
                    CURLOPT_HTTPHEADER => $this->headers
                ];
        }
        if (!curl_setopt_array(self::$client, $opts)) {
            throw new \Exception('curl opt params error');
        }
    }

    /**
     * @param array $extraCurlOpt
     * @return HttpClient
     */
    public function setExtraCurlOpt(array $extraCurlOpt): HttpClient
    {
        $this->extraCurlOpt = $extraCurlOpt;
        return $this;
    }

    /**
     * @param int $timeout
     * @return HttpClient
     */
    public function setTimeout(int $timeout): HttpClient
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * @param array|false $headers
     * @return HttpClient
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;
        return $this;
    }

}
