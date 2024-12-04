<?php

namespace QD\commerce\quickpay\services;

use craft\helpers\App;
use craft\commerce\base\GatewayInterface as BaseGatewayInterface;
use craft\base\Component;
use QuickPay\QuickPay;
use stdClass;

class Api extends Component
{

	private QuickPay $client;
	private BaseGatewayInterface $gateway;

	// Public Methods
	// =========================================================================


	/**
	 * Set client, and creates the client
	 *
	 * @param BaseGatewayInterface $gateway
	 * @return Api this so chaining can occur
	 */
	public function setGateway(BaseGatewayInterface $gateway): Api
	{
		$this->gateway = $gateway;
		$this->client = new QuickPay(":" . $this->getApiKey());

		return $this;
	}

	/**
	 * GET: queries the client with a get request
	 *
	 * @param string $url
	 * @param array $payload
	 * @return stdClass
	 */
	public function get(string $url, mixed $payload = []): stdClass
	{
		$response = $this->client->request->get($url, $payload);

		return $response->asObject();
	}

	/**
	 * POST: queries the client with a post request
	 *
	 * @param string $url
	 * @param mixed $payload
	 * @return stdClass
	 */
	public function post(string $url, mixed $payload = []): stdClass
	{
		$response = $this->client->request->post($url, $payload);

		return $response->asObject();
	}

	/**
	 * DELETE: queries the client with a post request
	 *
	 * @param string $url
	 * @param mixed $payload
	 * @return stdClass
	 */
	public function delete(string $url, mixed $payload = []): stdClass
	{
		$response = $this->client->request->delete($url, $payload);
		return $response->asObject();
	}

	/**
	 * PUT: queries the client with a put request
	 *
	 * @param string $url
	 * @param mixed $payload
	 * @return stdClass
	 */
	public function put(string $url, mixed $payload = []): stdClass
	{
		$response = $this->client->request->put($url, $payload);
		return $response->asObject();
	}

	/**
	 * Set headers
	 *
	 * @param array $headers
	 * @return Api
	 */
	public function setHeaders(array $headers): Api
	{
		$this->client->setHeaders($headers);
		return $this;
	}

	/**
	 * Get the api key from the gateway
	 *
	 * @return string
	 */
	private function getApiKey(): string
	{
		return App::parseEnv($this->gateway->api_key);
	}
}
