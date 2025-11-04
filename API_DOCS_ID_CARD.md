# ID Card Management API Documentation

## Overview

Three new API endpoints for managing agent/leader ID cards with public verification via QR code.

---

## 🔐 1. Get Agent Details (Admin Only)

**Endpoint:** `GET /api/admin/agents/{agentId}/details`

**Authorization:** Bearer Token (Admin only)

**Response:**

```json
{
    "success": true,
    "data": {
        "id": 123,
        "unique_id": "VHN00002",
        "name": "Gopal Maurya",
        "mobile": "8587577778",
        "email": "gopal@example.com",
        "role": "agent",
        "designation": "FSE",
        "is_active": true,
        "is_blocked": false,
        "profile_photo_url": "https://storage.../photo.jpg",
        "kyc_status": "approved",
        "is_kyc_verified": true,
        "wallet_balance": "5000.00",
        "created_at": "2024-01-15T10:30:00.000000Z",
        "profile": {
            "aadhar_number": "123456789012",
            "pan_number": "ABCDE1234F",
            "address": "Madhupur colony near Dr LP Lal memorial school lucknow Uttar Pradesh India",
            "dob": "1990-05-15",
            "joining_date": "2024-01-15",
            "id_card_valid_until": "2025-12-30"
        },
        "kyc": {
            "working_city": "Lucknow",
            "kyc_status": "approved",
            "submitted_at": "2024-01-20 14:30:00",
            "approved_at": "2024-01-21 10:15:00"
        },
        "parent": {
            "id": 45,
            "name": "Team Leader Name",
            "unique_id": "LDR00001",
            "role": "leader"
        },
        "bank_details": [
            {
                "id": 1,
                "account_holder_name": "Gopal Maurya",
                "bank_name": "HDFC Bank",
                "account_number": "12345678901234",
                "ifsc_code": "HDFC0001234"
            }
        ]
    }
}
```

**Use Case:** Display complete agent information for ID card generation in admin panel.

---

## ✏️ 2. Update Agent ID Card Info (Admin Only)

**Endpoint:** `POST /api/admin/agents/{agentId}/update-id-card`

**Authorization:** Bearer Token (Admin only)

**Content-Type:** `multipart/form-data`

**Request Body (All fields optional):**

```
agent_photo: File (jpeg/png/jpg, max 2MB)
joining_date: Date (YYYY-MM-DD, must be <= today)
id_card_valid_until: Date (YYYY-MM-DD, must be future date)
```

**Examples:**

1. **Update only photo:**

```javascript
const formData = new FormData();
formData.append("agent_photo", fileInput.files[0]);

fetch("/api/admin/agents/123/update-id-card", {
    method: "POST",
    headers: {
        Authorization: "Bearer " + adminToken,
    },
    body: formData,
});
```

2. **Update only dates:**

```javascript
const formData = new FormData();
formData.append("joining_date", "2024-01-15");
formData.append("id_card_valid_until", "2025-12-30");

fetch("/api/admin/agents/123/update-id-card", {
    method: "POST",
    headers: {
        Authorization: "Bearer " + adminToken,
    },
    body: formData,
});
```

3. **Update all fields:**

```javascript
const formData = new FormData();
formData.append("agent_photo", fileInput.files[0]);
formData.append("joining_date", "2024-01-15");
formData.append("id_card_valid_until", "2025-12-30");

fetch("/api/admin/agents/123/update-id-card", {
    method: "POST",
    headers: {
        Authorization: "Bearer " + adminToken,
    },
    body: formData,
});
```

**Success Response:**

```json
{
    "success": true,
    "message": "Agent ID card information updated successfully",
    "data": {
        "agent_id": 123,
        "name": "Gopal Maurya",
        "unique_id": "VHN00002",
        "profile_photo_url": "https://storage.../photo.jpg",
        "joining_date": "2024-01-15",
        "id_card_valid_until": "2025-12-30"
    }
}
```

**Validation Errors:**

```json
{
    "success": false,
    "message": "Validation error",
    "errors": {
        "agent_photo": ["The agent photo must be an image."],
        "joining_date": ["The joining date must be before or equal to today."],
        "id_card_valid_until": [
            "The id card valid until must be a date after today."
        ]
    }
}
```

---

## 🌐 3. Public ID Verification (No Auth Required)

**Endpoint:** `GET /api/verify-id/{uniqueId}`

**No Authorization Required** (Public API)

**Example:** `GET /api/verify-id/VHN00002`

**Response:**

```json
{
    "success": true,
    "data": {
        "verified": true,
        "employee_id": "VHN00002",
        "name": "Gopal Maurya",
        "designation": "FSE",
        "mobile": "8587577778",
        "profile_photo_url": "https://storage.../photo.jpg",
        "address": "Madhupur colony near Dr LP Lal memorial school lucknow Uttar Pradesh India",
        "working_city": "Lucknow",
        "joining_date": "15 Jan 2024",
        "valid_until": "30 Dec 2025",
        "status": {
            "is_active": true,
            "is_kyc_verified": true,
            "kyc_status": "approved",
            "is_card_valid": true,
            "card_expired": false
        },
        "messages": [
            {
                "type": "success",
                "text": "✅ This is a valid and verified ID card"
            }
        ],
        "verified_at": "04 Nov 2025 14:30:45"
    }
}
```

**Invalid/Blocked Response:**

