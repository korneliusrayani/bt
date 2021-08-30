<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Country\Country;
use DB;
use Auth;
use Braintree;
use App\Helpers\CurrencyHelper;
use Illuminate\Support\Facades\Validator;
use App\Rules\OnlyAsciiCharacters;
use App\Models\UserCourse\UserCourse;
use App\Helpers\OrderDataHelper;
use App\Models\Order\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Auth as FacadesAuth;
use Illuminate\Support\Facades\DB as FacadesDB;

class CheckoutController extends Controller
{
    public function index($courseSlug, Request $request)
    {
        $planController = new PlanController;
        $isPlan = $planController->isPlan($courseSlug);
        $product = null;
        $productType = null;
        $productItem = new Product;

        if ($isPlan) {
            $product = $planController->findPlan($courseSlug);
            $productType = 'plan';

            $productItem->id = $product->id;
            $productItem->slug = $product->id;
            $productItem->title = $product->name;
            $productItem->price = $product->price;
            $productItem->description = $product->description;
            $productItem->type = 'plan';
        } else {
            $product = FacadesDB::table('courses')->where('slug', $courseSlug)->first();

            $productItem->id = $product->id;
            $productItem->slug = $product->slug;
            $productItem->title = $product->title;
            $productItem->price = $product->price;
            $productItem->description = $product->description;
            $productItem->type = 'item';

            if (is_null($product)) {
                abort(403, 'Invalid course!');
            }
        }


        $authUser = FacadesAuth::user();
        // $userCourse = FacadesDB::table('user_courses')->where('user_id', $authUser->id)->where('course_id', $course->id)->first();

        // if ((!is_null($userCourse)) && ($authUser->role->id != 1)) {
        //     abort(403, 'You already have access to this course!');
        // }

        $countries = Country::with('statesInOrder')->orderBy('name', 'ASC')->get();

        $email = $authUser->email;

        $settings = FacadesDB::table('settings')->first();

        if (is_null($settings)) {
            abort(403, 'Settings not found!');
        }

        $currencyTextRaw = $settings->currency;
        $currency = CurrencyHelper::getCurrencyString();
        $braintreeEnabled = $settings->enable_braintree;
        $stripeEnabled = $settings->enable_stripe;
        $payPalSmartEnabled = $settings->enable_paypal_smart;

        $btToken = "";
        $brainTreeLabel = "Credit card by Braintree";

        if ($braintreeEnabled) {

            $payPalWithinBraintreeEnabled = $settings->enable_paypal_in_bt;

            if ($payPalWithinBraintreeEnabled) {
                $brainTreeLabel = "Credit Card and PayPal by Braintree";
            }

            $createCustomer = $planController->gateway()->customer()->create([
                'firstName' => FacadesAuth::user()->first_name,
                'lastName' => FacadesAuth::user()->last_name,
                'company' => 'CUSTOMER',
                'email' => FacadesAuth::user()->email,
                'phone' => FacadesAuth::user()->phone?? '',
                'fax' => FacadesAuth::user()->fax?? '',
                'website' => FacadesAuth::user()->site?? '',
            ]);

            $gateway = $planController->gateway();

            $btToken = $gateway->ClientToken()->generate([
                'customerId' => $createCustomer->customer->id
            ]);
        }

        $stripePubKey = "";

        if ($stripeEnabled) {

            $stripeSettings = FacadesDB::table('stripe_settings')->first();

            if (is_null($stripeSettings)) {
                abort(403, 'Stripe settings not found!');
            }
            if ($stripeSettings->stripe_environment == "test") {
                $stripePubKey = $stripeSettings->stripe_test_publishable_key;
            } else {
                $stripePubKey = $stripeSettings->stripe_live_publishable_key;
            }
        }


        return view('checkout', compact(
            'product',
            'productItem',
            'countries',
            'email',
            'currency',
            'braintreeEnabled',
            'stripeEnabled',
            'payPalSmartEnabled',
            'brainTreeLabel',
            'btToken',
            'stripePubKey',
            'currencyTextRaw'
        ));
    }

    public function prePaymentValidation(Request $request, $courseId, $courseSlug)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:191', new OnlyAsciiCharacters],
            'last_name' => ['required', 'string', 'max:191', new OnlyAsciiCharacters],
            'street' => ['required', 'string', 'max:191'],
            'apartment' => ['nullable', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:191'],
            'city' => ['required', 'string', 'max:191'],
            'state' => ['required', 'string', 'exists:states,state_code'],
            'country' => ['required', 'string', 'exists:countries,code'],
            'zip' => ['required', 'string', 'max:150'],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors());
        }

        // $courseToBuy = FacadesDB::table('courses')->find($courseId);
        // if (is_null($courseToBuy)) {
        //     return response()->json(['error' => 'The course does not exist.']);
        // }

        // if ($courseToBuy->slug != $courseSlug) {
        //     return response()->json(['error' => 'Discrepancy in course data.']);
        // }

        // if ($courseToBuy->price != $request->total) {
        //     return response()->json(['error' => 'Price discrepancy.']);
        // }

        return response()->json(['successful_validation' => 'success']);
    }

    public function fulfillOrder(Request $request)
    {
        $user = FacadesAuth::user();
        if (is_null($user)) {
            return redirect()->back()->withInput()->with('failureMsg', 'Payment received but logged-in user not found!');
        }

        if($request->get('product_type') == 'item') {
            $course = FacadesDB::table('courses')->find($request->course);
            if (is_null($course)) {
                return redirect()->back()->withInput()->with('failureMsg', 'Payment received but the course has not been found!');
            }
        }

        if($request->get('product_type') == 'plan') {
            $planController = new PlanController;

            $course = $planController->findPlan($request->course);

            if (is_null($course)) {
                return redirect()->back()->withInput()->with('failureMsg', 'Payment received but the plan has not been found!');
            }
        }

        $transactionId = $request->transaction_id;
        $orderData = [];
        OrderDataHelper::getOrderData($orderData, $request, $user, $course->title, $transactionId);
        $order = new Order;
        foreach ($orderData as $key => $orderValue) {
            $order->$key = $orderData[$key];
        }
        $order->save();

        $userCourse = FacadesDB::table('user_courses')->where('user_id', $user->id)->where('course_id', $course->id)->first();
        if (is_null($userCourse)) {
            $newUserCourse = new UserCourse;
            $newUserCourse->user_id = $user->id;
            $newUserCourse->course_id = $course->id;
            $newUserCourse->save();
        }

        return redirect()->route('thanks');
    }

    public function showThanks()
    {
        return view('thank-you');
    }
}
