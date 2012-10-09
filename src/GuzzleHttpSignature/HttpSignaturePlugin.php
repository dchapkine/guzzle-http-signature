<?php

namespace GuzzleHttpSignature;

use Guzzle\Common\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * PHP 5.3 port of node-http-signature (client part = signature creation)
 *
 * @see https://github.com/joyent/node-http-signature
 */
class HttpSignaturePlugin implements EventSubscriberInterface
{
    private $options;

    /**
     * Constructor
     *
     * @param array $options Signing options
     */
    public function __construct(array $options)
    {
		$this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
			'request.before_send' => 'onBeforeSend'
		);
    }
	
    /**
     * Sign HTTP request. This is a php port of the client part of "node-http-signature" library.
	 *
	 * This implementation curently supports following signature algorithms: "hmac-sha1", "hmac-sha256", "hmac-sha512"
     *
	 * @see https://github.com/joyent/node-http-signature/blob/master/lib/signer.js
     * @param Event $event
     */
    public function onBeforeSend(Event $event)
    {
		$req = $event['request'];
		
		//var_dump($req->getPath()); exit;
		
		$options = $this->options;
	
		// Available algorithms
		$algorithms = array(
			//'rsa-sha1',
			//'rsa-sha256',
			//'rsa-sha512',
			//'dsa-sha1',
			'hmac-sha1',
			'hmac-sha256',
			'hmac-sha512'
		);
	
		if (!isset($options['keyId']) || !is_string($options['keyId']))
			throw new \Exception("options.keyId must be a String");
		if (isset($options['algorithm']) && !is_string($options['algorithm']))
			throw new \Exception("options.algorithm must be a String");
		if (isset($options['headers']) && !is_array($options['headers']))
			throw new \Exception("options.headers must be an array of Strings");
		
		if ($req->getHeader('date') === null)
		{
			$req->setHeader('date', $this->_rfc1123());
		}
		
		if (!isset($options['headers']))
		{
			$options['headers'] = array('date');
		}
		else if (!in_array('date', $options['headers']))
		{
			$options['headers'] []= 'date';
		}
			
		if (!isset($options['algorithm']))
			$options['algorithm'] = 'rsa-sha256';
		
		$options['algorithm'] = strtolower($options['algorithm']);
		
		if (!in_array($options['algorithm'], $algorithms))
			throw new \Exception($options['algorithm']." is not supported");
			
		$i = 0;
		$stringToSign = '';
		foreach ($options['headers'] as $hval)
		{
			if (!is_string($hval))
				throw new \Exception("options.headers must be an array of Strings");
				
			$h = strtolower($hval);
			$value = $req->getHeader($h);
			if ($value === null)
			{
				if ($h === 'request-line')
				{
					$value = $req->getMethod() . ' ' . $this->getPath() + ' HTTP/1.1';
				}
				else
				{
					throw new \Exception($hval." was not in the request");
				}
			}
			
			$stringToSign .= $value;
			if (($i+1) < count($options['headers']))
				$stringToSign .= "\n";
			
			$i++;
		}
		
		if (preg_match('/(hmac|rsa)-(\w+)/', $options['algorithm'], $alg) !== 1)
			throw new \Exception("options.algorithm does not matche the required pattern");
			
		if ($alg[1] == 'hmac')
		{
			$hmac = hash_hmac(strtolower($alg[2]), $stringToSign, $options['key'], true);
			$signature = base64_encode($hmac);
		}
		else
		{
			throw new \Exception("Unsuported algorithm");
			/*
			var signer = crypto.createSign(options.algorithm.toUpperCase());
			signer.update(stringToSign);
			signature = signer.sign(options.key, 'base64');
			
			$pkeyid = openssl_get_privatekey($options['key']);
			openssl_sign($stringToSign, $signature, $options['key'], OPENSSL_ALGO_SHA1);
			*/
		}
		
		
		
		$authTemplate = 'Signature keyId="%s",algorithm="%s",headers="%s" %s';
		$req->setHeader('Authorization',
			sprintf($authTemplate,
					$options['keyId'],
					$options['algorithm'],
					implode(' ', $options['headers']),
					$signature));
    }

	/**
	 * Returns curent fate time in RFC1123 format, using UTC time zone
	 *
	 * @return string
	 */
	private function _rfc1123() {
	
		$date = new \DateTime(null, new \DateTimeZone("GMT"));
		return str_replace("+0000", "GMT", $date->format(\DateTime::RFC1123));
	}
	
}