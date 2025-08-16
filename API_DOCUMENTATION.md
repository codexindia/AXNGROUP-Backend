# Agent-Leader-Admin System API Documentation

## Overview
This is a Laravel-based API system for managing agents, leaders, and their transactions. The system is organized into modular controllers for better maintainability.

## Modules

### 1. Authentication Module (`/api/auth`)

#### Login Leader
- **POST** `/api/auth/login/leader`
- **Body**: 
  ```json
  {
    "mobile": "9876543210",
    "password": "password123"
  }
  ```

#### Login Agent
- **POST** `/api/auth/login/agent`
- **Body**: 
  ```json
  {
    "mobile": "9876543210",
    "password": "password123"
  }
  ```

#### Register Agent (Leader only)
- **POST** `/api/auth/register/agent`
- **Headers**: `Authorization: Bearer {token}`
- **Body**: 
  ```json
  {
    "name": "Agent Name",
    "mobile": "9876543210",
    "email": "agent@example.com",
    "password": "password123",
    "referral_code": "REF123"
  }
  ```

#### Get Profile
- **GET** `/api/auth/profile`
- **Headers**: `Authorization: Bearer {token}`

#### Logout
- **POST** `/api/auth/logout`
- **Headers**: `Authorization: Bearer {token}`

### 2. Wallet Module (`/api/wallet`)

#### Get Balance
- **GET** `/api/wallet/balance`
- **Headers**: `Authorization: Bearer {token}`

#### Get Transactions
- **GET** `/api/wallet/transactions`
- **Headers**: `Authorization: Bearer {token}`

#### Request Withdrawal
- **POST** `/api/wallet/withdraw`
- **Headers**: `Authorization: Bearer {token}`
- **Body**: 
  ```json
  {
    "amount": 1000.50
  }
  ```

#### Get Withdrawals
- **GET** `/api/wallet/withdrawals`
- **Headers**: `Authorization: Bearer {token}`

#### Credit Wallet (Leader/Admin only)
- **POST** `/api/wallet/credit`
- **Headers**: `Authorization: Bearer {token}`
- **Body**: 
  ```json
  {
    "user_id": 1,
    "amount": 500.00,
    "reference_type": "bank_transfer",
    "reference_id": 123,
    "remark": "Commission credit"
  }
  ```

### 3. Shop Module (`/api/shops`) - Onboarding

#### Create Shop Onboarding (Agent only)
- **POST** `/api/shops`
- **Headers**: `Authorization: Bearer {token}`
- **Body**: 
  ```json
  {
    "customer_name": "Customer Name",
    "customer_mobile": "9876543210",
    "team_leader_id": 2
  }
  ```

#### Get Agent's Shops
- **GET** `/api/shops/agent`
- **Headers**: `Authorization: Bearer {token}`

#### Get Leader's Assigned Shops
- **GET** `/api/shops/leader`
- **Headers**: `Authorization: Bearer {token}`

#### Update Shop Status (Leader only)
- **PUT** `/api/shops/{id}/status`
- **Headers**: `Authorization: Bearer {token}`
- **Body**: 
  ```json
  {
    "status": "approved", // or "rejected"
    "reject_remark": "Reason for rejection"
  }
  ```

#### Get Shop Details
- **GET** `/api/shops/{id}`
- **Headers**: `Authorization: Bearer {token}`

### 4. Bank Transfer Module (`/api/bank-transfers`)

#### Create Bank Transfer (Agent only)
- **POST** `/api/bank-transfers`
- **Headers**: `Authorization: Bearer {token}`
- **Body**: 
  ```json
  {
    "customer_name": "Customer Name",
    "customer_mobile": "9876543210",
    "amount": 5000.00,
    "team_leader_id": 2
  }
  ```

#### Get Agent's Bank Transfers
- **GET** `/api/bank-transfers/agent`
- **Headers**: `Authorization: Bearer {token}`

#### Get Leader's Assigned Bank Transfers
- **GET** `/api/bank-transfers/leader`
- **Headers**: `Authorization: Bearer {token}`

#### Update Bank Transfer Status (Leader only)
- **PUT** `/api/bank-transfers/{id}/status`
- **Headers**: `Authorization: Bearer {token}`
- **Body**: 
  ```json
  {
    "status": "approved", // or "rejected"
    "amount_change_remark": "Amount adjusted",
    "reject_remark": "Reason for rejection"
  }
  ```

#### Get Bank Transfer Details
- **GET** `/api/bank-transfers/{id}`
- **Headers**: `Authorization: Bearer {token}`

### 5. Profile Module (`/api/profile`)

#### Get Profile
- **GET** `/api/profile`
- **Headers**: `Authorization: Bearer {token}`

#### Update Profile
- **POST** `/api/profile/update`
- **Headers**: `Authorization: Bearer {token}`, `Content-Type: multipart/form-data`
- **Body**: 
  ```
  agent_photo: [file]
  aadhar_number: 123456789012
  pan_number: ABCDE1234F
  address: Complete address
  dob: 1990-01-01
  ```

#### Add Bank Details
- **POST** `/api/profile/bank-details`
- **Headers**: `Authorization: Bearer {token}`
- **Body**: 
  ```json
  {
    "account_holder_name": "Account Holder",
    "bank_name": "Bank Name",
    "account_number": "1234567890",
    "confirm_account_number": "1234567890",
    "ifsc_code": "BANK0001234"
  }
  ```

#### Get Bank Details
- **GET** `/api/profile/bank-details`
- **Headers**: `Authorization: Bearer {token}`

#### Update Bank Details
- **PUT** `/api/profile/bank-details/{id}`
- **Headers**: `Authorization: Bearer {token}`
- **Body**: Same as Add Bank Details

#### Delete Bank Details
- **DELETE** `/api/profile/bank-details/{id}`
- **Headers**: `Authorization: Bearer {token}`

## Response Format

All API responses follow this structure:

### Success Response
```json
{
  "success": true,
  "message": "Success message",
  "data": {}
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error message",
  "errors": {} // Validation errors when applicable
}
```

## Authentication

The API uses Laravel Sanctum for authentication. After login, include the Bearer token in the Authorization header for protected routes:

```
Authorization: Bearer {your-token-here}
```

## User Roles

- **agent**: Can create shop onboarding and bank transfer requests
- **leader**: Can approve/reject requests from agents, register new agents
- **admin**: Has access to all system features
- **office_staff**: Limited access as defined

## Database Features

- All monetary amounts use decimal(12,2) precision
- Soft deletes implemented for users, shops, and bank_transfers
- Foreign key constraints with cascade on delete
- Comprehensive indexing for optimal performance
- Wallet balance is managed through transactions only

## File Storage

Profile photos are stored in `storage/app/public/agent_photos/` and accessible via `/storage/agent_photos/` URL.