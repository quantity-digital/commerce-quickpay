<?php

namespace QD\commerce\quickpay\services;

use craft\base\Component;
use craft\commerce\elements\Order;
use craft\commerce\records\TaxRate;

class Taxes extends Component
{

	public function getProductTaxRate(Order $order): int|float
	{
		$rates = $this->getTaxRatesForOrder($order);

		return $rates['lineVat'];
	}

	public function getShippingTaxRate(Order $order)
	{
		$rates = $this->getTaxRatesForOrder($order);

		return $rates['shippingVat'];
	}

	public function getTaxRatesForOrder(Order $order)
	{
		$taxRates = $this->getAppliedTaxes($order);
		$lineVat = 0;
		$shippingVat = 0;

		foreach ($taxRates as $key => $taxRate) {

			if ($taxRate['taxable'] === TaxRate::TAXABLE_PRICE) {
				$lineVat = $taxRate['rate'];
			}

			if ($taxRate['taxable'] === TaxRate::TAXABLE_ORDER_TOTAL_SHIPPING) {
				$shippingVat = $taxRate['rate'];
			}

			if ($taxRate['taxable'] === TaxRate::TAXABLE_ORDER_TOTAL_PRICE) {
				$shippingVat = $taxRate['rate'];
				$lineVat = $taxRate['rate'];
			}

			if ($taxRate['taxable'] === TaxRate::TAXABLE_PRICE_SHIPPING) {
				$shippingVat = $taxRate['rate'];
			}

			if ($taxRate['taxable'] === TaxRate::TAXABLE_SHIPPING) {
				$shippingVat = $taxRate['rate'];
			}
		}

		return [
			'lineVat' => $lineVat,
			'shippingVat' => $shippingVat
		];
	}

	public function getAppliedTaxes($order)
	{
		$adjustments = $order->getAdjustments();
		$taxRates = [];
		foreach ($adjustments as $adjustment) {
			if ($adjustment->type === 'tax') {
				$taxRates[] = $adjustment->sourceSnapshot;
			}
		}

		return $taxRates;
	}
}
