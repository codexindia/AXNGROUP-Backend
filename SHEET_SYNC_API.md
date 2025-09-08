# Google Sheets Sync API Documentation

## Overview
This API endpoint allows you to automatically sync today's data from a Google Sheet to your local database. It will fetch all records from the specified sheet that match today's date and store them in the `sheet_data` table.

## Endpoint
**POST** `/api/google-sheets/sync-today`

## Authentication
- Requires authentication via Sanctum token
- Only **Admin** and **Leader** roles have access

## Request Parameters

### Required Parameters
- `sheet_name` (string, max:100) - Name of the sheet to sync data from

### Example Request
```json
{
    "sheet_name": "Master Sheet"
}
```

## Response Format

### Success Response (200)
```json
{
    "success": true,
    "message": "Today's data synced successfully",
    "data": {
        "sync_date": "07/09/2025",
        "sheet_name": "Master Sheet",
        "synced_records": 15,
        "skipped_records": 3,
        "total_today_records": 15,
        "records": [
            {
                "date": "2025-09-07",
                "cus_no": "9876543210",
                "actual_bt_tide": 1500.50
            },
            {
                "date": "2025-09-07",
                "cus_no": "9876543211",
                "actual_bt_tide": 2000.00
            }
        ]
    }
}
```

### Error Responses

#### Validation Error (422)
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "sheet_name": ["The sheet name field is required."]
    }
}
```

#### Google Sheets API Error (500)
```json
{
    "success": false,
    "message": "Failed to fetch data from Google Sheets",
    "error": "Error details from Google API"
}
```

#### Column Not Found Error (400)
```json
{
    "success": false,
    "message": "Date column not found. Looking for: date, created_at, timestamp columns"
}
```

## How It Works

### 1. Column Detection
The API automatically detects columns based on common naming patterns:

- **Date Column**: `date`, `Date`, `DATE`, `created_at`, `Created_At`, `timestamp`, `Timestamp`
- **Customer Number**: `Cus No`, `customer_no`, `Customer_No`, `mobile`, `Mobile`, `phone`, `Phone`, `contact`, `Contact`
- **Actual BT Tide**: `actual_bt_tide`, `ACTUAL_BT_TIDE`, `Actual_BT_Tide`, `bt_tide`, `BT_TIDE`, `Bt_Tide`

### 2. Data Processing
- Only processes records with yesterday's date (current day - 1)
- Skips empty rows or rows with missing essential data
- Cleans and validates numeric values for `actual_bt_tide`
- Updates existing records or creates new ones

### 3. Database Storage
Data is stored in the `sheet_data` table with the following structure:
```sql
CREATE TABLE sheet_data (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    cus_no VARCHAR(255) NOT NULL,
    actual_bt_tide DECIMAL(10,2) NULL,
    sheet_name VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_date_cus_no (date, cus_no),
    INDEX idx_date (date),
    INDEX idx_cus_no (cus_no)
);
```

## Usage Examples

### Curl Example
```bash
curl -X POST http://your-domain.com/api/google-sheets/sync-today \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"sheet_name": "Master Sheet"}'
```

### PHP Example
```php
$response = Http::withToken($token)
    ->post('http://your-domain.com/api/google-sheets/sync-today', [
        'sheet_name' => 'Master Sheet'
    ]);
```

## Configuration

### Google Sheets API Setup
The API key is currently hardcoded in the controller:
```php
$this->apiKey = "AIzaSyC4S469fLRYhr3vBA2D_MgBtQ7LxZl-R8o";
```

### Spreadsheet ID
The spreadsheet ID is configured in the controller:
```php
private $spreadsheetId = '1lX-hxxnWREIiNpsgkPm4VJs7zW68dJAQeoJUwZxSs7w';
```

## Features

✅ **Single Purpose** - Only sync functionality for clean, focused API
✅ **Automatic Column Detection** - Smart detection of date, customer number, and BT tide columns
✅ **Yesterday's Data** - Syncs previous day's data automatically
✅ **Duplicate Handling** - Updates existing records, creates new ones
✅ **Data Validation** - Validates and cleans numeric values
✅ **Error Handling** - Comprehensive error messages and validation
✅ **Performance Optimized** - Database indexes for fast queries
✅ **Role-based Access** - Admin and Leader access only

## Best Practices

1. **Daily Sync**: Run this endpoint once daily to sync previous day's data
2. **Error Monitoring**: Monitor API responses for any sync failures
3. **Data Validation**: Verify synced data periodically
4. **Performance**: Use during off-peak hours for large datasets

## Troubleshooting

### Common Issues
1. **Column Not Found**: Ensure your sheet has the required columns with standard naming
2. **Date Format**: Ensure dates are in a recognizable format (dd/mm/yyyy, yyyy-mm-dd, etc.)
3. **API Limits**: Google Sheets API has rate limits, consider implementing delays for large datasets
4. **Permissions**: Ensure your Google API key has access to the spreadsheet

### Support
For issues with this API, check:
1. Google Sheets API key configuration
2. Spreadsheet permissions
3. Column naming conventions
4. Date formats in your sheet
