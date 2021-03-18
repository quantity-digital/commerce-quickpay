<?php

namespace QD\commerce\quickpay\services;

use craft\base\Component;
use craft\commerce\base\SubscriptionGateway;
use craft\commerce\models\Transaction;
use craft\commerce\services\Gateways;
use craft\commerce\elements\Order;
use QD\commerce\quickpay\gateways\Gateway;
use QD\commerce\quickpay\models\PaymentRequestModel;
use QD\commerce\quickpay\Plugin;
use QD\commerce\quickpay\responses\CaptureResponse;
use QD\commerce\quickpay\responses\PaymentResponse;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\web\ServiceUnavailableHttpException;
use QD\commerce\quickpay\gateways\Subscriptions;
use QD\commerce\quickpay\responses\RefundResponse;
use yii\base\Exception;

class Payments extends Component
{

    public $api;

    public function init()
    {
        $this->api = Plugin::$plugin->getApi();
    }

    public function initiatePayment(PaymentRequestModel $paymentRequest)
    {
        $payload = $paymentRequest->getPayload();

        //Create payment
        $request = $this->api->post('/payments', $payload);

        return $request;
    }

    public function getPaymentLink(PaymentRequestModel $paymentRequest, $request)
    {
        //Create link to payment (redirect to it)
        $amount = $paymentRequest->getLinkPayload();
        $link = $this->api->put('/payments/' . $request->id . '/link', $amount);

        return $link;
    }

    public function intiatePaymentFromGateway(Transaction $transaction)
    {
        //Set gateway for API
        $this->api->setGateway($transaction->getGateway());

        $order = $transaction->getOrder();
        $paymentRequest = new PaymentRequestModel([
            'order'       => $order,
            'transaction' => $transaction,
        ]);

        //Create payment at quickpay
        $request = $this->initiatePayment($paymentRequest);
        $response = new PaymentResponse($request);

        //If payment wasn't created return with error message
        if (!$response->isSuccessful()) {
            return $response;
        }

        //Get redirect url
        $request = $this->getPaymentLink($paymentRequest, $request);

        if (!$request) {
            return false;
        }

        //Set redirect url inside response
        $url = $request->url ?? null;
        if ($url) {
            $response->setRedirectUrl($url);
        }

        return $response;
    }

    public function captureFromGateway(Transaction $transaction): CaptureResponse
    {
        $order                   = $transaction->getOrder();
        $authorizedTransation = $this->getSuccessfulTransactionForOrder($order);
        $authorizedAmount     = (int)$transaction->amount;
        $amount               = $order->getOutstandingBalance();

        $this->api->setGateway($order->getGateway());

        //Outstanding amount is larger than the authorized value - set amount to be equal to authorized value
        if ($authorizedAmount < $amount) {
            $amount = $authorizedAmount;
        }

        if ($authorizedAmount > $amount) {
            $transaction->amount = $amount;
            $transaction->paymentAmount = $amount;
        }

        $response = $this->api->post("/payments/{$authorizedTransation->reference}/capture", [
            'amount' => $amount * 100
        ]);

        return new CaptureResponse($response);
    }

    public function refundFromGateway(Transaction $transaction): RefundResponse
    {
        $amount     = (int)$transaction->amount * 100;
        $response = Plugin::$plugin->api->post("/payments/{$transaction->reference}/refund", [
            'amount' => $amount
        ]);

        return new RefundResponse($response);
    }

    /**
     * @param Order $order
     *
     * @return Transaction|null
     */
    public function getSuccessfulTransactionForOrder(Order $order)
    {
        foreach ($order->getTransactions() as $transaction) {

            if (
                $transaction->status === TransactionRecord::STATUS_SUCCESS
                && $transaction->type === TransactionRecord::TYPE_AUTHORIZE
            ) {

                return $transaction;
            }
        }
    }

    public function getGateway(): Gateway
    {
        $gateways = new Gateways();
        $quickpayGateway = null;

        foreach ($gateways->getAllCustomerEnabledGateways() as $gateway) {

            if ($gateway instanceof Gateway) {
                $quickpayGateway = $gateway;
                break;
            }
        }

        if (!$quickpayGateway) {
            throw new Exception('The Quickpay gateway is not setup correctly.');
        }

        return $gateway;
    }
}
