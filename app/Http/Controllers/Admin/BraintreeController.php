<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use DB;
use App\Models\PaymentGateway\BraintreeSetting;
use App\Helpers\AmountConverterHelper;
use Braintree;
use App\Helpers\OrderDataHelper;
use App\Http\Controllers\PlanController;
use App\Models\Order\Order;
use App\Models\UserCourse\UserCourse;
use Auth;
use Illuminate\Support\Facades\Auth as FacadesAuth;
use Illuminate\Support\Facades\DB as FacadesDB;
use Illuminate\Support\Facades\Log;

class BraintreeController extends Controller
{
    public function index()
    {
        $meta_title = "Braintree Settings";
        $braintreeSettings = FacadesDB::table('braintree_settings')->first();
        if (is_null($braintreeSettings)) {
            abort(403, 'Braintree settings not found!');
        }
        return view('admin.payments.braintree', compact('meta_title', 'braintreeSettings'));
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'braintree_environment' => ['required', Rule::in(['sandbox', 'production'])],
            'braintree_sandbox_merchant_id' => ['nullable', 'string'],
            'braintree_sandbox_public_key' => ['nullable', 'string'],
            'braintree_sandbox_private_key' => ['nullable', 'string'],
            'braintree_production_merchant_id' => ['nullable', 'string'],
            'braintree_production_public_key' => ['nullable', 'string'],
            'braintree_production_private_key' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $braintreeSettings = BraintreeSetting::first();
        if (is_null($braintreeSettings)) {
            abort(403, 'Braintree Settings not found!');
        }

        $braintreeSettings->fill($request->all());
        $braintreeSettings->save();

        return redirect()->back()->with('successMsg', 'The Braintree Settings have been successfully updated!');
    }

    public function braintreePayment(Request $request)
    {
        $user = FacadesAuth::user();
        $nonce = $request->get('nonce');

        if (is_null($user)) {
            return redirect()->back()->withInput()->with('failureMsg', 'Logged-in user not found!');
        }

        $planController = new PlanController;
        $gateway = $planController->gateway();

        $product = null;

        if ($request->get('product_type') == 'item') {
            $product = FacadesDB::table('courses')->find($request->get('course'));

            if (is_null($product)) {
                return redirect()->back()->withInput()->with('failureMsg', 'The product has not been found!');
            }
        } else {

            $product = $planController->findPlan($request->course);

            if (is_null($product)) {
                return redirect()->back()->withInput()->with('failureMsg', 'The plan has not been found!');
            }
        }


        $total = AmountConverterHelper::getBraintreeAmountBasedOnCurrency($request->total);

        if ($request->get('product_type') == 'item') {
            $result = $gateway->transaction()->sale([
                'amount' => $total,
                'paymentMethodNonce' => $nonce,
                'options' => [
                    'submitForSettlement' => true
                ]
            ]);
        }

        if ($request->get('product_type') == 'plan') {
            $result = $gateway->subscription()->create([
                'planId' => $product->id,
                'paymentMethodNonce' => $nonce,
            ]);
        }

        if ($result->success) {
            Log::info('---Purchase plan successfully---');
            Log::info(json_encode($result));

            $transactionId = "000";

            $transaction = null;

            if ($request->get('product_type') == 'item') {
                $transaction = $result->transaction;
            }

            if ($request->get('product_type') == 'plan') {
                $transaction = $result->subscription;
            }

            if (!is_null($transaction)) {
                $transactionId = $transaction->id;
            }

            $title = null;

            if($request->get('product_type')=='plan') {
                $title = $product->name;
            }


            if($request->get('product_type')=='item') {
                $title = $product->title;
            }

            $orderData = [];
            OrderDataHelper::getOrderData($orderData, $request, $user, $title, $transactionId);

            $order = new Order;
            foreach ($orderData as $key => $orderValue) {
                $order->$key = $orderData[$key];
            }
            $order->save();

            $userCourse = FacadesDB::table('user_courses')->where('user_id', $user->id)->where('course_id', $product->id)->first();

            if (is_null($userCourse)) {
                $newUserCourse = new UserCourse;
                $newUserCourse->user_id = $user->id;
                $newUserCourse->subscription_type = $product->id;
                $newUserCourse->save();
            }

            return redirect()->route('thanks');
        } else {
            $errorString = "";

            foreach ($result->errors->deepAll() as $error) {
                $errorString .= 'Error: ' . $error->code . ": " . $error->message . "\n";
            }

            return redirect()->back()->withInput()->with('failureMsg', $result->message);
        }
    }
}