```json
{
    "success": true,
    "data": {
        "verified": false,
        "employee_id": "VHN00002",
        "name": "Gopal Maurya",
        "designation": "FSE",
        "mobile": "8587577778",
        "status": {
            "is_active": false,
            "is_kyc_verified": true,
            "kyc_status": "approved",
            "is_card_valid": true,
            "card_expired": false
        },
        "messages": [
            {
                "type": "error",
                "text": "⚠️ This account is currently blocked/inactive"
            }
        ]
    }
}
```

**Not Found Response:**

```json
{
    "success": false,
    "message": "ID card not found. Invalid employee ID.",
    "verified": false
}
```

---

## 📱 QR Code Implementation

### Generate QR Code URL:

```
https://your-domain.com/api/verify-id/{employee_id}
```

**Example:**

```
https://api.axngroup.com/api/verify-id/VHN00002
```

### Frontend QR Code Generation (React Example):

```jsx
import QRCode from "qrcode.react";

function IDCard({ employeeId }) {
    const verificationUrl = `https://api.axngroup.com/api/verify-id/${employeeId}`;

    return (
        <div className="id-card">
            {/* Other ID card content */}
            <QRCode value={verificationUrl} size={128} level="H" />
        </div>
    );
}
```

---

## 🎨 Designation Mapping

| Role     | Designation Displayed |
| -------- | --------------------- |
| `agent`  | FSE                   |
| `leader` | Team Leader           |
| `admin`  | Admin                 |

---

## 📋 ID Card Required Fields

All fields available from **Get Agent Details** endpoint:

| Field         | Source                        | Display On Card |
| ------------- | ----------------------------- | --------------- |
| Profile Photo | `profile_photo_url`           | ✅              |
| Name          | `name`                        | ✅              |
| Employee ID   | `unique_id`                   | ✅              |
| Designation   | `designation`                 | ✅              |
| Mobile        | `mobile`                      | ✅              |
| City          | `kyc.working_city`            | ✅              |
| Address       | `profile.address`             | ✅ (Back side)  |
| Joining Date  | `profile.joining_date`        | ✅ (Back side)  |
| Valid Until   | `profile.id_card_valid_until` | ✅              |
| QR Code       | Generated                     | ✅              |

---

## 🔒 Security Notes

1. **Admin endpoints** require admin role authorization
2. **Public verification** is intentionally open (for QR scanning)
3. **Limited data exposure** on public endpoint (no sensitive bank details)
4. **Photo upload** validates file type and size
5. **Old photos** are automatically deleted on update

---

## 🚀 Usage Flow

### Admin Panel Flow:

1. Admin lists agents via existing `/api/admin/users?type=agents`
2. Admin clicks on agent to view details → `GET /api/admin/agents/{id}/details`
3. Admin updates photo/dates → `POST /api/admin/agents/{id}/update-id-card`
4. Generate ID card PDF with QR code containing verification URL

### Public Verification Flow:

1. User scans QR code on ID card
2. Opens URL: `/api/verify-id/{employee_id}`
3. Display verification status with color coding:
    - ✅ Green: Fully verified (active + KYC approved + not expired)
    - ⚠️ Yellow: Warning (pending KYC)
    - ❌ Red: Error (blocked or expired)

---

## 📝 Example: Complete ID Card Generation

```javascript
// 1. Fetch agent details
const response = await fetch("/api/admin/agents/123/details", {
    headers: {
        Authorization: "Bearer " + adminToken,
    },
});

const { data } = await response.json();

// 2. Generate ID card HTML/PDF
const idCardData = {
    photo: data.profile_photo_url,
    name: data.name,
    employeeId: data.unique_id,
    designation: data.designation,
    city: data.kyc?.working_city,
    mobile: data.mobile,
    validUntil: data.profile?.id_card_valid_until,
    qrCodeUrl: `https://api.axngroup.com/api/verify-id/${data.unique_id}`,
};

// 3. Use a PDF library to generate the card
// Example with html2pdf or similar
```

---

## ⚠️ Error Handling

**404 - Agent Not Found:**

```json
{
    "success": false,
    "message": "Agent not found"
}
```

**400 - Not an Agent/Leader:**

```json
{
    "success": false,
    "message": "User is not an agent or leader"
}
```

**403 - Unauthorized:**

```json
{
    "success": false,
    "message": "Unauthorized. Required roles: admin"
}
```

**422 - Validation Error:**

```json
{
    "success": false,
    "message": "Validation error",
    "errors": {
        "agent_photo": ["The agent photo must be an image."]
    }
}
```

---

## 🧪 Testing

**Test Admin Access:**

```bash
curl -X GET "http://localhost:8000/api/admin/agents/123/details" \
  -H "Authorization: Bearer {admin_token}"
```

**Test Public Verification:**

```bash
curl -X GET "http://localhost:8000/api/verify-id/VHN00002"
```

**Test Update:**

```bash
curl -X POST "http://localhost:8000/api/admin/agents/123/update-id-card" \
  -H "Authorization: Bearer {admin_token}" \
  -F "joining_date=2024-01-15" \
  -F "id_card_valid_until=2025-12-30"
```

---

## 📦 Migration Required

Before using these endpoints, run:

```bash
php artisan migrate
```

This adds `joining_date` and `id_card_valid_until` fields to `user_profiles` table.

---

**Questions?** Contact backend team.
