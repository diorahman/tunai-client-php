<?php

namespace Sandiloka\Tunai;

use Dflydev\Hawk\Credentials\Credentials;
use Dflydev\Hawk\Client\ClientBuilder;
use Guzzle\Http\Message\Response;
use Guzzle\Http\Client;

/**
 * Tunai.id invoice API.
 *
 * The APIs defined in here mostly for interacting with the Invoice endpoints and used by merchants.
 */
class Invoice
{
    /*
     * Tunai.id production root url
     */
    const TUNAI_URL = 'https://api.tunai.id/v1';
    
    /** @var string Tunai.id key **/
    private $key;
    /** @var string Tunai.id secret **/
    private $secret;
    
    /** members **/
    private $rootUrl;
    private $credentials;
    private $client;
    private $httpClient;

    /**
     * Create a new invoice based on key and secret.
     *
     * The `key` and `secret` reflect ownership of the newly created invoice.
     *
     * @param string $key Application key.
     * @param string $secret Application secret.
     * @param string $rootUrl The root url this application interacts with.
     */
    public function __construct($key, $secret, $rootUrl = self::TUNAI_URL)
    {
        $this->key = base64_encode('api:' . $key . ':' . $secret);
        $this->secret = $secret;
        $this->rootUrl = $rootUrl;

        $this->httpClient = new Client();
    }    

    /**
     * Get an invoice by merchant's reference id
     *
     * This should be called by merchants only.
     *
     * @param string $ref Reference id of an invoice.
     */
    public function getByRef($ref)
    {
        $res = $this->makeHttpRequest('/invoices/by-ref/' . $ref);
        return $res;
    }
 
    /**
     * Get an invoice by id
     *
     * @param string $id Invoice id.
     */
    public function getById($id)
    {
        $res = $this->makeHttpRequest('/invoices/' . $id);
        return $res;
    }
    
    /**
     * Create an invoice
     *
     * @param array $invoice Invoice data.
     */
    public function create($invoice)
    {
        $json = json_encode($invoice);
        $res = $this->makeHttpRequest('/invoices', 'POST', $json, 'application/json');
        return $res;
    }

    /**
     * Try to get an invoice using its merchant's refId
     * If it doesn't exist it will create a new invoice using the provided data
     *
     * @param array $invoice Invoice data.
     */
    public function getByRefOrCreate($invoice)
    {
        $res = $this->getByRef($invoice['refId']);
        if ($res->getStatusCode() == 404)
        {
            $res = $this->create($invoice);
            return $res;
        }
        return $res;
    }

    private function makeHttpRequest(
        $path,
        $method = 'GET',
        $payload = '',
        $contentType = '',
        $query = array()
    )
    {
        $url = $this->rootUrl . $path;

        // Make hawk request
        $hawkRequest = $this->makeHawkRequest(
            $url,
            $method,
            $payload,
            $contentType,
            $query
        );
        
        // Generate http request options
        $httpRequestOptions = 
            $this->generateHttpRequestOptions(
                $hawkRequest->header()->fieldValue(), 
                $payload,
                $query
            );
        $httpRequestOptions['exceptions'] = false;
        $request =  $this->httpClient->createRequest($method, $url, array(), $payload, $httpRequestOptions);
        $res = $request->send();
        return $res;
    }

    private function generateHttpRequestOptions($auth, $payload, $query = array())
    {
        $requestOptions = array
        (
            'verify' => false,
            'query' => $query
        );

        $requestHeaders = array
        (
            'Authorization' => $auth
        );

        $requestHeaders['Authorization'] = $auth;
        $requestOptions['verify'] = false;

        if ($payload)
        {
            $requestOptions['body'] = $payload;
            $requestHeaders['Content-Type'] = 'application/json';
        }
        
        $requestOptions['headers'] = $requestHeaders;
        return $requestOptions;
    }

    private function makeHawkRequest(
        $url,
        $method = 'GET',
        $payload = '',
        $contentType = '',
        $ext = array()
    ) {
        $this->client = $this->buildClient();
        $requestOptions = $this->generateRequestOptions($payload, $contentType, $ext);
        $request = $this->client->createRequest(
            $this->getCredentials(),
            $url,
            $method,
            $requestOptions
        );
        return $request;
    }

    private function buildClient()
    {
        $builder = ClientBuilder::create();
        return $builder->build();
    }

    private function generateRequestOptions($payload, $contentType, $ext = array())
    {
        $requestOptions = array();
        if ($payload && $contentType) {
            $requestOptions['payload'] = $payload;
            $requestOptions['content_type'] = $contentType;
        }

        if ($ext)
        {
            $requestOptions['ext'] = http_build_query($ext);
        }
        return $requestOptions;
    }

    private function getCredentials()
    {
        if ($this->credentials == null) 
        {
            $this->credentials = $this->generateCredentials($this->key, $this->secret);
        }
        return $this->credentials;
    }

    private function generateCredentials($key, $secret, $algorithm = 'sha256')
    {
        return new Credentials($secret, $algorithm, $key);
    }  
}
