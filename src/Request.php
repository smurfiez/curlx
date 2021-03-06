<?php

namespace CurlX;

/**
 * Class Request
 * @package CurlX
 *
 * @property string $url url of the Request
 * @property array $post_data array of post data
 * @property float $total_time running time of the request
 * @property int $timeout time (in msec) after which the request will be aborted
 * @property array $options cUrl options of the request
 * @property array $headers headers of the request
 * @property resource $handle cUrl handle of the request
 * @property callable[] $listeners array of registered listeners which will be called upon when request finishes
 * @property mixed $response curl's response
 * @property int $http_code the http code of the response
 */
class Request implements RequestInterface
{
    protected $url;
    protected $post = [];
    protected $result;
    protected $listeners = [];
    protected $timeout;
    protected $curlHandle;
    protected $headers = [];
    protected $options = [];
    protected $response;
    protected $httpCode;

    /**
     * Camelizes a string
     * @param string $str string to camelize
     * @return string camelized string
     */
    public static function camelize($str)
    {
        return str_replace('_', '', ucwords($str, '_'));
    }

    /**
     * Magic setter function
     * @param string $name attribute to set
     * @param mixed $value the new value
     * @return void
     */
    public function __set($name, $value)
    {
        $c = static::camelize($name);
        $m = "set$c";
        if (method_exists($this, $m)) {
            $this->$m($value);
        } else {
            user_error("undefined property $name");
        }
    }

    /**
     * Magic getter function
     * @param string $name of the attribute to get
     * @return mixed the attribute's value
     */
    public function __get($name)
    {
        $c = static::camelize($name);
        $m = "get$c";
        if (method_exists($this, $m)) {
            return $this->$m();
        } else {
            user_error("undefined property $name");
        }
    }

    /**
     * Request constructor.
     * @param string $url optional url
     */
    public function __construct($url = null)
    {
        $this->setUrl($url);

        // Defaults
        $this->options[CURLOPT_RETURNTRANSFER] = true;
        $this->options[CURLOPT_NOSIGNAL] = 1;
        $this->options[CURLOPT_FOLLOWLOCATION] = true;
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        if (isset($this->curlHandle)) {
            curl_close($this->curlHandle);
        }
    }

    /**
     * Clone the object
     * @return void
     */
    public function __clone()
    {
        if (isset($this->curlHandle)) {
            $this->curlHandle = curl_copy_handle($this->curlHandle);
        }
    }

    /**
     * Normalize an array
     * change from ['key' => 'value'] format to ['key: value']
     * @param array $array array to normalize
     * @return array normalized array
     */
    protected function normalize(array $array)
    {
        $normalized = [];
        foreach ($array as $key => $value) {
            if (is_string($key)) {
                $normalized[] = $key . ': ' . $value;
            } else {
                $normalized[] = $value;
            }
        }
        return $normalized;
    }

    /**
     * Setter for the url field
     * @param string $url url
     * @return void
     */
    public function setUrl($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL) === false) {
            $this->url = $url;
        }
    }

    /**
     * Getter for url field
     * @return string url
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Setter for the post data array
     * @param array $postData post data
     * @return void
     */
    public function setPostData(array $postData)
    {
        $this->post = $postData + $this->post;
        $this->options[CURLOPT_POST] = 1;
        if (!empty($this->post)) {
            $this->options[CURLOPT_POSTFIELDS] = http_build_query($this->post);
        }
    }

    /**
     * Getter for the post data array
     * @return array post data
     */
    public function getPostData()
    {
        return $this->post;
    }

    /**
     * Returns the time (msec) it took to make the request
     * @return float time
     */
    public function getTime()
    {
        if (isset($this->curlHandle)) {
            return curl_getinfo($this->curlHandle)['total_time'];
        }
        return (float)0;
    }

    /**
     * Get the result of a query
     * @return mixed result
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * This gets called by an agent when a request has completed
     * @param array $multiInfo result
     * @return void
     */
    public function callBack(array $multiInfo)
    {
        $this->notify();
    }

    /**
     * Add a listener that gets notified when the Request has completed
     * @param callable $function callback function
     * @return void
     */
    public function addListener(callable $function)
    {
        if (is_callable($function)) {
            if (!in_array($function, $this->listeners)) {
                $this->listeners[] = $function;
            }
        }
    }

    /**
     * Notify all listeners of request completion
     * @return void
     */
    protected function notify()
    {
        foreach ($this->listeners as $listener) {
            call_user_func($listener, $this);
        }
    }

    /**
     * Set a timeout value for the request
     * @param float $timeout timeout (msec)
     * @return void
     */
    public function setTimeout($timeout)
    {
        if ($timeout > 0) {
            $this->timeout = $timeout;
            $this->options[CURLOPT_TIMEOUT_MS] = $this->timeout;
        }
    }

    /**
     * Get the timeout value registered for the request
     * @return float timeout
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * Get the cUrl handle for the request
     * @return resource cUrl handle
     */
    public function getHandle()
    {
        if (!isset($this->curlHandle)) {
            $this->curlHandle = curl_init($this->url);
            curl_setopt_array($this->curlHandle, $this->options);
        }
        return $this->curlHandle;
    }

    /**
     * Add headers to the request
     * @param array $headers headers in ['key' => 'value] or ['key: value'] format
     * @return void
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers + $this->headers;
        $this->options[CURLOPT_HTTPHEADER] = $this->normalize($this->headers);
    }

    /**
     * Get headers set for the request
     * @return array headers in ['key' => 'value'] format
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Add cUrl options to the request
     * @param array $options options in ['key' => 'value'] format
     * @return void
     */
    public function setOptions(array $options)
    {
        $this->options = $options + $this->options;
    }

    /**
     * Get cUrl options set for the request
     * @return array options in ['key' => 'value'] format
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Get the response for the finished query
     * @return mixed response
     */
    public function getResponse()
    {
        if (!isset($this->response)) {
            if (isset($this->curlHandle)) {
                $this->response = curl_multi_getcontent($this->curlHandle);
            }
        }
        return $this->response;
    }

    /**
     * Get the Http code of the response
     * @return mixed
     */
    public function getHttpCode()
    {
        if (!isset($this->httpCode)) {
            if (isset($this->curlHandle)) {
                $this->httpCode = curl_getinfo($this->curlHandle)['http_code'];
            }
        }
        return $this->httpCode;
    }
}
