<?php

namespace QD\commerce\quickpay\responses;

use Craft;
use craft\commerce\base\RequestResponseInterface;

class SubscriptionResponse implements RequestResponseInterface
{
	/**
	 * @var
	 */
	protected $data = [];
	/**
	 * @var string
	 */
	private $_redirect = '';
	/**
	 * @var bool
	 */
	private $_processing = false;

	private $_error;
	private $_code = 200;

	/**
	 * Response constructor.
	 *
	 * @param $data
	 */
	public function __construct($data)
	{
		$this->data = $data;

		$statusCode = $this->data->error_code ?? null;
		$message    = $this->data->message ?? null;

		if ($statusCode) {
			$this->_code = $statusCode;
		}

		if ($statusCode && $statusCode > 299) {
			$this->_error = $message;
		}
	}

	// Public Properties
	// =========================================================================

	public function setRedirectUrl(string $url)
	{
		$this->_redirect = $url;
	}

	public function setProcessing(bool $status)
	{
		$this->_processing = $status;
	}


	/**
	 * Returns whether or not the payment was successful.
	 *
	 * @return bool
	 */
	public function isSuccessful(): bool
	{
		if ($this->isRedirect()) {
			return false;
		}

		return !$this->_error;
	}

	/**
	 * Returns whether or not the payment is being processed by gateway.
	 *
	 * @return bool
	 */
	public function isProcessing(): bool
	{
		return $this->_processing;
	}

	/**
	 * @inheritdoc
	 */
	public function isRedirect(): bool
	{
		return !empty($this->_redirect);
	}

	/**
	 * @inheritdoc
	 */
	public function getRedirectMethod(): string
	{
		return 'GET';
	}


	/**
	 * Returns the redirect data provided.
	 *
	 * @return array
	 */
	public function getRedirectData(): array
	{
		return [];
	}

	/**
	 * Returns the redirect URL to use, if any.
	 *
	 * @return string
	 */
	public function getRedirectUrl(): string
	{
		return $this->_redirect;
	}

	/**
	 * Returns the transaction reference.
	 *
	 * @return string
	 */
	public function getTransactionReference(): string
	{
		if (empty($this->data->id)) {
			return '';
		}

		return (string)$this->data->id;
	}

	/**
	 * Returns the response code.
	 *
	 * @return string
	 */
	public function getCode(): string
	{
		return $this->_code;
	}

	/**
	 * Returns the data.
	 *
	 * @return mixed
	 */
	public function getData()
	{
		return $this->data;
	}

	/**
	 * Returns the gateway message.
	 *
	 * @return string
	 */
	public function getMessage(): string
	{
		if ($this->_error) {
			return $this->_error;
		}

		return '';
	}

	/**
	 * Perform the redirect.
	 *
	 * @return mixed
	 */
	public function redirect()
	{
		return Craft::$app->getResponse()->redirect($this->_redirect)->send();
	}

}
