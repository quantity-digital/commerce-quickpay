<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Quantity Digital
 * @license MIT
 */

namespace QD\commerce\quickpay\gateways;

use Craft;
use craft\commerce\base\Gateway as BaseGateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin as CommercePlugin;
use craft\web\ServiceUnavailableHttpException;
use QD\commerce\quickpay\Plugin;

class Gateway extends BaseGateway
{

    const SUPPORTS = [
        'Authorize' => true,
        'Capture' => true,
        'CompleteAuthorize' => false,
        'CompletePurchase' => false,
        'PaymentSources' => false,
        'Purchase' => true,
        'Refund' => true,
        'PartialRefund' => true,
		'Void' => true,
        'Webhooks' => false,
    ];

    const PAYMENT_TYPES = [
        'authorize' => 'Authorize Only (Manually Capture)',
    ];

	use GatewayTrait;

    //Settings options
    public $api_key;
    public $private_key;
    public $analyticsId;
    public $brandingId;
    public $autoCapture;
    public $autoCaptureStatus;

    // Settings
    // =========================================================================

    /**
     * Returns the display name of this class.
     *
     * @return string The display name of this class.
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Quickpay');
    }

    /**
     * Returns the component’s settings HTML.
     *
     * @return string|null
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \yii\base\Exception
     */
    public function getSettingsHtml()
    {
		//craft.commerce.orderStatuses.allOrderStatuses

		foreach (CommercePlugin::getInstance()->getOrderStatuses()->getAllOrderStatuses() as $status) {
            $statusOptions[] = [
				'value' => $status->handle,
				'label' => $status->displayName
			];
        }

        return Craft::$app->getView()->renderTemplate('commerce-quickpay/settings', ['gateway' => $this, 'statusOptions' => $statusOptions]);
    }

	/**
	 * Returns the payment type options.
	 *
	 * @return array
	 */
	public function getPaymentTypeOptions(): array
	{
		return self::PAYMENT_TYPES;
	}

	/**
	 * Makes an authorize request.
	 *
	 * @param Transaction $transaction The authorize transaction
	 * @param BasePaymentForm $form A form filled with payment info
	 * @return RequestResponseInterface
	 */
	public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
	{
		//TODO : Implement paymentlink to be sent to customer for manual orders
		$response = Plugin::$plugin->getPayments()->intiatePaymentFromGateway($transaction);
		if(!$response){
			throw new ServiceUnavailableHttpException(Craft::t('commerce', 'An error occured when communicatiing with Quickpay. Please try again.'));
		}

		return $response;
	}

	/**
	 * Makes a capture request.
	 *
	 * @param Transaction $transaction The capture transaction
	 * @param string $reference Reference for the transaction being captured.
	 * @return RequestResponseInterface
	 */
	public function capture(Transaction $transaction, string $reference): RequestResponseInterface
	{
		$response = Plugin::$plugin->getPayments()->captureFromGateway($transaction);

		return $response;
	}

	/**
	 * Makes an refund request.
	 *
	 * @param Transaction $transaction The refund transaction
	 * @return RequestResponseInterface
	 */
	public function refund(Transaction $transaction): RequestResponseInterface
	{
		$response = Plugin::$plugin->getPayments()->refundFromGateway($transaction);

		return $response;
	}
}
