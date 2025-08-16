-- MySQL Query to create Admin user (no wallet needed)
INSERT INTO `users` (
    `unique_id`,
    `name`,
    `mobile`,
    `email`,
    `password`,
    `role`,
    `referral_code`,
    `is_blocked`,
    `created_at`,
    `updated_at`
) VALUES (
    'AXN00001',
    'System Admin',
    '9999999999',
    'admin@axngroup.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'admin',
    NULL,
    0,
    NOW(),
    NOW()
);

-- Create Profile for Admin (optional)
INSERT INTO `user_profiles` (
    `user_id`,
    `agent_photo`,
    `aadhar_number`,
    `pan_number`,
    `address`,
    `dob`,
    `created_at`,
    `updated_at`
) VALUES (
    LAST_INSERT_ID(),
    NULL,
    NULL,
    NULL,
    'Admin Office',
    NULL,
    NOW(),
    NOW()
);

-- NOTE: No wallet created for admin as per requirements
-- Only agents and leaders will have wallets created automatically during registration