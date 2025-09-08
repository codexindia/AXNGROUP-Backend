# Google Sheets Sync API - Testing Guide

## Quick Test Example

### 1. Test with Postman or any HTTP client

**Endpoint**: `POST /api/google-sheets/sync-today`

**Headers**:
```
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json
```

**Body**:
```json
{
    "sheet_name": "Sheet1"
}
```

### 2. Expected Response
```json
{
    "success": true,
    "message": "Today's data synced successfully",
    "data": {
        "sync_date": "2025-09-08",
        "sheet_name": "Sheet1",
        "synced_records": 5,
        "skipped_records": 2,
        "total_today_records": 5,
        "records": [...]
    }
}
```

### 3. Database Verification
After successful sync, check your database:
```sql
SELECT * FROM sheet_data WHERE date = CURDATE();
```

### 4. Sheet Requirements
Your Google Sheet should have columns like:
- `Date` or `date` (with today's date)
- `Cus_No` or `mobile` or `phone` (customer mobile number)
- `ACTUAL_BT_TIDE` or `bt_tide` (amount value)

### 5. Sample Sheet Format
```
| Date       | Cus_No     | ACTUAL_BT_TIDE |
|------------|------------|----------------|
| 2025-09-08 | 9876543210 | 1500.50        |
| 2025-09-08 | 9876543211 | 2000.00        |
| 2025-09-07 | 9876543212 | 1000.00        | (will be skipped - not today)
```

## Troubleshooting

### If you get "Column not found" error:
1. Check your sheet column names
2. Ensure they match the expected patterns in the documentation

### If you get "No data found":
1. Ensure your sheet has data for today's date
2. Check date format in your sheet

### If you get API errors:
1. Verify your Google Sheets API key in .env
2. Ensure the spreadsheet ID is correct in the controller
3. Check if the sheet name exists in your spreadsheet
