# Role-Based API Access Control Summary

## 🔐 **Authentication Endpoints (No Role Required)**
```
POST /api/auth/login/admin     - Admin login
POST /api/auth/login/leader    - Leader login  
POST /api/auth/login/agent     - Agent login
```

## 👤 **Admin Role Access (Admin Only)**
```
POST /api/auth/register/leader - Create new leader (Admin only)
```

## 👥 **Leader Role Access (Leader Only)**
```
POST /api/auth/register/agent        - Create new agent
GET  /api/shops/leader              - View assigned shops
PUT  /api/shops/{id}/status         - Approve/reject shops
GET  /api/bank-transfers/leader     - View assigned transfers
PUT  /api/bank-transfers/{id}/status - Approve/reject transfers
POST /api/wallet/credit             - Credit agent wallets
```

## 🛍️ **Agent Role Access (Agent Only)**
```
POST /api/shops/                    - Create shop onboarding
GET  /api/shops/agent              - View own shops
POST /api/bank-transfers/          - Create bank transfer
GET  /api/bank-transfers/agent     - View own transfers
POST /api/wallet/withdraw          - Request withdrawal
```

## 🔓 **Common Authenticated Access (All Roles)**
```
POST /api/auth/logout              - Logout
GET  /api/auth/profile             - Get profile
GET  /api/wallet/balance           - Check balance
GET  /api/wallet/transactions      - View transactions
GET  /api/wallet/withdrawals       - View withdrawals
GET  /api/profile/*                - Profile management
GET  /api/shops/{id}               - View shop details
GET  /api/bank-transfers/{id}      - View transfer details
```

## 🚫 **Access Restrictions**

### **Admin Cannot:**
- Access wallet endpoints (no wallet)
- Create shops or bank transfers
- Request withdrawals

### **Leader Cannot:**
- Create shops or bank transfers directly
- Access agent-specific endpoints

### **Agent Cannot:**
- Register other users
- Approve/reject requests
- Credit wallets

## ⚡ **Role Hierarchy Flow**
```
Admin → Creates Leaders (No Wallet)
  ↓
Leader → Creates Agents (Has Wallet)
  ↓  
Agent → Creates Shops/Transfers (Has Wallet)
```

## 🧪 **Testing Role Access**

### **1. Create Admin (SQL)**
```sql
INSERT INTO users (unique_id, name, mobile, email, password, role, is_blocked, created_at, updated_at) 
VALUES ('AXN00001', 'Admin', '9999999999', 'admin@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 0, NOW(), NOW());
```

### **2. Test Admin Login & Leader Creation**
```bash
# Login as Admin
POST /api/auth/login/admin
{
    "mobile": "9999999999",
    "password": "password"
}

# Create Leader (Admin token required)
POST /api/auth/register/leader
Authorization: Bearer {admin-token}
{
    "name": "Team Leader",
    "mobile": "8888888888", 
    "password": "password123"
}
```

### **3. Test Leader Login & Agent Creation**
```bash
# Login as Leader
POST /api/auth/login/leader
{
    "mobile": "8888888888",
    "password": "password123"
}

# Create Agent (Leader token required)
POST /api/auth/register/agent
Authorization: Bearer {leader-token}
{
    "name": "Sales Agent",
    "mobile": "7777777777",
    "password": "password123"
}
```

### **4. Test Agent Shop Creation**
```bash
# Login as Agent
POST /api/auth/login/agent
{
    "mobile": "7777777777", 
    "password": "password123"
}

# Create Shop (Agent token required)
POST /api/shops
Authorization: Bearer {agent-token}
{
    "customer_name": "Shop Owner",
    "customer_mobile": "6666666666",
    "team_leader_id": 2
}
```

## ⚠️ **Error Responses**

**Insufficient Role (403):**
```json
{
    "success": false,
    "message": "Unauthorized. Required roles: admin"
}
```

**Unauthenticated (401):**
```json
{
    "success": false,
    "message": "Unauthenticated"
}
```

The role-based access control is now properly implemented and enforced across all endpoints!