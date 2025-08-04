-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 07, 2025 at 11:33 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hr_management_system_with_roles`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_name`, `last_name`, `email`, `username`, `password_hash`, `role_id`, `created_at`, `updated_at`, `is_active`) VALUES
(1, 'John', 'Doe', 'john.doe@example.com', 'johndoe', '$2y$10$abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrs', 1, '2025-07-07 09:08:34', '2025-07-07 09:08:34', 1),
(2, 'Jane', 'Smith', 'jane.smith@example.com', 'janesmith', '$2y$10$abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrs', 5, '2025-07-07 09:08:34', '2025-07-07 09:08:34', 1),
(3, 'Admin', 'User', 'admin@example.com', 'admin', '$2y$10$abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrs', 3, '2025-07-07 09:08:34', '2025-07-07 09:08:34', 1),
(4, 'Bob', 'Johnson', 'bob.j@example.com', 'bobj', '$2y$10$abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrs', 1, '2025-07-07 09:08:34', '2025-07-07 09:08:34', 1),
(5, 'Alice', 'Williams', 'alice.w@example.com', 'alicew', '$2y$10$abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrs', 2, '2025-07-07 09:08:34', '2025-07-07 09:08:34', 1),
(6, 'Finance', 'Officer', 'finance@example.com', 'finance', '$2y$10$abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrs', 4, '2025-07-07 09:08:34', '2025-07-07 09:08:34', 1),
(7, 'brad', 'Juma', 'brad@gmail.com', 'brad1', '$2y$10$Wr7/W2YsMGOsSwb07kn9auTRsqQS85x9mS3mq9mTIz/GGWCb8z2qi', 1, '2025-07-07 09:10:05', '2025-07-07 09:10:05', 1),
(8, 'Russel', 'Jamie', 'Russel@gmail.com', 'Russ1', '$2y$10$Zs2R2vRWIg19w27XEVLMeuHajVqBG2Wkc8.gxqW49emFcjRroK0Ou', 1, '2025-07-07 09:24:08', '2025-07-07 09:24:08', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_username` (`username`),
  ADD KEY `idx_users_role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- Payroll Batches Table
CREATE TABLE IF NOT EXISTS payroll_batches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('pending','approved','paid') DEFAULT 'pending',
  approved_by INT DEFAULT NULL,
  approved_at DATETIME DEFAULT NULL
);

-- Payroll Batch Items Table
CREATE TABLE IF NOT EXISTS payroll_batch_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  batch_id INT NOT NULL,
  employee_id INT NOT NULL,
  base_salary DECIMAL(10,2) NOT NULL,
  bonuses DECIMAL(10,2) DEFAULT 0,
  deductions DECIMAL(10,2) DEFAULT 0,
  net_pay DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (batch_id) REFERENCES payroll_batches(id),
  FOREIGN KEY (employee_id) REFERENCES employees(id)
);

-- Add salary fields to employees table
ALTER TABLE employees 
  ADD COLUMN base_salary DECIMAL(10,2) DEFAULT 0,
  ADD COLUMN bonuses DECIMAL(10,2) DEFAULT 0,
  ADD COLUMN deductions DECIMAL(10,2) DEFAULT 0;
