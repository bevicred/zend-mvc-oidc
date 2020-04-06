<?php

namespace Zend\Mvc\OIDC\Common\Infra;

/**
 * Class HttpClient
 *
 * @package Zend\Mvc\OIDC\Common\Infra
 */
class HttpClient
{

    public function sendRequest(
        string $baseUrl,
        string $method = 'GET',
        string $path = '/',
        array $headers = array(),
        string $data = ''
    ) {
        $method = strtoupper($method);
        $url = $baseUrl . $path;

        // Initiate HTTP request
        $request = curl_init();

        curl_setopt($request, CURLOPT_URL, $url);
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);

        if ($method === 'POST') {
            curl_setopt($request, CURLOPT_POST, true);
            curl_setopt($request, CURLOPT_POSTFIELDS, $data);
            array_push($headers, 'Content-Length: ' . strlen($data));
        }

        curl_setopt($request, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($request);
        $response_code = curl_getinfo($request, CURLINFO_HTTP_CODE);
        curl_close($request);

        return array(
            'code' => $response_code,
            'body' => $response
        );
    }
}