-- Create database for checkout project
-- Execute this in phpMyAdmin or MySQL command line

CREATE DATABASE IF NOT EXISTS checkoutpay CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Grant privileges (adjust username/password as needed)
-- GRANT ALL PRIVILEGES ON checkoutpay.* TO 'root'@'localhost';
-- FLUSH PRIVILEGES;
CREATE DATABASE IF NOT EXISTS checkoutpay CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'checkoutpay_user'@'localhost' IDENTIFIED BY 'checkoutpay_pass_2024';
GRANT ALL PRIVILEGES ON checkoutpay.* TO 'checkoutpay_user'@'localhost';
FLUSH PRIVILEGES;