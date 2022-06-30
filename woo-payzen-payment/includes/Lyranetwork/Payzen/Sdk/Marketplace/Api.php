<?php
/**
 * Copyright © Lyra Network and contributors.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network and contributors
 * @license   See COPYING.md for license details.
 */

namespace Lyranetwork\Payzen\Sdk\Marketplace;

/**
 * Cient class for Marketplace web services API.
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
    private $token;

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
     * @param string $username
     * @param string $password
     */
    public function __construct($endpoint, $username, $password)
    {
        if (empty($endpoint) || !preg_match('#^https?://(\w+(:\w*)?@)?(\S+)(:[0-9]+)?[\w\#!:.?+=&%@`~;,|!\-/]*$#u', $endpoint)) {
            throw new \InvalidArgumentException('Endpoint must be a valid URL.');
        }

        if (empty($username)) {
            throw new \InvalidArgumentException('Username is mandatory.');
        }

        if (empty($password)) {
            throw new \InvalidArgumentException('Password is mandatory.');
        }

        $this->endpoint = $endpoint;
        $this->token = $username . ':' . $password;
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
     * @param string $method
     * @param string $target
     * @param mixed $data
     * @return array|bool
     * @throws Exception
     */
    public function request($method, $target, $data = array())
    {
        if (extension_loaded('curl')) {
            return $this->curlRequest($method, $target, $data);
        }

        return $this->fallbackRequest($method, $target, $data);
    }

    /**
     * @param string $method
     * @param string $target
     * @param mixed $data
     * @return array|bool
     * @throws Exception
     */
    protected function curlRequest($method, $target, $data = array())
    {
        $url = $this->endpoint . $target;

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-type: application/json',
            'User-Agent: Plugins Marketplace PHP SDK'
        ));
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);

        if (! empty($data)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }

        curl_setopt($curl, CURLOPT_USERPWD, $this->token);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectionTimeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);

        if ($this->proxyHost && $this->proxyPort) {
            curl_setopt($curl, CURLOPT_PROXY, $this->proxyHost);
            curl_setopt($curl, CURLOPT_PROXYPORT, $this->proxyPort);
        }

        // We disable SSL validation for test key because there is a lot of WAMP installations that do not handle certificates well.
        $test_mode = strpos($this->endpoint, '/marketplace-test/') !== false;
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, ! $test_mode);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, ! $test_mode);

        $rawResponse = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if (in_array($status, array(301, 302), true)) {
            curl_close($curl);
            return true;
        }

        if (!in_array($status, array(200, 201, 202, 203, 204, 205, 206, 207, 208, 209), true)) {
            curl_close($curl);
            throw new \Exception(
                "Call to URL $url failed with unexpected status: $status, response: $rawResponse",
                $status
            );
        }

        if (empty($rawResponse)) {
            curl_close($curl);
            return true;
        }

        $response = json_decode($rawResponse, true);
        if (! is_array($response)) {
            $error = curl_error($curl);
            $errno = curl_errno($curl);

            curl_close($curl);
            throw new \Exception("Call to URL $url failed, response: $rawResponse, curl_error: $error, curl_errno: $errno.");
        }

        curl_close($curl);
        return $response;
    }

    /**
     * @param string $method
     * @param string $target
     * @param mixed $data
     * @return array|bool
     * @throws Exception
     */
    protected function fallbackRequest($method, $target, $data = array())
    {
        $url = $this->endpoint . $target;

        $http = array(
            'method'  => $method,
            'header'  => 'Authorization: Basic ' . base64_encode($this->token) . "\r\n".
            'Content-Type: application/json' . "\r\n" .
            'User-Agent: Plugins Marketplace PHP SDK',
            'timeout' => $this->timeout
        );

        if (! empty($data)) {
            $http['content'] = json_encode($data);
        }

        if ($this->proxyHost && $this->proxyPort) {
            $http['proxy'] = $this->proxyHost . ':' . $this->proxyPort;
        }

        $ssl = array();

        // We disable SSL validation for test key because there is a lot of WAMP installations that do not handle certificates well.
        $test_mode = strpos($this->endpoint, '/marketplace-test/') !== false;
        $ssl['verify_peer'] = ! $test_mode;
        $ssl['verify_peer_name'] = ! $test_mode;

        $context = stream_context_create(array('http' => $http, 'ssl' => $ssl));
        $rawResponse = file_get_contents($url, false, $context);

        if (! $rawResponse) {
            return true;
        }

        $response = json_decode($rawResponse, true);
        if (! is_array($response)) {
            throw new \Exception("Call to URL $url failed, response: $rawResponse.");
        }

        return $response;
    }
}
