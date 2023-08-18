<?php

namespace QD\commerce\quickpay\responses;

use Craft;
use craft\commerce\base\RequestResponseInterface;

class PaymentResponse implements RequestResponseInterface
{
	/**
	 * @var
	 */
	protected mixed $data = [];
	public mixed $errors = [];
	public string $message = '';

	private string $_redirect = '';
	private bool $_processing = false;
	public int $_code = 200;

	/**
	 * Response constructor.
	 *
	 * @param $data
	 */
	public function __construct(mixed $data)
	{
		$this->data = $data;

		$statusCode = $this->data->error_code ?? null;
		$this->message  = $this->data->message ?? '';

		if ($statusCode) {
			$this->_code = $statusCode;
		}

		if(isset($data->errors)){
			$this->errors = json_decode(json_encode ( $data->errors ) , true);
		}
	}

	// Public Properties
	// =========================================================================

	/**
	 * Encapsulates _redirect
	 *
	 * @param string $url
	 * @return void
	 */
	public function setRedirectUrl(string $url): void
	{
		$this->_redirect = $url;
	}

	/**
	 * Encapsulates processing
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
		if($this->errors){
			return false;
		}

		return true;
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
		return (string) $this->data->id ?? '';
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
		return $this->message ?? '';
	}

	/**
	 * Perform the redirect.
	 *
	 * @return mixed
	 */
	public function redirect(): void
	{
		Craft::$app->getResponse()->redirect($this->_redirect)->send();
	}
}
