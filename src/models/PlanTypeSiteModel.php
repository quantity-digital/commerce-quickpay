<?php

namespace QD\commerce\quickpay\models;

use Craft;
use craft\base\Model;
use craft\models\Site;
use QD\commerce\quickpay\Plugin;
use yii\base\InvalidConfigException;

class PlanTypeSiteModel extends Model
{
    // Properties
    // =========================================================================

    public $id;
    public $planTypeId;
    public $siteId;
    public $hasUrls;
    public $uriFormat;
    public $template;
    public $uriFormatIsRequired = true;

    private $planType;
    private $site;


    // Public Methods
    // =========================================================================

    public function getPlanType(): PlanTypeModel
    {
        if ($this->planType !== null) {
            return $this->planType;
        }

        if (!$this->planTypeId) {
            throw new InvalidConfigException('Site is missing its plan type ID');
        }

        if (($this->planType = Plugin::$plugin->getPlanTypes()->getPlanTypeById($this->planTypeId)) === null) {
            throw new InvalidConfigException('Invalid plan type ID: ' . $this->planTypeId);
        }

        return $this->planType;
    }

    public function setPlanType(PlanTypeModel $planType)
    {
        $this->planType = $planType;
    }

    public function getSite(): Site
    {
        if (!$this->site) {
            $this->site = Craft::$app->getSites()->getSiteById($this->siteId);
        }

        return $this->site;
    }

    public function rules(): array
    {
        $rules = parent::rules();

        if ($this->uriFormatIsRequired) {
            $rules[] = ['uriFormat', 'required'];
        }

        return $rules;
    }
}
