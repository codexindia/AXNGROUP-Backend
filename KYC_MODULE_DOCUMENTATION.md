# KYC Module Documentation

## Overview
The KYC (Know Your Customer) module allows team leaders and agents to submit their verification documents for admin approval. This module ensures compliance and identity verification for all users in the MLM system.

## Features
- Document submission with photo uploads
- Status tracking (pending, approved, rejected)
- Admin review and approval system
- Comprehensive validation
- File storage management
- Search and filtering capabilities

## Mandatory Fields

### 1. Aadhaar Card Details
- **Aadhaar Number**: 12-digit unique identification number
- **Aadhaar Photo**: Clear photo of Aadhaar card (JPEG/PNG, max 2MB)

### 2. PAN Card Details
- **PAN Number**: 10-character alphanumeric PAN number (Format: ABCDE1234F)
- **PAN Photo**: Clear photo of PAN card (JPEG/PNG, max 2MB)

### 3. Bank Details
- **Bank Account Number**: Bank account number (9-20 digits)
- **IFSC Code**: Bank IFSC code (11 characters)
- **Bank Name**: Name of the bank
- **Account Holder Name**: Name as per bank records
- **Passbook Photo**: Photo of bank passbook first page (JPEG/PNG, max 2MB)

### 4. Profile Details
- **Profile Photo**: Clear photo of the user (JPEG/PNG, max 2MB)
- **Working City**: Current city of operation

## Database Schema

### KYC Verifications Table Structure
```sql
- id (Primary Key)
- user_id (Foreign Key to users table)
- aadhar_number (VARCHAR 12)
- aadhar_photo (VARCHAR 255)
- pan_number (VARCHAR 10)
- pan_photo (VARCHAR 255)
- bank_account_number (VARCHAR 255)
- bank_ifsc_code (VARCHAR 11)
- bank_name (VARCHAR 255)
- account_holder_name (VARCHAR 255)
- passbook_photo (VARCHAR 255)
- profile_photo (VARCHAR 255)
- working_city (VARCHAR 255)
- kyc_status (ENUM: pending, approved, rejected)
- remark (TEXT)
- submitted_at (TIMESTAMP)
- approved_at (TIMESTAMP)
- approved_by (Foreign Key to users table)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)
```

## API Endpoints

### Agent/Leader Endpoints

#### 1. Submit KYC Documents
- **URL**: `POST /api/kyc/submit`
- **Authorization**: Bearer Token (Agent/Leader only)
- **Content-Type**: `multipart/form-data`
- **Parameters**: All mandatory fields listed above
- **Response**: KYC submission details with status

#### 2. Get My KYC Status
- **URL**: `GET /api/kyc/my-kyc`
- **Authorization**: Bearer Token (Agent/Leader only)
- **Response**: Current user's KYC status and details

### Admin Endpoints

#### 3. Get All KYC Submissions
- **URL**: `GET /api/kyc/all`
- **Authorization**: Bearer Token (Admin only)
- **Query Parameters**:
  - `status`: Filter by status (pending, approved, rejected)
  - `role`: Filter by role (agent, leader)
  - `date_from`: Filter from date
  - `date_to`: Filter to date
  - `search`: Search by name, mobile, Aadhaar, PAN, etc.
  - `per_page`: Items per page (1-100)

#### 4. Get Pending KYCs
- **URL**: `GET /api/kyc/pending`
- **Authorization**: Bearer Token (Admin only)
- **Response**: All pending KYC submissions

#### 5. Get KYC Details
- **URL**: `GET /api/kyc/{id}`
- **Authorization**: Bearer Token (Admin only)
- **Response**: Detailed KYC information with all documents

#### 6. Review KYC (Approve/Reject)
- **URL**: `PUT /api/kyc/{id}/review`
- **Authorization**: Bearer Token (Admin only)
- **Parameters**:
  - `status`: "approved" or "rejected"
  - `remark`: Optional remark for the decision

## Validation Rules

### Aadhaar Number
- Required, exactly 12 digits
- Regex: `/^[0-9]{12}$/`

