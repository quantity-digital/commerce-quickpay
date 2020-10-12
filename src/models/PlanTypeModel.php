<?php
namespace QD\commerce\quickpay\models;

use craft\base\Model;
use craft\behaviors\FieldLayoutBehavior;
use craft\helpers\ArrayHelper;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;
use QD\commerce\quickpay\elements\Plan;
use QD\commerce\quickpay\Plugin;
use QD\commerce\quickpay\records\PlanTypeRecord;

class PlanTypeModel extends Model
{
    // Properties
    // =========================================================================

    public $id;
    public $name;
    public $handle;
    public $skuFormat;
    public $template;
    public $fieldLayoutId;

    private $_siteSettings;

    // Public Methods
    // =========================================================================

    public function __toString()
    {
        return $this->handle;
    }

    public function rules()
    {
        return [
            [['id', 'fieldLayoutId'], 'number', 'integerOnly' => true],
            [['name', 'handle'], 'required'],
            [['name', 'handle'], 'string', 'max' => 255],
            [['handle'], UniqueValidator::class, 'targetClass' => PlanTypeRecord::class, 'targetAttribute' => ['handle'], 'message' => 'Not Unique'],
            [['handle'], HandleValidator::class, 'reservedWords' => ['id', 'postDate', 'dateUpdated', 'uid', 'title']],
        ];
    }

    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce-quickpay/types/' . $this->id);
    }

    public function getSiteSettings(): array
    {
        if ($this->_siteSettings !== null) {
            return $this->_siteSettings;
        }

        if (!$this->id) {
            return [];
        }

        $this->setSiteSettings(ArrayHelper::index(Plugin::$plugin->planTypes->getPlanTypeSites($this->id), 'siteId'));

        return $this->_siteSettings;
    }

    public function setSiteSettings(array $siteSettings)
    {
        $this->_siteSettings = $siteSettings;

        foreach ($this->_siteSettings as $settings) {
            $settings->setPlanType($this);
        }
    }

    public function getPlanFieldLayout(): FieldLayout
    {
        $behavior = $this->getBehavior('planFieldLayout');
        return $behavior->getFieldLayout();
    }

    public function behaviors(): array
    {
        return [
            'planFieldLayout' => [
                'class' => FieldLayoutBehavior::class,
                'elementType' => Plan::class,
                'idAttribute' => 'fieldLayoutId'
            ]
        ];
    }
}
