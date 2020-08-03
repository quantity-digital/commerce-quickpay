<?php

namespace QD\commerce\quickpay\services;

use Craft;
use craft\base\Component;
use QD\commerce\quickpay\gateways\Gateway;
use QD\commerce\quickpay\Plugin;
use QuickPay\QuickPay;

class Api extends Component
{

	private $_client;

	// Public Methods
	// =========================================================================

	public function init()
	{
		// Set initial token
		$this->_client = new QuickPay(":".$this->getApiKey());
	}

	public function post($url,$payload = []){
		$response = $this->_client->request->post($url, $payload);

		return $response->asObject();
	}

	public function put($url,$payload = []){
		$response = $this->_client->request->put($url, $payload);

		if ($response->isSuccess()){
			return $response->asObject();
		}

		//TODO Handle error in request
	}

	private function getApiKey(){

		$gateway = Plugin::$plugin->getPayments()->getGateway();

		return Craft::parseEnv($gateway->api_key);
	}

}
