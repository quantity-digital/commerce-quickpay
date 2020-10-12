<?php

namespace QD\commerce\quickpay\models;

use craft\commerce\base\Plan;
use craft\commerce\base\PlanInterface;
use craft\helpers\Json;

class QuickpayPlan extends Plan
{
    /**
     * @inheritdoc
     */
    public function canSwitchFrom(PlanInterface $currentPlan): bool
    {
        return true;
    }

	public function getPlanData()
    {
        return Json::decode($this->planData,false);
    }

	public function getInterval(){
		$planData = $this->getPlanData();
		return isset($planData->interval) ? $planData->interval : null;
	}

	public function getBillingPrice(){
		$planData = $this->getPlanData();
		return isset($planData->billingPrice) ? $planData->billingPrice : null;
	}

	public function getTrialDays(){
		$planData = $this->getPlanData();
		return isset($planData->trialDays) ? $planData->trialDays : 0;
	}

	public function setTrialDays(){
		$planData = $this->getPlanData();
		return isset($planData->trialDays) ? $planData->trialDays : 0;
	}
}
