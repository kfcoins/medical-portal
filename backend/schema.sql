-- schema.sql
-- Run this file in phpMyAdmin or MySQL CLI to set up the database

CREATE DATABASE IF NOT EXISTS PharmaTrust CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE PharmaTrust;

CREATE TABLE IF NOT EXISTS `users` (
    `id` VARCHAR(36) PRIMARY KEY,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `email_otp` VARCHAR(10) NULL,
    `otp_expires_at` DATETIME NULL,
    `role` ENUM('patient', 'pharmacy', 'admin') DEFAULT 'patient',
    `ghana_card` VARCHAR(50),
    `nhis_number` VARCHAR(100),
    `nhis_card_url` VARCHAR(255),
    `nhis_status` ENUM('pending', 'approved', 'declined') NULL,
    `region` VARCHAR(100),
    `is_verified` BOOLEAN DEFAULT FALSE,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `agents` (
    `id` VARCHAR(36) PRIMARY KEY,
    `user_id` VARCHAR(36) NOT NULL,
    `agent_id` VARCHAR(50) UNIQUE,
    `pharmacy_name` VARCHAR(150),
    `council_reg_no` VARCHAR(100),
    `fda_license_no` VARCHAR(100),
    `agent_type` VARCHAR(100),
    `region` VARCHAR(100) NOT NULL,
    `address` TEXT,
    `rating` DECIMAL(3,2) DEFAULT 0.00,
    `total_orders` INT DEFAULT 0,
    `verification_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `id_front_url` VARCHAR(255),
    `pharm_license_url` VARCHAR(255),
    `bio` TEXT,
    `nhis_enabled` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `medicines` (
    `id` VARCHAR(36) PRIMARY KEY,
    `agent_id` VARCHAR(36) NULL,
    `name` VARCHAR(150) NOT NULL,
    `generic_name` VARCHAR(150),
    `category` ENUM('malaria', 'antibiotics', 'chronic', 'otc', 'other') NOT NULL,
    `description` TEXT,
    `manufacturer` VARCHAR(150),
    `batch_number` VARCHAR(100),
    `fda_approved` BOOLEAN DEFAULT FALSE,
    `nhis_listed` BOOLEAN DEFAULT FALSE,
    `price` DECIMAL(10, 2) NOT NULL,
    `unit` VARCHAR(50) DEFAULT 'per pack',
    `stock_qty` INT DEFAULT 0,
    `expiry_date` DATE,
    `requires_rx` BOOLEAN DEFAULT FALSE,
    `image_url` VARCHAR(255),
    `qr_code` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `orders` (
    `id` VARCHAR(36) PRIMARY KEY,
    `order_no` VARCHAR(100) UNIQUE NOT NULL,
    `patient_id` VARCHAR(36) NOT NULL,
    `agent_id` VARCHAR(36) NOT NULL,
    `total_amount` DECIMAL(10, 2) NOT NULL,
    `nhis_deduction` DECIMAL(10, 2) DEFAULT 0.00,
    `amount_due` DECIMAL(10, 2) NOT NULL,
    `prescription` VARCHAR(255),
    `status` ENUM('pending', 'confirmed', 'dispensed', 'delivered', 'cancelled') DEFAULT 'pending',
    `payment_method` ENUM('cash', 'momo', 'nhis', 'card') DEFAULT 'cash',
    `payment_status` ENUM('unpaid', 'paid') DEFAULT 'unpaid',
    `delivery_address` TEXT,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `order_items` (
    `id` VARCHAR(36) PRIMARY KEY,
    `order_id` VARCHAR(36) NOT NULL,
    `medicine_id` VARCHAR(36) NOT NULL,
    `quantity` INT NOT NULL,
    `unit_price` DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `contacts` (
    `id` VARCHAR(36) PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `phone` VARCHAR(50) NOT NULL,
    `email` VARCHAR(150),
    `user_type` VARCHAR(100),
    `message` TEXT NOT NULL,
    `is_read` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `messages` (
    `id` VARCHAR(36) PRIMARY KEY,
    `sender_id` VARCHAR(36) NOT NULL,
    `receiver_id` VARCHAR(36) NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);
