# Google Sheets Integration Documentation

## Overview
This module allows the AXN Group API to fetch data from Google Sheets by phone number and date filters. It integrates with the Google Sheets API to search and retrieve specific records.

## Setup

### 1. Google Sheets API Key
To use this feature, you need a Google Sheets API key:

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Enable the Google Sheets API
4. Create credentials (API Key)
5. Add the API key to your `.env` file:
   ```
   GOOGLE_SHEETS_API_KEY=your_api_key_here
   ```

### 2. Spreadsheet Configuration
- **Spreadsheet ID**: `1lX-hxxnWREIiNpsgkPm4VJs7zW68dJAQeoJUwZxSs7w`
- **Access**: The spreadsheet must be publicly readable or shared with the service account

## API Endpoints

### 1. Search by Phone Number and Date
```
GET /api/google-sheets/search
```

**Parameters:**
- `phone_number` (required): Phone number to search for
- `date` (optional): Date filter in YYYY-MM-DD format
- `sheet_name` (optional): Specific sheet name (default: "Sheet1")

**Example Request:**
```
GET /api/google-sheets/search?phone_number=9876543210&date=2024-01-15&sheet_name=Data
```

**Example Response:**
```json
{
    "success": true,
    "message": "Data fetched successfully",
    "data": {
        "headers": ["Name", "Phone", "Date", "Amount"],
        "rows": [
            {
                "Name": "John Doe",
                "Phone": "9876543210",
                "Date": "2024-01-15",
                "Amount": "5000"
            }
        ],
        "total_records": 1
    },
    "filters": {
        "phone_number": "9876543210",
        "date": "2024-01-15",
        "sheet_name": "Data"
    }
}
```

### 2. Get Available Sheet Names
```
GET /api/google-sheets/sheet-names
```

**Example Response:**
```json
{
    "success": true,
    "message": "Sheet names fetched successfully",
    "data": [
        {
            "id": 0,
            "title": "Sheet1",
            "index": 0
        },
        {
            "id": 123456,
            "title": "Data",
            "index": 1
        }
    ]
}
```

### 3. Get Spreadsheet Information
```
GET /api/google-sheets/info
```

**Example Response:**
```json
{
    "success": true,
    "message": "Spreadsheet information fetched successfully",
    "data": {
        "spreadsheet_id": "1lX-hxxnWREIiNpsgkPm4VJs7zW68dJAQeoJUwZxSs7w",
        "title": "AXN Group Data",
        "locale": "en_US",
        "sheets_count": 2,
        "sheets": [
            {
                "title": "Sheet1",
                "id": 0,
                "index": 0
            }
        ]
    }
}
```

## Features

### Smart Column Detection
The controller automatically detects column types based on header names:
- **Phone columns**: Looks for headers containing "phone", "mobile", "contact", "number"
- **Date columns**: Looks for headers containing "date", "created", "timestamp", "time"

### Phone Number Matching
- Removes all non-numeric characters for comparison
- Supports partial matching (useful for different phone formats)
- Handles various phone number formats (with/without country codes)

### Date Parsing
- Supports multiple date formats
- Converts dates to YYYY-MM-DD format for comparison
- Handles various date representations

### Access Control
- **Admin**: Full access to all endpoints
- **Leader**: Full access to all endpoints
- **Agent**: No access (only admins and leaders can search external data)

## Error Handling

### Common Error Responses:

**API Key Not Configured:**
```json
{
    "success": false,
    "message": "Google Sheets API key not configured"
}
```

**Validation Error:**
```json
{
    "success": false,
    "message": "Validation error",
    "errors": {
        "phone_number": ["The phone number field is required."]
    }
}
```

**No Data Found:**
```json
{
    "success": false,
    "message": "No data found in the spreadsheet"
}
```

**Google Sheets API Error:**
```json
{
    "success": false,
    "message": "Failed to fetch data from Google Sheets",
    "error": "API error details"
}
```

## Usage Examples

### Search for specific phone number:
```bash
curl -X GET "http://localhost:8000/api/google-sheets/search?phone_number=9876543210" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Search with date filter:
```bash
curl -X GET "http://localhost:8000/api/google-sheets/search?phone_number=9876543210&date=2024-01-15" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Search in specific sheet:
```bash
curl -X GET "http://localhost:8000/api/google-sheets/search?phone_number=9876543210&sheet_name=Transactions" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Security Considerations

1. **API Key Security**: Store the Google Sheets API key securely in environment variables
2. **Access Control**: Only admins and leaders can access this functionality
3. **Rate Limiting**: Consider implementing rate limiting for Google Sheets API calls
4. **Data Privacy**: Ensure the spreadsheet doesn't contain sensitive personal information

## Troubleshooting

### Common Issues:

1. **403 Forbidden**: Check if the spreadsheet is publicly accessible or shared properly
2. **Invalid API Key**: Verify the Google Sheets API key is correct and has proper permissions
3. **Sheet Not Found**: Ensure the sheet name exists in the spreadsheet
4. **No Results**: Check if the phone number format matches the data in the sheet

### Debugging Tips:

1. Use the `/info` endpoint to verify spreadsheet access
2. Use the `/sheet-names` endpoint to check available sheets
3. Check Laravel logs for detailed error messages
4. Verify the Google Sheets API is enabled in Google Cloud Console
