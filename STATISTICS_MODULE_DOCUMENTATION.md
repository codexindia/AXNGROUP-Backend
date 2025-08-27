# Statistics Module Documentation

## Overview
The Statistics Module provides comprehensive analytics and reporting for agents and leaders in the AXN Group system. It aggregates data from Shop Onboarding, Bank Transfers, and Reward Pass modules to provide insights into performance and activity.

## API Endpoints

### 1. Agent Statistics
**GET** `/api/statistics/agent`

Get comprehensive statistics for the authenticated agent.

#### Authorization
- **Required**: Bearer token (Agent only)
- **Middleware**: `auth:sanctum`, `role:agent`

#### Request Headers
```
Authorization: Bearer {agent_token}
Content-Type: application/json
Accept: application/json
```

#### Response Format
```json
{
    "success": true,
    "data": {
        "agent_info": {
            "id": 3,
            "name": "Sales Agent",
            "email": "agent@example.com",
            "leader": {
                "id": 2,
                "name": "Team Leader"
            }
        },
        "shop_onboarding": {
            "total": 45,
            "approved": 30,
            "pending": 10,
            "rejected": 5,
            "this_month": 8
        },
        "bank_transfers": {
            "total": 25,
            "approved": 20,
            "pending": 3,
            "rejected": 2,
            "total_amount": "750000.00",
            "this_month": 5,
            "this_month_amount": "125000.00"
        },
        "reward_passes": {
            "total": 15,
            "approved": 12,
            "pending": 2,
            "rejected": 1,
            "this_month": 3
        },
        "summary": {
            "total_activities": 85,
            "total_approved": 62,
            "total_pending": 15,
            "total_rejected": 8,
            "success_rate": 72.94
        }
    }
}
```

#### Statistics Included
- **Agent Info**: Basic agent details and assigned leader
- **Shop Onboarding**: Complete breakdown by status (total, approved, pending, rejected)
- **Bank Transfers**: Transaction counts, amounts, and monthly summaries
- **Reward Passes**: Customer registration statistics
- **Overall Summary**: Combined metrics and success rates

---

### 2. Leader Team Statistics
**GET** `/api/statistics/leader`

Get comprehensive team statistics for the authenticated leader, including data from all assigned agents.

#### Authorization
- **Required**: Bearer token (Leader only)
- **Middleware**: `auth:sanctum`, `role:leader`

#### Request Headers
```
Authorization: Bearer {leader_token}
Content-Type: application/json
Accept: application/json
```

#### Response Format
```json
{
    "success": true,
    "data": {
        "leader_info": {
            "id": 2,
            "name": "Team Leader",
            "email": "leader@example.com",
            "total_agents": 5
        },
        "shop_onboarding": {
            "total": 150,
            "approved": 120,
            "pending": 20,
            "rejected": 10,
            "this_month": 25
        },
        "bank_transfers": {
            "total": 80,
            "approved": 65,
            "pending": 10,
            "rejected": 5,
            "total_amount": "2450000.00",
            "this_month": 15,
            "this_month_amount": "450000.00"
        },
        "reward_passes": {
            "total": 60,
            "approved": 50,
            "pending": 8,
            "rejected": 2,
            "this_month": 12
        },
        "summary": {
            "total_activities": 290,
            "total_approved": 235,
            "total_pending": 38,
            "total_rejected": 17,
            "success_rate": 81.03
        },
        "agent_performance": [
            {
                "agent_id": 3,
                "agent_name": "Sales Agent",
                "shops": {
                    "total": 45,
                    "approved": 30
                },
                "bank_transfers": {
                    "total": 25,
                    "approved": 20,
                    "amount": "750000.00"
                },
                "reward_passes": {
                    "total": 15,
                    "approved": 12
                },
                "total_activities": 85,
                "total_approved": 62,
                "success_rate": 72.94
            }
        ]
    }
}
```

#### Statistics Included
- **Leader Info**: Basic leader details and agent count
- **Aggregated Team Data**: Combined statistics from all assigned agents
- **Individual Agent Performance**: Detailed breakdown per agent
- **Success Rates**: Performance metrics for team management

---

## Data Sources

### Shop Onboarding Module
- **Model**: `Shop`
- **Statuses**: pending, approved, rejected
- **Key Metrics**: 
  - Total onboarding requests
  - Approval/rejection rates
  - Monthly activity

### Bank Transfer Module
- **Model**: `BankTransfer`
- **Statuses**: pending, approved, rejected
- **Key Metrics**:
  - Transfer counts by status
  - Total approved amounts
  - Monthly transaction volumes

### Reward Pass Module
- **Model**: `RewardPass`
- **Statuses**: pending, approved, rejected
- **Key Metrics**:
  - Customer registration counts
  - Approval rates
  - Monthly registrations

---

## Key Features

### 🔒 **Role-Based Access**
- **Agents**: Can only view their own statistics
- **Leaders**: Can view aggregated team statistics and individual agent performance
- **Secure**: Role-based middleware prevents unauthorized access

### 📊 **Comprehensive Metrics**
- **Status Breakdowns**: Complete counts by approval status
- **Time-Based Analysis**: Current month vs. all-time statistics
- **Success Rates**: Performance calculation with percentage metrics
- **Financial Data**: Bank transfer amounts and totals

### 👥 **Team Management**
- **Agent Performance**: Individual agent statistics within team
- **Ranking System**: Agents sorted by total activities
- **Comparative Analysis**: Easy comparison between team members

### 📈 **Business Intelligence**
- **Activity Tracking**: Monitor all three core business activities
- **Trend Analysis**: Monthly vs. total performance comparison
- **Success Measurement**: Approval rates and rejection analysis

---

## Usage Examples

