<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Wallet\WalletController;
use App\Http\Controllers\Api\Shop\ShopController;
use App\Http\Controllers\Api\BankTransfer\BankTransferController;
use App\Http\Controllers\Api\Profile\ProfileController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Auth Routes (No middleware)
Route::prefix('auth')->group(function () {
    Route::post('login/admin', [AuthController::class, 'loginAdmin']);
    Route::post('login/leader', [AuthController::class, 'loginLeader']);
    Route::post('login/agent', [AuthController::class, 'loginAgent']);
    
    // Protected auth routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::middleware('role:admin')->post('register/leader', [AuthController::class, 'registerLeader']);
        Route::middleware('role:leader')->post('register/agent', [AuthController::class, 'registerAgent']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('profile', [AuthController::class, 'profile']);
    });
});

// Protected Routes (Require Authentication)
Route::middleware('auth:sanctum')->group(function () {
    
    // Wallet Module
    Route::prefix('wallet')->group(function () {
        Route::get('balance', [WalletController::class, 'getBalance']);
        Route::get('transactions', [WalletController::class, 'getTransactions']);
        Route::middleware('role:agent,leader')->post('withdraw', [WalletController::class, 'requestWithdrawal']); // Only wallet holders
        Route::get('withdrawals', [WalletController::class, 'getWithdrawals']);
        Route::middleware('role:leader,admin')->post('credit', [WalletController::class, 'creditWallet']); // Leader/Admin only
    });
    
    // Shop Module (Onboarding)
    Route::prefix('shops')->group(function () {
        Route::middleware('role:agent')->post('/', [ShopController::class, 'create']); // Agent only
        Route::middleware('role:agent')->get('/agent', [ShopController::class, 'getByAgent']); // Agent only
        Route::middleware('role:leader')->get('/leader', [ShopController::class, 'getByLeader']); // Leader only
        Route::middleware('role:leader')->put('/{id}/status', [ShopController::class, 'updateStatus']); // Leader only
        Route::get('/{id}', [ShopController::class, 'show']);
        
        // Admin Routes
        Route::middleware('role:admin')->group(function () {
            Route::get('/admin/onboarding-history', [ShopController::class, 'getOnboardingHistory']);
            Route::get('/admin/bank-transfer-history', [ShopController::class, 'getBankTransferHistory']);
            Route::get('/admin/daily-reports', [ShopController::class, 'getDailyReports']);
        });
    });
    
    // Bank Transfer Module
    Route::prefix('bank-transfers')->group(function () {
        Route::middleware('role:agent')->post('/', [BankTransferController::class, 'create']); // Agent only
        Route::middleware('role:agent')->get('/agent', [BankTransferController::class, 'getByAgent']); // Agent only
        Route::middleware('role:leader')->get('/leader', [BankTransferController::class, 'getByLeader']); // Leader only
        Route::middleware('role:leader')->put('/{id}/status', [BankTransferController::class, 'updateStatus']); // Leader only
        Route::get('/{id}', [BankTransferController::class, 'show']);
    });
    
    // Profile Module
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'getProfile']);
        Route::post('/update', [ProfileController::class, 'updateProfile']);
        Route::post('/bank-details', [ProfileController::class, 'addBankDetails']);
        Route::get('/bank-details', [ProfileController::class, 'getBankDetails']);
        Route::put('/bank-details/{id}', [ProfileController::class, 'updateBankDetails']);
        Route::delete('/bank-details/{id}', [ProfileController::class, 'deleteBankDetails']);
    });
    
});

// Default route for testing
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
