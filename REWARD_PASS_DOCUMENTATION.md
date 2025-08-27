# Reward Pass Module Documentation

## Overview
The Reward Pass module allows agents to create reward pass applications for customers. This follows the same approval workflow as shops and bank transfers, where admin approves or rejects the applications.

## Features
- Simple customer registration (name + mobile only)
- Standard approval workflow (pending → approved/rejected)
- Admin approval/rejection with remarks
- Agent and Leader view capabilities
- Search and filtering for admin
- Statistics tracking

## Mandatory Fields
- **Customer Name**: Full name of the customer
- **Customer Mobile**: Mobile number (10-15 digits)

## Database Schema

### Reward Passes Table Structure
```sql
- id (Primary Key)
- agent_id (Foreign Key to users table)
- customer_name (VARCHAR 255)
- customer_mobile (VARCHAR 15)
- status (ENUM: pending, approved, rejected)
- reject_remark (VARCHAR 255, nullable)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)
```

## API Endpoints

### Agent Endpoints

#### 1. Create Reward Pass
- **URL**: `POST /api/reward-passes`
- **Authorization**: Bearer Token (Agent only)
- **Content-Type**: `application/json`
- **Parameters**:
  - `customer_name` (required): Customer's full name
  - `customer_mobile` (required): Customer's mobile number (10-15 digits)
- **Response**: Created reward pass details with pending status

#### 2. Get Agent's Reward Passes
- **URL**: `GET /api/reward-passes/agent`
- **Authorization**: Bearer Token (Agent only)
- **Response**: Paginated list of agent's reward passes

### Leader Endpoints

#### 3. Get Leader's Reward Passes
- **URL**: `GET /api/reward-passes/leader`
- **Authorization**: Bearer Token (Leader only)
- **Response**: Paginated list of reward passes from agents under the leader

### General Endpoints

#### 4. Get Reward Pass Details
- **URL**: `GET /api/reward-passes/{id}`
- **Authorization**: Bearer Token (Agent/Leader/Admin)
- **Response**: Detailed reward pass information
- **Access Control**: 
  - Agent: Own reward passes only
  - Leader: Reward passes from their agents
  - Admin: All reward passes

### Admin Endpoints

#### 5. Get All Reward Passes
- **URL**: `GET /api/reward-passes/admin/all`
- **Authorization**: Bearer Token (Admin only)
- **Query Parameters**:
  - `status`: Filter by status (pending, approved, rejected)
  - `agent_id`: Filter by specific agent ID
  - `leader_id`: Filter by specific leader ID
  - `date_from`: Filter from date
  - `date_to`: Filter to date
  - `search`: Search by customer name, mobile, or agent name
  - `per_page`: Items per page (1-100)
- **Response**: Paginated list with statistics

#### 6. Get Pending Reward Passes
- **URL**: `GET /api/reward-passes/admin/pending`
- **Authorization**: Bearer Token (Admin only)
- **Response**: All pending reward passes for review

#### 7. Review Reward Pass
- **URL**: `PUT /api/reward-passes/admin/{id}/approval`
- **Authorization**: Bearer Token (Admin only)
- **Parameters**:
  - `status` (required): "approved" or "rejected"
  - `admin_remark` (optional): Reason for decision
- **Response**: Updated reward pass details

## Validation Rules

### Customer Name
- Required field
- String type
- Maximum 255 characters

### Customer Mobile
- Required field
- String type  
- Maximum 15 characters
- Must contain only digits (10-15 characters)
- Regex: `/^[0-9]{10,15}$/`

## Status Flow

### 1. Pending (Default)
- Initial status when agent creates reward pass
- Waiting for admin review

### 2. Approved
- Admin has approved the reward pass
- Customer is eligible for rewards

### 3. Rejected
- Admin found issues with the application
- Rejection reason stored in `reject_remark`

## Workflow

### Agent Workflow:
1. Agent identifies potential reward pass customer
2. Collects customer name and mobile number
3. Creates reward pass via API
4. Status is automatically set to "pending"
5. Agent can view all their reward passes

