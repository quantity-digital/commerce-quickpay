<?php

namespace QD\commerce\quickpay\assetbundles\plans;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class PlansAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = "@QD/commerce/quickpay/assetbundles/plans/dist";

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/Plans.js',
        ];
        parent::init();
    }
}
