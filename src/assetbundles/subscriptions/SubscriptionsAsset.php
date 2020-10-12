<?php

namespace QD\commerce\quickpay\assetbundles\subscriptions;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class SubscriptionsAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = "@QD/commerce/quickpay/assetbundles/subscriptions/dist";

        $this->depends = [
            CpAsset::class,
        ];

        $this->css = [
            'css/Subscription.css',
        ];
        parent::init();
    }
}
