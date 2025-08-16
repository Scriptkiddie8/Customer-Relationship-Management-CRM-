-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 16, 2024 at 08:54 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `crm_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `leads`
--

CREATE TABLE `leads` (
  `id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `source` varchar(50) DEFAULT NULL,
  `form` varchar(50) DEFAULT NULL,
  `channel` varchar(50) DEFAULT NULL,
  `stage` varchar(50) DEFAULT NULL,
  `owner` int(11) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `labels` varchar(255) DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `tag` enum('Individual','B2B') DEFAULT NULL,
  `status` enum('new','followup','contacted','payment_done','completed','lost') DEFAULT 'new',
  `progress` varchar(255) DEFAULT 'Not Started',
  `category` enum('normal','b2b') DEFAULT 'normal',
  `note` text DEFAULT NULL,
  `converted_price` decimal(10,2) DEFAULT NULL,
  `conversion_date` datetime DEFAULT NULL,
  `payment_details` text DEFAULT NULL,
  `payment_screenshot` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leads`
--

INSERT INTO `leads` (`id`, `created_at`, `name`, `email`, `source`, `form`, `channel`, `stage`, `owner`, `phone`, `labels`, `requirements`, `tag`, `status`, `progress`, `category`, `note`, `converted_price`, `conversion_date`, `payment_details`, `payment_screenshot`) VALUES
(1, '0000-00-00 00:00:00', 'Bhuvan Chandra Chintala', 'chandrabhuone@gmail.com', 'Paid', 'NLF-copy', 'Email address', 'Intake', 14, '9390182727', '0', NULL, NULL, 'new', 'Not Interested', 'b2b', NULL, NULL, NULL, NULL, NULL),
(3, '0000-00-00 00:00:00', 'Amar Wadhwani', 'amarwadhwani24@gmail.com', 'Paid', 'NLF-copy', 'Email address', 'Intake', 13, '+19714882200', '0', NULL, NULL, 'new', 'Converted', 'normal', NULL, NULL, NULL, NULL, NULL),
(4, '0000-00-00 00:00:00', 'Ravi Tank', 'sunnytank410@gmail.com', 'Paid', 'NLF-copy', 'Email address', 'Intake', 14, '+917622988547', '0', NULL, NULL, 'new', 'First Call Done', 'b2b', NULL, NULL, NULL, NULL, NULL),
(5, '0000-00-00 00:00:00', 'Asish Barman', 'asisbarpeta982@gmail.com', 'Paid', 'NLF-copy', 'Email address', 'Intake', 13, '+917896361214', '0', NULL, NULL, 'new', 'Follow-up', '', NULL, NULL, NULL, NULL, NULL),
(6, '0000-00-00 00:00:00', 'Kirankumar Radadiya', 'mannskyrose5@gmail.com', 'Paid', 'NLF-copy', 'Email address', 'Intake', 13, '9825931102', '0', NULL, NULL, 'new', 'Not Interested', 'normal', NULL, NULL, NULL, NULL, NULL),
(7, '0000-00-00 00:00:00', 'gupta', 'guptavikrantllb6@gmail.com', 'Paid', 'NLF-copy', 'Email address', 'Intake', 13, '+919389286320', '0', NULL, NULL, 'new', 'Follow-up', 'b2b', NULL, NULL, NULL, NULL, NULL),
(8, '0000-00-00 00:00:00', 'Aaviinash Ingale', 'replyavinash@gmail.com', 'Paid', 'NLF-copy', 'Email address', '1st Call Done', 13, '+919881154254', '0', NULL, NULL, 'new', 'Not Started', 'normal', NULL, NULL, NULL, NULL, NULL),
(9, '0000-00-00 00:00:00', 'Bilal ML', 'bilalml@gmail.com', 'Paid', 'NLF-copy', 'Email address', 'Intake', 15, '+919916118119', '0', NULL, NULL, 'new', 'First Call Done', 'b2b', NULL, NULL, NULL, NULL, NULL),
(10, '0000-00-00 00:00:00', 'Vaibhav Vaibhav', 'vaibhavambatkar14@gmail.com', 'Paid', 'NLF-copy', 'Email address', 'Intake', 15, '+918180803674', '0', NULL, NULL, 'new', 'Didn\'t Connect', 'b2b', NULL, NULL, NULL, NULL, NULL),
(11, '0000-00-00 00:00:00', 'Vaishnav Singh Thakur', 'vaishnavtravel@yahoo.com', 'Paid', 'NLF-copy', 'Email address', 'Intake', 15, '+919827245081', '0', NULL, NULL, 'new', 'Quote Sent', 'b2b', NULL, NULL, NULL, NULL, NULL),
(12, '0000-00-00 00:00:00', 'Md Yusufar Rahman', 'yusufar0786@gmail.com', 'Paid', 'NLF-copy', 'Email address', 'Intake', 15, '+919954816116', '0', NULL, NULL, 'new', 'Converted', 'b2b', NULL, NULL, NULL, NULL, NULL),
(13, '0000-00-00 00:00:00', 'Avi Ghode', 'ghode.avi@gmail.com', 'Paid', 'NLF-copy', 'Email address', '1st Call Done', 18, '+919822961505', '0', NULL, NULL, 'new', 'Not Started', 'normal', NULL, NULL, NULL, NULL, NULL),
(14, '0000-00-00 00:00:00', 'Rajeev Gupta', 'guptarajeev3333@gmail.com', 'Paid', 'NLF-copy', 'Email address', 'Intake', 18, '+918079016107', '0', NULL, NULL, 'new', 'Not Started', 'normal', NULL, NULL, NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD KEY `owner_id` (`owner`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `leads`
--
ALTER TABLE `leads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1469;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
