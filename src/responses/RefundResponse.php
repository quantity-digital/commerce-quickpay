<?php

namespace QD\commerce\quickpay\responses;

use craft\commerce\base\RequestResponseInterface;

class RefundResponse implements RequestResponseInterface
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

	/**
	 * Response constructor.
	 *
	 * @param $data
	 */
	public function __construct($data)
	{
		$this->data = $data;
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
		return !isset($this->data->errors);
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
		if (isset($this->data->errors)) {
			return $this->data->error_code ? $this->data->error_code : '';
		}

		return '200';
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
		return '';
	}

	/**
	 * Perform the redirect.
	 *
	 * @return mixed
	 */
	public function redirect()
	{
		return false;
	}
}
