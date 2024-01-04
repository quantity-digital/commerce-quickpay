<?php

namespace QD\commerce\quickpay\models;

use craft\base\Model;

class PaymentResponseModel extends Model
{
  public function __construct(
    public readonly int $id,
    public readonly string $ulid,
    public readonly int $merchant_id,
    public readonly string $order_id,
    public readonly string $accepted,
    public readonly string $type,
    public readonly string $text_on_statement,
    public readonly int|null $branding_id,
    public readonly array $variables,
    public readonly string $currency,
    public readonly string $state,
    public readonly array $metadata,
    public readonly array $link,
    public readonly array|null $shipping_address,
    public readonly array|null $invoice_address,
    public readonly array $basket,
    public readonly array|null $shipping,
    public readonly array $operations,
    public readonly bool $test_mode,
    public readonly string $acquirer,
    public readonly string|null $facilitator,
    public readonly string $created_at,
    public readonly string $updated_at,
    public readonly string $retented_at,
    public readonly float $balance,
    public readonly float|null $fee,
    public readonly string|null $deadline_at,
  ) {
  }
}
