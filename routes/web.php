<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\BraintreeController;
use App\Http\Controllers\Admin\StripeController;
use App\Http\Controllers\Admin\PaypalController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\FrontendCourseController;
use App\Http\Controllers\Member\MemberDashboardController;
use App\Http\Controllers\PlanController;
use Braintree\WebhookNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes();

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('checkout/{courseSlug}', [CheckoutController::class, 'index'])->name('checkout')->middleware('auth');
Route::post('checkout/validate/{courseId}/{courseSlug}', [CheckoutController::class, 'prePaymentValidation'])->name('checkout.validate');
Route::post('checkout/fulfill/order', [CheckoutController::class, 'fulfillOrder'])->name('checkout.fulfill.order');
Route::post('payment/braintree', [BraintreeController::class, 'braintreePayment'])->name('braintree.payment');
Route::post('payment/stripe/{paymentIntentId}', [StripeController::class, 'getStripePaymentIntent'])->name('stripe.payment');
Route::get('checkout/success/thank-you', [CheckoutController::class, 'showThanks'])->name('thanks');

Route::get('privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('terms', [PageController::class, 'terms'])->name('terms');

Route::get('courses', [FrontendCourseController::class, 'index'])->name('courses.index');
Route::get('courses/{courseId}', [FrontendCourseController::class, 'show'])->name('courses.show');

Route::get('plans', [PlanController::class, 'index'])->name('plan.index');
Route::get('plans-all', [PlanController::class, 'allPlans'])->name('plan.all');
Route::get('plans/{planId}', [PlanController::class, 'show'])->name('plan.show');

Route::any('/paypal-webhook', function(Request $request) {
    Log::info('--- Paypal ---');
    Log::info(json_encode($request->all()));
});

Route::any('/braintree-webhook', function(Request $request) {

    Log::info(json_encode($request->all()));

    $gateway = (new PlanController())->gateway();

    if($request->has('test')) {
        # Gear icon (top right) > API > Keys > MerchantID
        $sampleNotification = $gateway->webhookTesting()->sampleNotification(
            Braintree\WebhookNotification::SUBSCRIPTION_WENT_PAST_DUE,
            '67zpbr'
        );

        Log::info('###');
        Log::info(json_encode($sampleNotification));

        $webhookNotification = $gateway->webhookNotification()->parse(
            $sampleNotification['bt_signature'], $sampleNotification['bt_payload']
        );

        Log::info(json_encode($webhookNotification));
    } else {
        Log::info('+++');
        $webhookNotification = $gateway->webhookNotification()->parse(
            $request->get('bt_signature'), $request->get('bt_payload')
        );

        Log::info(json_encode($webhookNotification));
        Log::info('+++');
    }



    return response()->json($request->all(), 200);

});

// Routes for Admin group
Route::group([
    'as'=>'admin.',
    'prefix'=>'admin',
    'middleware' => ['auth', 'admin']], // Checks if user is admin or not

    function() {
        // Admin dashboard
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        //Course routes
        Route::get('courses', [CourseController::class, 'index'])->name('courses');
        Route::get('courses/create', [CourseController::class, 'create'])->name('courses.create');
        Route::post('courses', [CourseController::class, 'store'])->name('courses.store');
        Route::get('courses/{courseId}/edit', [CourseController::class, 'edit'])->name('courses.edit');
        Route::put('courses/{courseId}', [CourseController::class, 'update'])->name('courses.update');
        Route::delete('courses/{courseId}', [CourseController::class, 'destroy'])->name('courses.destroy');

        // Braintree routes
        Route::get('payments/braintree', [BraintreeController::class, 'index'])->name('braintree');
        Route::put('payments/braintree/update', [BraintreeController::class, 'update'])->name('braintree.update');

        // Stripe routes
        Route::get('payments/stripe', [StripeController::class, 'index'])->name('stripe');
        Route::put('payments/stripe/update', [StripeController::class, 'update'])->name('stripe.update');

        // PayPal routes
        Route::get('payments/paypal', [PaypalController::class, 'index'])->name('paypal');
        Route::put('payments/paypal/update', [PaypalController::class, 'update'])->name('paypal.update');

        // Settings route
        Route::get('payments/settings', [SettingsController::class, 'index'])->name('settings');
        Route::put('payments/settings/update', [SettingsController::class, 'update'])->name('settings.update');

        // Users route
        Route::get('users', [UsersController::class, 'index'])->name('users');
        Route::delete('users/{userId}', [UsersController::class, 'destroy'])->name('users.destroy');

        // Orders route
        Route::get('orders', [OrdersController::class, 'index'])->name('orders');
        Route::delete('orders/{orderId}', [OrdersController::class, 'destroy'])->name('orders.destroy');
        Route::delete('orders-cancel/{orderId}', [OrdersController::class, 'cancel'])->name('orders.cancel');

    });


// Routes for Member group
Route::group([
    'as'=>'member.',
    'prefix'=>'member',
    'middleware' => ['auth']],

    function() {
        Route::get('dashboard', [MemberDashboardController::class, 'index'])->name('dash');
    }
);
