<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AppSetting;

class AppSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'app_version',
                'value' => '1.0.0',
                'type' => 'string',
                'description' => 'Current version of the mobile application',
            ],
            [
                'key' => 'app_name',
                'value' => 'AXN Group',
                'type' => 'string',
                'description' => 'Application name',
            ],
            [
                'key' => 'app_download_link_android',
                'value' => 'https://play.google.com/store/apps/details?id=com.axngroup',
                'type' => 'string',
                'description' => 'Google Play Store download link for Android app',
            ],
            [
                'key' => 'app_download_link_ios',
                'value' => 'https://apps.apple.com/app/axngroup',
                'type' => 'string',
                'description' => 'App Store download link for iOS app',
            ],
            [
                'key' => 'force_update',
                'value' => false,
                'type' => 'boolean',
                'description' => 'Whether to force users to update the app',
            ],
            [
                'key' => 'minimum_version',
                'value' => '1.0.0',
                'type' => 'string',
                'description' => 'Minimum required version of the app',
            ],
            [
                'key' => 'maintenance_mode',
                'value' => false,
                'type' => 'boolean',
                'description' => 'Enable/disable maintenance mode for the app',
            ],
            [
                'key' => 'maintenance_message',
                'value' => 'The app is currently under maintenance. Please try again later.',
                'type' => 'string',
                'description' => 'Message to display during maintenance mode',
            ],
            [
                'key' => 'support_email',
                'value' => 'support@axngroup.com',
                'type' => 'string',
                'description' => 'Support email address',
            ],
            [
                'key' => 'support_phone',
                'value' => '+91-1234567890',
                'type' => 'string',
                'description' => 'Support phone number',
            ],
            [
                'key' => 'company_address',
                'value' => 'AXN Group Head Office, City, Country',
                'type' => 'string',
                'description' => 'Company address',
            ],
            [
                'key' => 'terms_url',
                'value' => 'https://axngroup.com/terms',
                'type' => 'string',
                'description' => 'Terms and conditions URL',
            ],
            [
                'key' => 'privacy_url',
                'value' => 'https://axngroup.com/privacy',
                'type' => 'string',
                'description' => 'Privacy policy URL',
            ],
            [
                'key' => 'max_withdrawal_amount',
                'value' => 50000,
                'type' => 'integer',
                'description' => 'Maximum withdrawal amount allowed',
            ],
            [
                'key' => 'min_withdrawal_amount',
                'value' => 100,
                'type' => 'integer',
                'description' => 'Minimum withdrawal amount required',
            ],
            [
                'key' => 'app_features',
                'value' => [
                    'shop_onboarding' => true,
                    'bank_transfers' => true,
                    'reward_passes' => true,
                    'kyc_verification' => true,
                    'wallet_management' => true
                ],
                'type' => 'json',
                'description' => 'Available features in the app',
            ],
            [
                'key' => 'announcement',
                'value' => 'Welcome to AXN Group! Start onboarding shops and earn rewards.',
                'type' => 'string',
                'description' => 'General announcement message for users',
            ],
            [
                'key' => 'show_announcement',
                'value' => true,
                'type' => 'boolean',
                'description' => 'Whether to show announcement in the app',
            ]
        ];

        foreach ($settings as $setting) {
            AppSetting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => is_array($setting['value']) ? json_encode($setting['value']) : $setting['value'],
                    'type' => $setting['type'],
                    'description' => $setting['description'],
                    'is_active' => true
                ]
            );
        }

        $this->command->info('App settings seeded successfully!');
    }
}
