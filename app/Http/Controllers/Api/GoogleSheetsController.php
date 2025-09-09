<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SheetData;
use App\Models\MonthlyBtSheetData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GoogleSheetsController extends Controller
{
    private $spreadsheetId = '1lX-hxxnWREIiNpsgkPm4VJs7zW68dJAQeoJUwZxSs7w';
    private $apiKey;

    public function __construct()
    {
        $this->apiKey = "AIzaSyC4S469fLRYhr3vBA2D_MgBtQ7LxZl-R8o";
    }

    /**
     * Find column index by possible header names
     */
    private function findColumnIndex($headers, $possibleNames)
    {
        foreach ($headers as $index => $header) {
            foreach ($possibleNames as $name) {
                if (stripos($header, $name) !== false) {
                    return $index;
                }
            }
        }
        return null;
    }

    /**
     * Parse date from various formats
     */
    private function parseDate($dateString)
    {
        try {
            return Carbon::parse($dateString);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Webhook endpoint to receive data from Google Sheets
     */
    public function webhookReceiveData(Request $request)
    {
        try {
            // Log the incoming webhook data for debugging
            Log::info('Google Sheets Webhook Data:', $request->all());

            $validator = Validator::make($request->all(), [
                'sheet_name' => 'required|string|max:100',
                'data' => 'required|array',
                'data.*.date' => 'required|string',
                'data.*.cus_no' => 'required|string',
                'data.*.actual_bt_tide' => 'nullable|numeric'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $sheetName = $request->input('sheet_name');
            $data = $request->input('data');
            
            $syncedCount = 0;
            $errorCount = 0;

            foreach ($data as $row) {
                try {
                    // Parse date
                    $parsedDate = $this->parseDate($row['date']);
                    if (!$parsedDate) {
                        $errorCount++;
                        continue;
                    }

                    // Handle different sheet types
                    if (stripos($sheetName, 'bank transfer') !== false || stripos($sheetName, 'bt') !== false) {
                        // Handle Bank Transfer sheet data
                        $this->processBankTransferWebhookData($row, $parsedDate);
                    } else {
                        // Handle regular sheet data
                        $this->processRegularWebhookData($row, $parsedDate, $sheetName);
                    }
                    
                    $syncedCount++;
                } catch (\Exception $e) {
                    Log::error('Error processing webhook row:', ['error' => $e->getMessage(), 'row' => $row]);
                    $errorCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Webhook data processed successfully',
                'data' => [
                    'sheet_name' => $sheetName,
                    'synced_records' => $syncedCount,
                    'error_records' => $errorCount
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Webhook processing error:', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error processing webhook data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process regular sheet data from webhook
     */
    private function processRegularWebhookData($row, $parsedDate, $sheetName)
    {
        $cusNo = $row['cus_no'];
        $actualBtTide = $row['actual_bt_tide'] ?? null;

        // Check if record already exists
        $existingRecord = SheetData::where('date', $parsedDate->format('Y-m-d'))
            ->where('cus_no', $cusNo)
            ->where('sheet_name', $sheetName)
            ->first();

        if ($existingRecord) {
            // Update existing record
            $existingRecord->update([
                'actual_bt_tide' => $actualBtTide
            ]);
        } else {
            // Create new record
            SheetData::create([
                'date' => $parsedDate->format('Y-m-d'),
                'cus_no' => $cusNo,
                'actual_bt_tide' => $actualBtTide,
                'sheet_name' => $sheetName
            ]);
        }
    }

    /**
     * Process Bank Transfer sheet data from webhook
     */
    private function processBankTransferWebhookData($row, $parsedDate)
    {
        $year = $parsedDate->year;
        $month = $parsedDate->month;
        
        // For Bank Transfer sheet, we expect different fields
        $cusName = $row['cus_name'] ?? '';
        $mobileNo = $row['mobile_no'] ?? $row['cus_no'] ?? '';
        $totalBt = $row['total_bank_transfer'] ?? $row['actual_bt_tide'] ?? 0;

        if (empty($mobileNo)) {
            throw new \Exception('Mobile number is required for Bank Transfer data');
        }

        // Check if record already exists
        $existingRecord = MonthlyBtSheetData::where('mobile_no', $mobileNo)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($existingRecord) {
            // Update existing record
            $existingRecord->update([
                'cus_name' => $cusName,
                'total_bank_transfer' => $totalBt,
                'sheet_name' => 'Bank Transfer'
            ]);
        } else {
            // Create new record
            MonthlyBtSheetData::create([
                'cus_name' => $cusName,
                'mobile_no' => $mobileNo,
                'total_bank_transfer' => $totalBt,
                'year' => $year,
                'month' => $month,
                'sheet_name' => 'Bank Transfer'
            ]);
        }
    }

    /**
     * Sync data from Google Sheet to database for specified date
     */
    public function syncTodayData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sheet_name' => 'required|string|max:100',
            'date' => 'nullable|date_format:Y-m-d' // Optional date parameter
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!$this->apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Google Sheets API key not configured'
            ], 500);
        }

        $sheetName = $request->input('sheet_name');
        
        // Use provided date or default to yesterday
        $targetDate = $request->input('date') 
            ? Carbon::createFromFormat('Y-m-d', $request->input('date'))
            : Carbon::today()->subDay();
        
        $targetDateString = $targetDate->format('d/m/Y'); // Format for sheet comparison

        try {
            // Fetch all data from the specified sheet
            $url = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/{$sheetName}?key={$this->apiKey}";

            $response = Http::get($url);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch data from Google Sheets',
                    'error' => $response->body()
                ], 500);
            }

            $data = $response->json();

            if (!isset($data['values']) || empty($data['values'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data found in the specified sheet'
                ], 404);
            }

            $rows = $data['values'];
            $headers = array_shift($rows); // First row as headers

            if (empty($headers)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No headers found in the sheet'
                ], 400);
            }

            // Find column indexes
            $dateColumnIndex = $this->findColumnIndex($headers, ['date', 'Date', 'DATE', 'created_at', 'Created_At', 'timestamp', 'Timestamp']);
            $cusNoColumnIndex = $this->findColumnIndex($headers, ['Cus No', 'customer_no', 'Customer_No', 'mobile', 'Mobile', 'phone', 'Phone', 'contact', 'Contact']);
            $actualBtTideColumnIndex = $this->findColumnIndex($headers, ['actual_bt_tide', 'ACTUAL_BT_TIDE', 'Actual_BT_Tide', 'bt_tide', 'BT_TIDE', 'Bt_Tide']);

            if ($dateColumnIndex === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Date column not found. Looking for: date, created_at, timestamp columns'
                ], 400);
            }

            if ($cusNoColumnIndex === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer number column not found. Looking for: cus_no, mobile, phone, contact columns'
                ], 400);
            }

            if ($actualBtTideColumnIndex === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'ACTUAL BT_TIDE column not found. Looking for: actual_bt_tide, bt_tide columns'
                ], 400);
            }

            $syncedCount = 0;

            foreach ($rows as $row) {
                // Skip empty rows
                if (empty($row) || count($row) <= max($dateColumnIndex, $cusNoColumnIndex, $actualBtTideColumnIndex)) {
                    continue;
                }

                $dateValue = $row[$dateColumnIndex] ?? '';
                $cusNo = $row[$cusNoColumnIndex] ?? '';
                $actualBtTide = $row[$actualBtTideColumnIndex] ?? '';

                // Skip if essential data is missing
                if (empty($dateValue) || empty($cusNo)) {
                    continue;
                }

                // Parse and validate date
                try {
                    $parsedDate = $this->parseDate($dateValue);
                    
                    // Compare with target date - only process records matching the target date
                    if (!$parsedDate || $dateValue !== $targetDateString) {
                        continue;
                    }
                } catch (\Exception $e) {
                    continue;
                }

                // Clean and validate actual_bt_tide
                $actualBtTideValue = null;
                if (!empty($actualBtTide)) {
                    $cleanedValue = preg_replace('/[^\d.-]/', '', $actualBtTide);
                    if (is_numeric($cleanedValue)) {
                        $actualBtTideValue = (float) $cleanedValue;
                    }
                }

                // Check if record already exists
                $existingRecord = SheetData::where('date', $parsedDate->format('Y-m-d'))
                    ->where('cus_no', $cusNo)
                    ->where('sheet_name', $sheetName)
                    ->first();

                if ($existingRecord) {
                    // Update existing record
                    $existingRecord->update([
                        'actual_bt_tide' => $actualBtTideValue
                    ]);
                    $syncedCount++;
                } else {
                    // Create new record
                    SheetData::create([
                        'date' => $parsedDate->format('Y-m-d'),
                        'cus_no' => $cusNo,
                        'actual_bt_tide' => $actualBtTideValue,
                        'sheet_name' => $sheetName
                    ]);
                    $syncedCount++;
                }
            }

            // Sync bank transfer data
            $bankTransferResult = $this->syncBankTransferSheet($targetDate);
            
            return response()->json([
                'success' => true,
                'message' => "Data synced successfully for {$targetDate->format('Y-m-d')}",
                'data' => [
                    'sheet_name' => $sheetName,
                    'synced_records' => $syncedCount,
                    'bank_transfer_synced' => $bankTransferResult['synced_records'] ?? 0
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error syncing data from Google Sheets',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync Bank Transfer sheet data
     */
    private function syncBankTransferSheet($targetDate)
    {
        try {
            $sheetName = ' Bank Transfer';
            $year = $targetDate->year;
            $month = $targetDate->month;

            // Fetch all data from the Bank Transfer sheet
            // For sheet names with spaces, we need to URL encode them properly
            $encodedSheetName = urlencode($sheetName);
            $url = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/{$encodedSheetName}?key={$this->apiKey}";

            $response = Http::get($url);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Failed to fetch Bank Transfer sheet data',
                    'error' => $response->body()
                ];
            }

            $data = $response->json();

            if (!isset($data['values']) || empty($data['values'])) {
                return [
                    'success' => false,
                    'message' => 'No data found in Bank Transfer sheet'
                ];
            }

            $rows = $data['values'];
            $headers = array_shift($rows); // First row as headers

            if (empty($headers)) {
                return [
                    'success' => false,
                    'message' => 'No headers found in Bank Transfer sheet'
                ];
            }

            // Find column indexes for Bank Transfer sheet
            $cusNameColumnIndex = $this->findColumnIndex($headers, ['cus_name', 'Cus Name', 'Customer_Name', 'customer_name', 'name', 'Name']);
            $mobileNoColumnIndex = $this->findColumnIndex($headers, ['mobile_no', 'Mobile No.', 'mobile', 'Mobile', 'phone', 'Phone', 'contact', 'Contact']);
            $totalBtColumnIndex = $this->findColumnIndex($headers, ['total_bank_transfer', 'Total Bank Transfer', 'total_bt', 'Total_BT', 'amount', 'Amount']);

            if ($cusNameColumnIndex === null) {
                return [
                    'success' => false,
                    'message' => 'Customer name column not found in Bank Transfer sheet'
                ];
            }

            if ($mobileNoColumnIndex === null) {
                return [
                    'success' => false,
                    'message' => 'Mobile number column not found in Bank Transfer sheet'
                ];
            }

            if ($totalBtColumnIndex === null) {
                return [
                    'success' => false,
                    'message' => 'Total Bank Transfer column not found in Bank Transfer sheet'
                ];
            }

            $syncedBtCount = 0;

            foreach ($rows as $row) {
                // Skip empty rows
                if (empty($row) || count($row) <= max($cusNameColumnIndex, $mobileNoColumnIndex, $totalBtColumnIndex)) {
                    continue;
                }

                $cusName = $row[$cusNameColumnIndex] ?? '';
                $mobileNo = $row[$mobileNoColumnIndex] ?? '';
                $totalBt = $row[$totalBtColumnIndex] ?? '';

                // Skip if essential data is missing
                if (empty($cusName) || empty($mobileNo)) {
                    continue;
                }

                // Clean and validate total bank transfer amount
                $totalBtValue = 0;
                if (!empty($totalBt)) {
                    $cleanedValue = preg_replace('/[^\d.-]/', '', $totalBt);
                    if (is_numeric($cleanedValue)) {
                        $totalBtValue = (float) $cleanedValue;
                    }
                }

                // Create or update record for the current month
                $existingRecord = MonthlyBtSheetData::where('mobile_no', $mobileNo)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->first();

                if ($existingRecord) {
                    // Update existing record
                    $existingRecord->update([
                        'cus_name' => $cusName,
                        'total_bank_transfer' => $totalBtValue,
                        'sheet_name' => $sheetName
                    ]);
                    $syncedBtCount++;
                } else {
                    // Create new record
                    MonthlyBtSheetData::create([
                        'cus_name' => $cusName,
                        'mobile_no' => $mobileNo,
                        'total_bank_transfer' => $totalBtValue,
                        'year' => $year,
                        'month' => $month,
                        'sheet_name' => $sheetName
                    ]);
                    $syncedBtCount++;
                }
            }

            return [
                'success' => true,
                'message' => 'Bank Transfer data synced successfully',
                'synced_records' => $syncedBtCount
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error syncing Bank Transfer sheet data',
                'error' => $e->getMessage()
            ];
        }
    }
}