### Leader Workflow:
1. Leader can view all reward passes from their agents
2. Monitor agent performance and customer acquisition
3. No approval rights (same as shops and bank transfers)

### Admin Workflow:
1. View all pending reward passes
2. Review customer information
3. Approve or reject with appropriate remarks
4. Monitor statistics and trends
5. Use filters to manage large volumes

## API Response Examples

### Create Reward Pass Response:
```json
{
    "success": true,
    "message": "Reward pass created successfully",
    "data": {
        "id": 1,
        "agent_id": 3,
        "customer_name": "John Doe",
        "customer_mobile": "9876543210",
        "status": "pending",
        "reject_remark": null,
        "created_at": "2025-08-27T15:45:00.000000Z",
        "updated_at": "2025-08-27T15:45:00.000000Z",
        "agent": {
            "id": 3,
            "name": "Sales Agent"
        }
    }
}
```

### Admin Statistics Response:
```json
{
    "statistics": {
        "total": 150,
        "pending": 25,
        "approved": 100,
        "rejected": 25,
        "today": 8,
        "this_month": 45
    }
}
```

### Admin Approval Response:
```json
{
    "success": true,
    "message": "Reward pass approved successfully",
    "data": {
        "id": 1,
        "status": "approved",
        "reject_remark": null,
        "agent": {
            "id": 3,
            "name": "Sales Agent",
            "parent": {
                "id": 2,
                "name": "Team Leader"
            }
        }
    }
}
```

## Security Features

### 1. Role-Based Access Control
- Only agents can create reward passes
- Only admins can approve/reject
- Leaders can only view their agents' reward passes
- Proper authentication required for all endpoints

### 2. Data Validation
- Mobile number format validation
- Name length restrictions
- Status validation for admin actions

### 3. Authorization Checks
- Agents can only view their own reward passes
- Leaders can only view their agents' reward passes
- Admins have full access

## Error Handling

### Common Error Codes:
- **422**: Validation errors (missing/invalid data)
- **403**: Unauthorized access (wrong role or not your data)
- **404**: Reward pass not found
- **400**: Business logic errors (already processed, etc.)

### Validation Error Examples:
```json
{
    "success": false,
    "message": "Validation error",
    "errors": {
        "customer_mobile": [
            "The customer mobile field must be between 10 and 15 digits."
        ]
    }
}
```

## Integration with Existing System

### Database Relationships:
- **RewardPass** belongs to **User** (agent)
- **User** has many **RewardPasses**
- Follows same pattern as Shop and BankTransfer models

### Route Structure:
- Follows RESTful conventions
- Consistent with existing shop/bank-transfer routes
- Same middleware and role-based protection

### Controller Pattern:
- Consistent error handling with other modules
- Same validation approach
- Similar response structure

## Statistics and Reporting

### Available Statistics:
- Total reward passes created
- Pending applications count
- Approved applications count
- Rejected applications count
- Daily creation count
- Monthly creation count

### Filtering Options:
- By status (pending/approved/rejected)
- By agent ID
- By leader ID (all agents under leader)
- By date range
- By search term (customer name/mobile/agent name)

## Best Practices

### For Agents:
1. Verify customer mobile number before submission
2. Use correct customer name spelling
3. Monitor status of submitted applications
4. Follow up on rejected applications

### For Admins:
1. Review customer information carefully
2. Provide clear rejection reasons
3. Use filtering to manage workload efficiently
4. Monitor trends and patterns in applications

## Postman Collection

The Reward Pass endpoints are included in the main AXN Group API Collection under the "Reward Pass" folder with:
- Simple form submission examples
- All CRUD operations for different roles
- Response examples for approval and rejection
- Proper authentication setup for each role
- Filter and search examples for admin

## Migration Commands

```bash
# Run the reward pass migration
php artisan migrate

# Check migration status
php artisan migrate:status
```

## Route List

```bash
# View all reward pass routes
php artisan route:list --path=reward
```

This module provides a streamlined way for agents to register customers for reward programs while maintaining the same administrative oversight as other business processes in the system.
