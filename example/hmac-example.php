<?php

require_once '../vendor/autoload.php';

use Guzzle\Http\Client;
use Guzzle\Http\Exception\ClientErrorResponseException;
use Guzzle\Http\Exception\BadResponseException;
use GuzzleHttpSignature\HttpSignaturePlugin;
use Guzzle\Http\Exception\CurlException;



//
// request config
//
$requestUrl = 'https://api.domain.tld/jobs/44';
$requestMethod = 'POST';
$requestData = array("some" => array("custom" => array("data")));
$requestHeaders = array('content-md5' => md5(http_build_query($requestData)));


//
// signature config
//
$keyId = "Test";			// your key id
$key = "secret";			// your private key
$algorithm = "hmac-sha512";	// algorithm: hmac-sha1, hmac-sha256, hmac-sha512
$headersToSign = array(		// headers we want to include into signature
	"date",
	"content-md5"
);


//
// sending request
//
try
{
	$plugin = new HttpSignaturePlugin(array(
		'keyId' => $keyId,
		'key' => $key,
		'algorithm' => $algorithm,
		'headers' => $headersToSign
	));
	
	$client = new Client();
	$client->addSubscriber($plugin);
	$req = $client->createRequest($requestMethod, $requestUrl, $requestHeaders, $requestData);
	$response = $req->send();
}
catch (ClientErrorResponseException $e)
// guzzle throws ClientErrorResponseException when error http codes are sent (401, 500, ...)
{
	$response = $e->getResponse();
}
catch (CurlException $e)
// the api provider is probably down or there is an issue with connection
{
	$msg = $e->getMessage();
}


//
// print response
//
header('Content-Type: text');
if (isset($response))
	echo "\n" . $response->getStatusCode() . "\n" . $response->getBody(true) . "\n";
else
	echo $msg."\n";

