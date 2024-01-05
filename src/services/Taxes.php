<?php

namespace QD\commerce\quickpay\services;

use craft\base\Component;
use craft\commerce\elements\Order;
use craft\commerce\records\TaxRate;

class Taxes extends Component
{
	/**
	 * Gets the product tax rate for an order
	 *
	 * @param Order $order
	 * @return integer|float
	 */
	public function getProductTaxRate(Order $order): int|float
	{
		$rates = $this->getTaxRatesForOrder($order);

		return $rates['lineVat'];
	}

	/**
	 * Gets the shipping tax rate for an order
	 *
	 * @param Order $order
	 * @return int|float
	 */
	public function getShippingTaxRate(Order $order): int|float
	{
		$rates = $this->getTaxRatesForOrder($order);

		return $rates['shippingVat'];
	}

	/**
	 * Gets the tax rates for an order
	 *
	 * @param Order $order
	 * @return array
	 */
	public function getTaxRatesForOrder(Order $order): array
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

	/**
	 * Get the applied taxes for an order
	 *
	 * @param Order $order
	 * @return array
	 */
	public function getAppliedTaxes(Order $order): array
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
