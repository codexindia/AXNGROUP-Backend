# Leader-Agent-Shop Relationship Documentation (Updated with parent_id)

## Overview
This document explains the improved hierarchical structure using `parent_id` in the users table for direct parent-child relationships between users (admin -> leader -> agent).

## Database Structure (Updated)

### Direct Hierarchical Relationships
```
Admin (parent_id: null)
  └── Leader (parent_id: admin_id)
      └── Agent (parent_id: leader_id)
          └── Shop (agent_id)
              └── BankTransfer (agent_id, shop_id)
```

### Key Tables
1. **users** - Contains admins, leaders, and agents with `parent_id` for hierarchy
2. **shops** - Contains shop information with only `agent_id` (team leader derived from agent's parent)
3. **bank_transfers** - Contains bank transfer requests with `agent_id` and `shop_id` (team leader derived automatically)

## Key Improvements

### 1. **Direct Parent-Child Relationships**
- Added `parent_id` to users table
- Admin creates leaders (parent_id = admin_id)
- Leader creates agents (parent_id = leader_id)
- No more indirect relationships through shops

### 2. **Automatic Leader Assignment**
- Bank transfers no longer require `team_leader_id` in request
- Team leader is automatically derived from agent's parent
- Shops no longer require `team_leader_id` in request
- Team leader is automatically the agent's parent

### 3. **Cleaner API Requests**

**Before (Complex):**
```json
POST /api/bank-transfers/
{
    "shop_id": 1,
    "customer_name": "John Doe",
    "customer_mobile": "1234567890", 
    "amount": 10000,
    "team_leader_id": 3  // Had to specify manually
}
```

**After (Simplified):**
```json
POST /api/bank-transfers/
{
    "shop_id": 1,
    "customer_name": "John Doe",
    "customer_mobile": "1234567890",
    "amount": 10000
    // team_leader_id is automatic based on agent's parent
}
```

## Updated Model Relationships

### User Model:
```php
// Parent relationship (leader for agent, admin for leader)
public function parent()
{
    return $this->belongsTo(User::class, 'parent_id');
}

// Children relationship (agents for leader, leaders for admin)  
public function children()
{
    return $this->hasMany(User::class, 'parent_id');
}

// Get all agents under this leader
public function agents()
{
    return $this->hasMany(User::class, 'parent_id')->where('role', 'agent');
}

// Get all leaders under this admin
public function leaders() 
{
    return $this->hasMany(User::class, 'parent_id')->where('role', 'leader');
}
```

### Shop Model:
```php
// Get the team leader through the agent's parent relationship
public function getTeamLeaderAttribute()
{
    return $this->agent ? $this->agent->parent : null;
}
```

### BankTransfer Model:
```php
// Get the team leader through the agent's parent relationship  
public function getTeamLeaderAttribute()
{
    return $this->agent ? $this->agent->parent : null;
}
```

## Database Queries for Common Tasks

### 1. Find Which Leader a Specific Agent Belongs To:
```php
$agent = User::find($agentId);
$leader = $agent->parent; // Direct parent relationship
```

### 2. Find All Agents Under a Leader:
```php
$leader = User::find($leaderId);
$agents = $leader->agents; // Direct children relationship
// OR
$agents = User::where('parent_id', $leaderId)->where('role', 'agent')->get();
```

### 3. Find All Leaders Under an Admin:
```php
$admin = User::find($adminId);
$leaders = $admin->leaders; // Direct children relationship  
// OR
$leaders = User::where('parent_id', $adminId)->where('role', 'leader')->get();
```

### 4. Find Team Leader for a Bank Transfer:
```php
$bankTransfer = BankTransfer::with('agent.parent')->find($transferId);
$teamLeader = $bankTransfer->team_leader; // Uses accessor
```

## API Endpoints (Updated)

### 1. Get All Agents Under a Leader
```
GET /api/relationships/leader/{leaderId}/agents
```
**Response:**
```json
{
    "success": true,
    "message": "Agents retrieved successfully", 
    "data": [
        {
            "id": 5,
            "name": "Agent Name",
            "mobile": "1234567890",
            "role": "agent",
            "parent_id": 3
        }
    ],
    "total": 1
}
```

### 2. Get Complete Leader Hierarchy
```
GET /api/relationships/leader/{leaderId}/hierarchy
```

### 3. Get Bank Transfers by Shop (Updated Response)
```
GET /api/relationships/shop/{shopId}/bank-transfers
```
**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "shop_id": 1,
            "agent_id": 5,
            "amount": "10000.00",
            "status": "pending",
            "shop": {...},
            "agent": {
                "id": 5,
                "name": "Agent Name",
                "parent": {
                    "id": 3,
                    "name": "Leader Name",
                    "role": "leader"
                }
            }
        }
    ]
}
```

## Registration Process (Updated)

### 1. Admin Registers Leader:
```json
POST /api/auth/register/leader
{
    "name": "Leader Name",
    "mobile": "1234567890",
    "email": "leader@example.com", 
    "password": "password123"
}
// Automatically sets parent_id = admin_id
```

### 2. Leader Registers Agent:
```json
POST /api/auth/register/agent
{
    "name": "Agent Name",
    "mobile": "1234567890", 
    "email": "agent@example.com",
    "password": "password123"
}
// Automatically sets parent_id = leader_id
```

### 3. Agent Creates Shop:
```json
POST /api/shops/
{
    "customer_name": "Shop Owner",
    "customer_mobile": "1234567890"
}
// team_leader_id is derived from agent's parent automatically
```

### 4. Agent Creates Bank Transfer:
```json
POST /api/bank-transfers/
{
    "shop_id": 1,
    "customer_name": "Customer Name",
    "customer_mobile": "1234567890",
    "amount": 10000
}
// team_leader_id is derived from agent's parent automatically
```

## Migrations Applied
1. `2024_08_23_000001_add_shop_id_to_bank_transfers_table.php` - Added shop_id
2. `2024_08_23_000002_add_parent_id_to_users_table.php` - Added parent_id for hierarchy
3. `2024_08_23_000003_remove_team_leader_id_from_bank_transfers.php` - Removed redundant team_leader_id
4. `2024_08_23_000004_remove_team_leader_id_from_shops.php` - Removed redundant team_leader_id

## Benefits of This Approach

### 1. **Simplified API Calls**
- No need to manually specify team_leader_id
- Automatic parent-child relationship handling
- Fewer validation rules needed

### 2. **Data Consistency**
- Cannot accidentally assign wrong leader to agent's work
- Direct hierarchical relationships prevent data inconsistencies
- Single source of truth for relationships

### 3. **Better Performance**
- Direct parent-child queries are faster
- No complex JOIN operations needed for basic relationships
- Simpler database structure

### 4. **Easier Maintenance**
- Clear hierarchical structure
- Automatic relationship management
- Less prone to human errors

## Security Notes
- Agents can only work under their assigned leader (parent)
- Leaders can only manage their direct children (agents)
- Admins have access to all hierarchy levels
- All endpoints use proper role-based middleware with parent-child validation
