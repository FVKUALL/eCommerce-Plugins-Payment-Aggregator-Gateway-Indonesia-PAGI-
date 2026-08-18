<?php

use Illuminate\Support\Facades\Route;
use Webkul\BDPay\Http\Controllers\BDPayController;

Route::group(['middleware' => ['web']], function () {
    Route::get('bdpay/redirect', [BDPayController::class, 'redirect'])->name('bdpay.redirect');
    Route::post('bdpay/callback', [BDPayController::class, 'callback'])->name('bdpay.callback');
});
