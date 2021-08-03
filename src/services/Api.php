<?php

namespace QD\commerce\quickpay\services;

use Craft;
use craft\base\Component;
use QD\commerce\quickpay\Plugin;
use QuickPay\QuickPay;

class Api extends Component
{

	private $client;
	private $gateway;

	// Public Methods
	// =========================================================================

	public function init($gateway = null)
	{
	}

	public function setGateway($gateway)
	{
		$this->gateway = $gateway;
		$this->client = new QuickPay(":" . $this->getApiKey());

		return $this;
	}

	public function get($url, $payload = [])
	{
		$response = $this->client->request->get($url, $payload);

		return $response->asObject();
	}

	public function post($url, $payload = [])
	{
		$response = $this->client->request->post($url, $payload);

		return $response->asObject();
	}

	public function delete($url, $payload = [])
	{
		$response = $this->client->request->delete($url, $payload);
		return $response->asObject();
	}

	public function put($url, $payload = [])
	{
		$response = $this->client->request->put($url, $payload);

		if ($response->isSuccess()) {
			return $response->asObject();
		}
	}

	public function setHeaders($headers)
	{
		$this->client->setHeaders($headers);
		return $this;
	}

	private function getApiKey()
	{
		return Craft::parseEnv($this->gateway->api_key);
	}
}