### Agent Dashboard Integration
```javascript
// Fetch agent statistics for dashboard
fetch('/api/statistics/agent', {
    headers: {
        'Authorization': 'Bearer ' + agentToken,
        'Content-Type': 'application/json'
    }
})
.then(response => response.json())
.then(data => {
    const stats = data.data;
    
    // Display shop onboarding stats
    document.getElementById('total-shops').innerText = stats.shop_onboarding.total;
    document.getElementById('approved-shops').innerText = stats.shop_onboarding.approved;
    
    // Display bank transfer stats
    document.getElementById('total-amount').innerText = stats.bank_transfers.total_amount;
    
    // Display success rate
    document.getElementById('success-rate').innerText = stats.summary.success_rate + '%';
});
```

### Leader Team Overview
```javascript
// Fetch leader team statistics
fetch('/api/statistics/leader', {
    headers: {
        'Authorization': 'Bearer ' + leaderToken,
        'Content-Type': 'application/json'
    }
})
.then(response => response.json())
.then(data => {
    const teamStats = data.data;
    
    // Display team overview
    document.getElementById('team-size').innerText = teamStats.leader_info.total_agents;
    document.getElementById('team-success-rate').innerText = teamStats.summary.success_rate + '%';
    
    // Display individual agent performance
    teamStats.agent_performance.forEach(agent => {
        console.log(`${agent.agent_name}: ${agent.success_rate}% success rate`);
    });
});
```

---

## Performance Considerations

### Database Optimization
- **Indexed Fields**: All foreign keys (agent_id) are properly indexed
- **Efficient Queries**: Uses single queries with COUNT and SUM aggregations
- **Minimal Joins**: Limited relationship loading for performance

### Caching Recommendations
```php
// Consider caching statistics for frequently accessed data
$cacheKey = "agent_stats_{$agentId}";
$stats = Cache::remember($cacheKey, 300, function () use ($agentId) {
    return $this->calculateAgentStatistics($agentId);
});
```

### Response Size
- **Optimized**: Only essential data included in responses
- **Pagination**: Not required for statistics (summary data only)
- **Efficient**: Calculated metrics rather than raw data transfer

---

## Security Features

### Authentication
- **Bearer Token**: Required for all endpoints
- **Role Validation**: Strict role-based access control
- **User Context**: Statistics scoped to authenticated user's permissions

### Authorization Matrix
| Role   | Agent Stats | Leader Stats | Notes |
|--------|-------------|--------------|-------|
| Agent  | ✅ Own Only | ❌ No Access | Can only view personal statistics |
| Leader | ❌ No Access | ✅ Team Only | Can view all assigned agents' data |
| Admin  | ❌ No Access | ❌ No Access | Uses separate admin reporting endpoints |

### Data Privacy
- **Scope Limitation**: Agents cannot access other agents' data
- **Leader Boundary**: Leaders only see their assigned agents
- **No Cross-Team**: No access to other teams' statistics

---

## Error Handling

### Common Response Codes
- **200**: Success - Statistics retrieved
- **401**: Unauthorized - Invalid or missing token
- **403**: Forbidden - Insufficient role permissions
- **500**: Server Error - Database or processing error

### Error Response Format
```json
{
    "success": false,
    "message": "Only agents can view their statistics"
}
```

---

## Integration with Other Modules

### Wallet Module
- **Payout Correlation**: Statistics can be cross-referenced with payout history
- **Performance Incentives**: Success rates can drive commission calculations

### KYC Module
- **Verification Status**: Future enhancement to include KYC completion rates
- **Compliance Tracking**: Monitor KYC submission and approval statistics

### Reporting Module
- **Admin Reports**: Statistics feed into broader admin reporting system
- **Daily Reports**: Monthly statistics complement daily activity reports

---

## Future Enhancements

### Planned Features
1. **Time Range Filters**: Custom date ranges for statistics
2. **Comparison Views**: Month-over-month performance comparison
3. **Export Functionality**: PDF/Excel export of statistics
4. **Graphical Data**: Chart-ready data formats
5. **Real-time Updates**: WebSocket-based live statistics

### Performance Improvements
1. **Background Processing**: Calculate statistics via scheduled jobs
2. **Data Warehousing**: Separate analytics database for complex queries
3. **Caching Strategy**: Redis-based caching for frequently accessed data

---

## Testing

### Postman Collection
The AXN Group Postman collection includes comprehensive examples for both endpoints:

- **Agent Statistics**: `GET /api/statistics/agent`
- **Leader Statistics**: `GET /api/statistics/leader`

### Test Scenarios
1. **Agent Access**: Verify agents can access their statistics
2. **Leader Team View**: Confirm leaders see aggregated team data
3. **Role Restrictions**: Ensure proper access control
4. **Data Accuracy**: Validate statistics match actual database counts
5. **Performance**: Monitor response times for large datasets

### Sample Test Data
```php
// Create test data for statistics validation
$agent = User::factory()->agent()->create();
$leader = User::factory()->leader()->create();

// Associate agent with leader
$agent->update(['parent_id' => $leader->id]);

// Create test activities
Shop::factory()->count(10)->create(['agent_id' => $agent->id]);
BankTransfer::factory()->count(5)->create(['agent_id' => $agent->id]);
RewardPass::factory()->count(3)->create(['agent_id' => $agent->id]);
```

---

## Conclusion

The Statistics Module provides essential business intelligence capabilities for the AXN Group system, enabling:

- **Agent Performance Tracking**: Individual productivity and success metrics
- **Team Management**: Leader oversight of agent performance
- **Business Insights**: Comprehensive activity and approval analytics
- **Decision Support**: Data-driven performance evaluation

The module integrates seamlessly with existing system architecture while maintaining security, performance, and usability standards.
