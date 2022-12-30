<?php
/**
 * Copyright © Lyra Network and contributors.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @author    Simon Sprankel (https://github.com/sprankhub)
 * @copyright Lyra Network and contributors
 * @license   See COPYING.md for license details.
 */

namespace Lyranetwork\Payzen\Sdk\Rest;

/**
 * Cient class for REST web services API.
 */
class Api
{
    /**
     * @var string
     */
    private $endpoint;

    /**
     * @var string
     */
    private $privateKey;

    /**
     * @var int
     */
    private $connectionTimeout = 45;

    /**
     * @var int
     */
    private $timeout = 45;

    /**
     * @var null|string
     */
    private $proxyHost;

    /**
     * @var null|int|string
     */
    private $proxyPort;

    /**
     * @param string $endpoint
     * @param string $site_id
     * @param string $password
     */
    public function __construct($endpoint, $site_id, $password)
    {
        if (empty($endpoint)) {
            throw new \InvalidArgumentException('Endpoint parameter is mandatory.');
        }

        if (empty($site_id)) {
            throw new \InvalidArgumentException('Site ID is mandatory.');
        }

        if (empty($password)) {
            throw new \InvalidArgumentException('Private key is mandatory.');
        }

        $this->endpoint = $endpoint;
        $this->privateKey = $site_id . ':' . $password;
    }

    /**
     * @param null|string $host
     * @param string|int $port
     * @return $this
     */
    public function setProxy($host, $port)
    {
        $this->proxyHost = $host;
        $this->proxyPort = $port;

        return $this;
    }

    /**
     * @param int $connectionTimeout Maximum amount of time in seconds that is allowed to make the
     *      connection to the server. It can be set to 0 to disable this limit, but this is inadvisable
     *      in a production environment.
     * @param int $timeout Maximum amount of time in seconds to which the execution of individual
     *      cURL extension function calls will be limited. Note that the value for this setting should
     *      include the value for CURLOPT_CONNECTTIMEOUT.
     *      In other words, CURLOPT_CONNECTTIMEOUT is a segment of the time represented by
     *      CURLOPT_TIMEOUT, so the value of the CURLOPT_TIMEOUT should be greater than the value of
     *      the CURLOPT_CONNECTTIMEOUT.
     * @return $this
     */
    public function setTimeouts($connectionTimeout, $timeout)
    {
        $this->connectionTimeout = $connectionTimeout;
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * @param string $target
     * @param mixed $data
     * @return array
     * @throws Exception
     */
    public function post($target, $data)
    {
        if (extension_loaded('curl')) {
            return $this->curlPost($target, $data);
        }

        return $this->fallbackPost($target, $data);
    }

    /**
     * @param string $target
     * @param mixed $data
     * @return array
     * @throws Exception
     */
    protected function curlPost($target, $data)
    {
        $url = $this->endpoint . $target;

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Plugins REST API PHP SDK');
        curl_setopt($curl, CURLOPT_USERPWD, $this->privateKey);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectionTimeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);

        // We disable SSL validation for test key because there is a lot of WAMP installations that do not handle certificates well.
        $test_mode = strpos($this->privateKey, 'testpassword_') !== false;
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $test_mode ? 0 : 2);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, ! $test_mode);

        $raw_response = curl_exec($curl);

        $info = curl_getinfo($curl);
        if (! in_array($info['http_code'], array(200, 401), true)) {
            $error = curl_error($curl);
            $errno = curl_errno($curl);
            curl_close($curl);

            $msg = "Call to URL $url failed with unexpected status: {$info['http_code']}";

            if ($raw_response) {
                $msg .= ", raw response: $raw_response";
            }

            if ($errno) {
                $msg .= ", cURL error: $error ($errno)";
            }

            $msg .= ", cURL info: " . print_r($info, true);

            throw new \Exception($msg, '-1');
        }

        $response = json_decode($raw_response, true);
        if (! is_array($response)) {
            $error = curl_error($curl);
            $errno = curl_errno($curl);
            curl_close($curl);

            $msg = "Call to URL $url returned an unexpected response, raw response: $raw_response";

            if ($errno) {
                $msg .= ", cURL error: $error ($errno)";
            }

            $msg .= ", cURL info: " . print_r($info, true);

            throw new \Exception($msg, '-1');
        }

        curl_close($curl);

        return $response;
    }

    /**
     * @param string $target
     * @param mixed $data
     * @return array
     * @throws Exception
     */
    protected function fallbackPost($target, $data)
    {
        $url = $this->endpoint . $target;

        $http = array(
            'method'  => 'POST',
            'header'  => 'Authorization: Basic ' . base64_encode($this->privateKey) . "\r\n".
                         'Content-Type: application/json',
            'content' => $data,
            'user_agent' => 'Plugins REST API PHP SDK',
            'timeout' => $this->timeout
        );

        if ($this->proxyHost && $this->proxyPort) {
            $http['proxy'] = $this->proxyHost . ':' . $this->proxyPort;
        }

        $ssl = array();

        // We disable SSL validation for test key because there is a lot of WAMP installations that do not handle certificates well.
        $test_mode = strpos($this->privateKey, 'testpassword_') !== false;
        $ssl['verify_peer'] = ! $test_mode;
        $ssl['verify_peer_name'] = ! $test_mode;

        $context = stream_context_create(array('http' => $http, 'ssl' => $ssl));
        $raw_response = file_get_contents($url, false, $context);

        if (! $raw_response) {
            throw new \Exception("Error: call to URL $url failed.", '-1');
        }

        $response = json_decode($raw_response, true);
        if (! is_array($response)) {
            throw new \Exception("Error: call to URL $url failed, response $raw_response.", '-1');
        }

        return $response;
    }
}
