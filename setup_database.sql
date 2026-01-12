-- Create database for checkout project
CREATE DATABASE IF NOT EXISTS checkoutpay CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create dedicated user for checkoutpay database
CREATE USER IF NOT EXISTS 'checkoutpay_user'@'localhost' IDENTIFIED BY 'checkoutpay_pass_2024';

-- Grant all privileges on checkoutpay database to the new user
GRANT ALL PRIVILEGES ON checkoutpay.* TO 'checkoutpay_user'@'localhost';

-- Apply changes
FLUSH PRIVILEGES;

-- Verify creation
SHOW DATABASES LIKE 'checkoutpay';
SELECT User, Host FROM mysql.user WHERE User = 'checkoutpay_user';
