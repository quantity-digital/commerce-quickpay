<?php

namespace QD\commerce\quickpay\responses;

use craft\commerce\base\RequestResponseInterface;

class CaptureResponse implements RequestResponseInterface
{
	/**
	 * @var
	 */
	protected mixed $data = [];

	/**
	 * @var string
	 */
	private string $_redirect = '';

	/**
	 * @var bool
	 */
	private bool $_processing = false;

	/**
	 * Response constructor.
	 *
	 * @param mixed $data
	 */
	public function __construct(mixed $data)
	{
		$this->data = $data;
	}

	// Public Properties
	// =========================================================================

	/**
	 * Encapsulates the _redirect
	 *
	 * @param string $url
	 * @return void
	 */
	public function setRedirectUrl(string $url): void
	{
		$this->_redirect = $url;
	}

	/**
	 * Encapsulates the _processing
	 *
	 * @param boolean $status
	 * @return void
	 */
	public function setProcessing(bool $status): void
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
		if (isset($this->data->message) && strpos($this->data->message, 'Not found:')) {
			return false;
		}

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
		return (string)$this->data->id ?? '';
	}

	/**
	 * Returns the response code.
	 *
	 * @return string
	 */
	public function getCode(): string
	{
		if (isset($this->data->errors)) {
			return $this->data->error_code ?? '500';
		}

		return '200';
	}

	/**
	 * Returns the data.
	 *
	 * @return mixed
	 */
	public function getData(): mixed
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
	 * @return void
	 */
	public function redirect(): void
	{
	}
}
