<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Wallet\WalletController;
use App\Http\Controllers\Api\Shop\ShopController;
use App\Http\Controllers\Api\BankTransfer\BankTransferController;
use App\Http\Controllers\Api\Profile\ProfileController;
use App\Http\Controllers\Api\Kyc\KycController;
use App\Http\Controllers\Api\RewardPass\RewardPassController;
use App\Http\Controllers\Api\RelationshipController;
use App\Http\Controllers\Api\HierarchyController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\GoogleSheetsController;

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

// Protected Routes (Require Authentication and Block Check)
Route::middleware(['auth:sanctum', 'check.blocked'])->group(function () {
    
    // Wallet Module
    Route::prefix('wallet')->group(function () {
        Route::get('balance', [WalletController::class, 'getBalance']);
        Route::get('transactions', [WalletController::class, 'getTransactions']);
        Route::middleware('role:agent,leader')->post('withdraw', [WalletController::class, 'requestWithdrawal']); // Only wallet holders
        Route::get('withdrawals', [WalletController::class, 'getWithdrawals']);
        Route::middleware('role:leader,admin')->post('credit', [WalletController::class, 'creditWallet']); // Leader/Admin only
        Route::middleware('role:agent')->get('payouts', [WalletController::class, 'getPayoutHistory']); // Agent payout history
    });
    
    // Shop Module (Onboarding)
    Route::prefix('shops')->group(function () {
        Route::middleware('role:agent')->post('/', [ShopController::class, 'create']); // Agent only
        Route::middleware('role:agent')->get('/agent', [ShopController::class, 'getByAgent']); // Agent only
        Route::middleware('role:leader')->get('/leader', [ShopController::class, 'getByLeader']); // Leader view only
        Route::get('/{id}', [ShopController::class, 'show']);
        
        // Admin Routes
        Route::middleware('role:admin')->group(function () {
            Route::get('/admin/onboarding-history', [ShopController::class, 'getOnboardingHistory']);
            Route::get('/admin/bank-transfer-history', [ShopController::class, 'getBankTransferHistory']);
            Route::get('/admin/daily-reports', [ShopController::class, 'getDailyReports']);
            Route::get('/admin/pending', [ShopController::class, 'getPendingForAdmin']);
            Route::put('/admin/{id}/approval', [ShopController::class, 'adminApproval']);
        });
    });

    // Statistics Module
    Route::prefix('statistics')->group(function () {
        Route::middleware('role:agent')->get('/agent', [ShopController::class, 'getAgentStatistics']); // Agent statistics
        Route::middleware('role:leader')->get('/leader', [ShopController::class, 'getLeaderStatistics']); // Leader team statistics
    });
    
    // Bank Transfer Module
    Route::prefix('bank-transfers')->group(function () {
        Route::middleware('role:agent')->post('/', [BankTransferController::class, 'create']); // Agent only
        Route::middleware('role:agent')->get('/agent', [BankTransferController::class, 'getByAgent']); // Agent only
        Route::middleware('role:leader')->get('/leader', [BankTransferController::class, 'getByLeader']); // Leader view only
        Route::get('/{id}', [BankTransferController::class, 'show']);
        
        // Admin Routes
        Route::middleware('role:admin')->group(function () {
            Route::get('/admin/pending', [BankTransferController::class, 'getPendingForAdmin']);
            Route::put('/admin/{id}/approval', [BankTransferController::class, 'adminApproval']);
        });
    });
    
    // Reward Pass Module
    Route::prefix('reward-passes')->group(function () {
        Route::middleware('role:agent')->post('/', [RewardPassController::class, 'create']); // Agent only
        Route::middleware('role:agent')->get('/agent', [RewardPassController::class, 'getByAgent']); // Agent only
        Route::middleware('role:leader')->get('/leader', [RewardPassController::class, 'getByLeader']); // Leader view only
        Route::get('/{id}', [RewardPassController::class, 'show']);
        
        // Admin Routes
        Route::middleware('role:admin')->group(function () {
            Route::get('/admin/all', [RewardPassController::class, 'getAllRewardPasses']);
            Route::get('/admin/pending', [RewardPassController::class, 'getPendingForAdmin']);
            Route::put('/admin/{id}/approval', [RewardPassController::class, 'adminApproval']);
        });
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
    
    // KYC Module
    Route::prefix('kyc')->group(function () {
        // Agent/Leader Routes
        Route::middleware('role:agent,leader')->post('/submit', [KycController::class, 'submitKyc']);
        Route::middleware('role:agent,leader')->get('/my-kyc', [KycController::class, 'getMyKyc']);
        
        // Admin Routes
        Route::middleware('role:admin')->group(function () {
            Route::get('/all', [KycController::class, 'getAllKyc']);
            Route::get('/pending', [KycController::class, 'getPendingKycs']);
            Route::get('/{id}', [KycController::class, 'getKycDetails']);
            Route::put('/{id}/review', [KycController::class, 'reviewKyc']);
        });
    });
    
    // Relationship Module - For tracking Leader-Agent relationships
    Route::prefix('relationships')->group(function () {
        Route::middleware('role:leader,admin')->get('/leader/{leaderId}/agents', [RelationshipController::class, 'getAgentsUnderLeader']);
        Route::middleware('role:leader,admin')->get('/leader/{leaderId}/hierarchy', [RelationshipController::class, 'getLeaderHierarchy']);
        Route::middleware('role:agent,leader,admin')->get('/agent/{agentId}/bank-transfers', [RelationshipController::class, 'getAgentBankTransfers']);
        Route::middleware('role:leader,admin')->get('/check-relation/{agentId}/{leaderId}', [RelationshipController::class, 'checkAgentLeaderRelation']);
    });
    
    // Hierarchy Module - Simplified parent-child relationships
    Route::prefix('hierarchy')->group(function () {
        Route::middleware('role:admin')->get('/my-leaders', [HierarchyController::class, 'getLeadersUnderAdmin']);
        Route::middleware('role:leader')->get('/my-agents', [HierarchyController::class, 'getAgentsUnderLeader']);
        Route::middleware('role:agent,leader')->get('/my-parent', [HierarchyController::class, 'getMyParent']);
        Route::middleware('role:admin')->get('/complete', [HierarchyController::class, 'getCompleteHierarchy']);
    });
    
    // Admin User Management Routes
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::post('/toggle-user-block', [AdminController::class, 'toggleUserBlock']);
        Route::get('/users', [AdminController::class, 'getUsersList']);
    });
    
    // App Settings Routes
    Route::get('/settings', [SettingsController::class, 'getAppSettings']); // For all authenticated users
    
    // Admin Settings Management Routes
    Route::prefix('admin/settings')->middleware('role:admin')->group(function () {
        Route::get('/all', [SettingsController::class, 'getAllSettings']);
        Route::post('/save', [SettingsController::class, 'saveSetting']);
        Route::post('/save-multiple', [SettingsController::class, 'saveMultipleSettings']);
        Route::delete('/delete', [SettingsController::class, 'deleteSetting']);
        Route::post('/toggle', [SettingsController::class, 'toggleSetting']);
    });
    
    // Google Sheets Integration Routes
    Route::prefix('google-sheets')->middleware('role:admin,leader')->group(function () {
        Route::post('/sync-today', [GoogleSheetsController::class, 'syncTodayData']);
    });
    
});

// Default route for testing
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
