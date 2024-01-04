<?php

namespace QD\commerce\quickpay\plugin;

abstract class Data
{
  const STATE_INITIAL = 'initial';
  const STATE_NEW = 'new';
  const STATE_PENDING = 'pending';
  const STATE_PROCESSED = 'processed';
  const STATE_REJECTED = 'rejected';
}
