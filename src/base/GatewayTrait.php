<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Quantity Digital
 * @license MIT
 */

namespace QD\commerce\quickpay\base;

use Craft;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\OffsitePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\Transaction;
use craft\commerce\errors\NotImplementedException;
use craft\web\Response as WebResponse;
use yii\base\NotSupportedException;

trait GatewayTrait
{

	/**
	 * Returns payment Form HTML
	 *
	 * @param array $params
	 *
	 * @return string|null
	 */
	public function getPaymentFormHtml(array $params) : string
	{
		return '';
	}

	/**
	 * Creates a payment source from source data and user id.
	 *
	 * @param BasePaymentForm $sourceData
	 * @param int             $userId
	 *
	 * @return PaymentSource
	 */
	Public function createPaymentSource(BasePaymentForm $sourceData, int $userId): PaymentSource
	{
		throw new NotImplementedException('Not implemented by the payment gateway');
	}

	/**
	 * Deletes a payment source on the gateway by its token.
	 *
	 * @param string $token
	 *
	 * @return bool
	 */
	public function deletePaymentSource($token): bool
	{
		return false;
	}

	/**
	 * Processes a webhook and return a response
	 *
	 * @return WebResponse
	 * @throws \Throwable if something goes wrong
	 */
	public function processWebHook(): WebResponse
	{
		throw new NotImplementedException('Not implemented by the payment gateway');
	}

	/**
	 * Returns payment form model to use in payment forms.
	 *
	 * @return BasePaymentForm
	 */
	public function getPaymentFormModel(): BasePaymentForm
	{
		return new OffsitePaymentForm();
	}

	/**
	 * Complete the authorization for offsite payments.
	 *
	 * @param Transaction $transaction The transaction
	 *
	 * @return RequestResponseInterface
	 * @throws NotSupportedException
	 */
	public function completeAuthorize(Transaction $transaction): RequestResponseInterface
	{
		throw new NotSupportedException(Craft::t('commerce', 'Complete Authorize is not supported by this gateway'));
	}

		/**
	 * Complete the purchase for offsite payments.
	 *
	 * @param Transaction $transaction The transaction
	 * @return RequestResponseInterface
	 * @throws NotImplementedException
	 */
	public function completePurchase(Transaction $transaction): RequestResponseInterface
	{
		throw new NotImplementedException('Not implemented by the payment gateway');
	}

	/**
	 * Makes a purchase request.
	 *
	 * @param Transaction $transaction The purchase transaction
	 * @param BasePaymentForm $form A form filled with payment info
	 * @return RequestResponseInterface
	 */
	public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
	{
		throw new NotImplementedException('Not implemented by the payment gateway');
	}

	//============== Support errors occur because the trait is imported into the Gateway, which has the SUPPORTS array
	/**
	 * 
	 * Returns true if gateway supports authorize requests.
	 *
	 * @return bool
	 */
	public function supportsAuthorize(): bool
	{
		return self::SUPPORTS['Authorize'];
	}

	/**
	 * Returns true if gateway supports capture requests.
	 *
	 * @return bool
	 */
	public function supportsCapture(): bool
	{
		return self::SUPPORTS['Capture'];
	}

	/**
	 * Returns true if gateway supports completing authorize requests
	 *
	 * @return bool
	 */
	public function supportsCompleteAuthorize(): bool
	{
		return self::SUPPORTS['CompleteAuthorize'];
	}

	/**
	 * Returns true if gateway supports completing purchase requests
	 *
	 * @return bool
	 */
	public function supportsCompletePurchase(): bool
	{
		return self::SUPPORTS['CompletePurchase'];
	}

	/**
	 * Returns true if gateway supports payment sources
	 *
	 * @return bool
	 */
	public function supportsPaymentSources(): bool
	{
		return self::SUPPORTS['PaymentSources'];
	}

	/**
	 * Returns true if gateway supports purchase requests.
	 *
	 * @return bool
	 */
	public function supportsPurchase(): bool
	{
		return self::SUPPORTS['Purchase'];
	}

	/**
	 * Returns true if gateway supports refund requests.
	 *
	 * @return bool
	 */
	public function supportsRefund(): bool
	{
		return self::SUPPORTS['Refund'];
	}

	/**
	 * Returns true if gateway supports partial refund requests.
	 *
	 * @return bool
	 */
	public function supportsPartialRefund(): bool
	{
		return self::SUPPORTS['PartialRefund'];
	}

	/**
	 * Returns true if gateway supports webhooks.
	 *
	 * @return bool
	 */
	public function supportsWebhooks(): bool
	{
		return self::SUPPORTS['Webhooks'];
	}
}