### PAN Number
- Required, exactly 10 characters
- Format: 5 letters + 4 digits + 1 letter
- Regex: `/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/`

### IFSC Code
- Required, exactly 11 characters
- Format: 4 letters + 0 + 6 alphanumeric
- Regex: `/^[A-Z]{4}0[A-Z0-9]{6}$/`

### Photos
- Required for all document photos
- Allowed formats: JPEG, PNG, JPG
- Maximum size: 2MB each

### Bank Account Number
- Required, 9-20 characters
- Only numeric characters allowed

## File Storage

### Directory Structure
```
storage/app/public/
├── kyc/
│   ├── aadhar/          # Aadhaar card photos
│   ├── pan/             # PAN card photos
│   ├── passbook/        # Bank passbook photos
│   └── profile/         # Profile photos
```

### File Naming Convention
- Files are stored with Laravel's default hash naming
- Original extensions are preserved
- Files are accessible via public URL: `{app_url}/storage/{file_path}`

## KYC Status Flow

### 1. Draft/Not Submitted
- User has not submitted KYC documents yet

### 2. Pending
- User has submitted all required documents
- Waiting for admin review
- `submitted_at` timestamp is set

### 3. Approved
- Admin has verified and approved all documents
- `approved_at` timestamp and `approved_by` are set
- User can now perform all system operations

### 4. Rejected
- Admin found issues with the documents
- Rejection reason provided in `remark` field
- User can resubmit corrected documents

## Security Features

### 1. Role-Based Access Control
- Only agents and leaders can submit KYC
- Only admins can review and approve KYC
- Proper authentication required for all endpoints

### 2. File Validation
- Strict file type checking
- File size limitations
- Secure file storage

### 3. Data Validation
- Government ID format validation
- Bank details format checking
- Comprehensive input sanitization

## Usage Workflow

### For Agents/Leaders:
1. Gather all required documents
2. Take clear photos of all documents
3. Submit KYC via API with all mandatory fields
4. Check status using "Get My KYC" endpoint
5. If rejected, review remarks and resubmit

### For Admins:
1. View pending KYCs using admin endpoints
2. Review each KYC submission in detail
3. Verify all documents and information
4. Approve or reject with appropriate remarks
5. Monitor KYC statistics and compliance

## Error Handling

### Common Error Codes:
- **422**: Validation errors (missing/invalid data)
- **403**: Unauthorized access (wrong role)
- **400**: Business logic errors (already submitted, etc.)
- **404**: KYC record not found
- **500**: Server errors (file upload issues, etc.)

### File Upload Errors:
- File too large (>2MB)
- Invalid file format
- File corruption
- Storage permission issues

## Statistics and Reporting

### KYC Statistics Include:
- Total KYC submissions
- Pending reviews
- Approved count
- Rejected count
- Agent vs Leader submissions
- Daily submission counts

## Best Practices

### For Development:
1. Always validate file uploads before processing
2. Use database transactions for KYC operations
3. Implement proper error handling
4. Clean up files on failed operations
5. Use proper indexing for search operations

### For Testing:
1. Test with various file formats and sizes
2. Validate all form field combinations
3. Test role-based access control
4. Verify file storage and cleanup
5. Test search and filtering functions

## Migration Commands

```bash
# Run the KYC migration
php artisan migrate

# Create storage link for file access
php artisan storage:link
```

## Model Relationships

### User Model
- `hasOne(KycVerification::class)` - User's KYC record

### KycVerification Model
- `belongsTo(User::class)` - The user who submitted KYC
- `belongsTo(User::class, 'approved_by')` - Admin who approved/rejected

## File Access URLs

Photos can be accessed via:
```
GET {app_url}/storage/kyc/aadhar/{filename}
GET {app_url}/storage/kyc/pan/{filename}
GET {app_url}/storage/kyc/passbook/{filename}
GET {app_url}/storage/kyc/profile/{filename}
```

## Postman Collection

The KYC endpoints are included in the main AXN Group API Collection under the "KYC" folder with:
- Sample form data for document submission
- All CRUD operations for admin management
- Response examples for success and error scenarios
- Proper authentication setup for different roles
