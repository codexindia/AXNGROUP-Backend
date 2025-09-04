<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    /**
     * Get all app settings (for agents and team leaders)
     *
     * @return JsonResponse
     */
    public function getAppSettings(): JsonResponse
    {
        $settings = AppSetting::where('is_active', true)
            ->select('key', 'value', 'type', 'description')
            ->get()
            ->keyBy('key')
            ->map(function ($setting) {
                return [
                    'value' => $setting->parsed_value,
                    'description' => $setting->description
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'App settings retrieved successfully',
            'data' => $settings
        ]);
    }

    /**
     * Get all settings for admin management
     *
     * @return JsonResponse
     */
    public function getAllSettings(): JsonResponse
    {
        $settings = AppSetting::orderBy('key')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'All settings retrieved successfully',
            'data' => $settings
        ]);
    }

    /**
     * Create or update a setting
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function saveSetting(Request $request): JsonResponse
    {
        $request->validate([
            'key' => 'required|string|max:255',
            'value' => 'nullable',
            'type' => 'nullable|in:string,integer,boolean,json',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        try {
            $setting = AppSetting::firstOrNew(['key' => $request->key]);
            
            // Set value with type detection or explicit type
            $setting->setValue($request->value, $request->type);
            
            if ($request->has('description')) {
                $setting->description = $request->description;
            }
            
            $setting->is_active = $request->get('is_active', true);
            $setting->save();

            return response()->json([
                'success' => true,
                'message' => 'Setting saved successfully',
                'data' => [
                    'id' => $setting->id,
                    'key' => $setting->key,
                    'value' => $setting->parsed_value,
                    'type' => $setting->type,
                    'description' => $setting->description,
                    'is_active' => $setting->is_active
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save setting: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update multiple settings at once
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function saveMultipleSettings(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string|max:255',
            'settings.*.value' => 'nullable',
            'settings.*.type' => 'nullable|in:string,integer,boolean,json',
            'settings.*.description' => 'nullable|string',
            'settings.*.is_active' => 'boolean'
        ]);

        $savedSettings = [];
        $errors = [];

        foreach ($request->settings as $settingData) {
            try {
                $setting = AppSetting::firstOrNew(['key' => $settingData['key']]);
                
                $setting->setValue($settingData['value'], $settingData['type'] ?? null);
                
                if (isset($settingData['description'])) {
                    $setting->description = $settingData['description'];
                }
                
                $setting->is_active = $settingData['is_active'] ?? true;
                $setting->save();

                $savedSettings[] = [
                    'key' => $setting->key,
                    'value' => $setting->parsed_value,
                    'type' => $setting->type,
                    'description' => $setting->description,
                    'is_active' => $setting->is_active
                ];

            } catch (\Exception $e) {
                $errors[] = [
                    'key' => $settingData['key'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'success' => empty($errors),
            'message' => empty($errors) ? 'All settings saved successfully' : 'Some settings failed to save',
            'data' => [
                'saved' => $savedSettings,
                'errors' => $errors
            ]
        ]);
    }

    /**
     * Delete a setting
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteSetting(Request $request): JsonResponse
    {
        $request->validate([
            'key' => 'required|string|exists:app_settings,key'
        ]);

        $setting = AppSetting::where('key', $request->key)->first();
        
        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found'
            ], 404);
        }

        $setting->delete();

        return response()->json([
            'success' => true,
            'message' => 'Setting deleted successfully'
        ]);
    }

    /**
     * Toggle setting active status
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function toggleSetting(Request $request): JsonResponse
    {
        $request->validate([
            'key' => 'required|string|exists:app_settings,key'
        ]);

        $setting = AppSetting::where('key', $request->key)->first();
        
        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found'
            ], 404);
        }

        $setting->is_active = !$setting->is_active;
        $setting->save();

        $status = $setting->is_active ? 'activated' : 'deactivated';

        return response()->json([
            'success' => true,
            'message' => "Setting has been {$status} successfully",
            'data' => [
                'key' => $setting->key,
                'is_active' => $setting->is_active
            ]
        ]);
    }
}
