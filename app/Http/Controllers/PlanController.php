<?php

namespace App\Http\Controllers;

use App\Helpers\CurrencyHelper;
use Auth;
use DB;
use Braintree\Gateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth as FacadesAuth;
use Illuminate\Support\Facades\DB as FacadesDB;

class PlanController extends Controller
{
    public function index()
    {
        $plans = $this->allPlans();

        return view('plan', compact('plans'));
    }

    public function gateway()
    {
        $braintreeSettings = FacadesDB::table('braintree_settings')->first();

        if (empty($braintreeSettings)) {
            abort(404, 'Braintree settings not found!');
        }

        return new Gateway([
            'environment' => $braintreeSettings->braintree_environment,
            'merchantId' => $braintreeSettings->braintree_sandbox_merchant_id,
            'publicKey' => $braintreeSettings->braintree_sandbox_public_key,
            'privateKey' => $braintreeSettings->braintree_sandbox_private_key
        ]);
    }

    public function allPlans()
    {
        return $this->gateway()->plan()->all();
    }

    public function findPlan($planId)
    {
        $plans = $this->gateway()->plan()->all();
        $plan = null;

        foreach ($plans as $planItem) {
            if ($planItem->id == $planId) {
                $plan = $planItem;
            }
        }

        return $plan;
    }

    public function isPlan($planId)
    {
        if (isset($this->findPlan($planId)->id)) {
            return true;
        }
        return false;
    }

    public function show($planId)
    {
        $authUser = FacadesAuth::user();
        $plans = $this->allPlans();

        $plan = null;

        foreach ($plans as $planItem) {
            if ($planItem->id == $planId) {
                $plan = $planItem;
            }
        }

        if (is_null($plan)) {
            abort(403, 'The Plan has not been found!');
        }

        $userBoughtPlan = false;

        if (!is_null($authUser)) {
            // $userPlan = DB::table('user_plans')->where('user_id', $authUser->id)->where('plan_id', $plan->id)->first();
            // if (!is_null($userPlan)) {
            //     $userBoughtPlan = true;
            // }
        }

        $currency = CurrencyHelper::getCurrencyString();

        return view('plan-item', compact('plan', 'userBoughtPlan', 'currency'));
    }
}
