-- phpMyAdmin SQL Dump
-- version 4.5.1
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Generation Time: Sep 02, 2025 at 04:50 AM
-- Server version: 10.1.13-MariaDB
-- PHP Version: 5.6.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `equipation_manage`
--

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `id` int(11) NOT NULL,
  `project` varchar(10) NOT NULL,
  `start` varchar(30) NOT NULL,
  `end` varchar(30) NOT NULL,
  `status` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `contracts`
--

INSERT INTO `contracts` (`id`, `project`, `start`, `end`, `status`) VALUES
(1, '1', '2025-09-01', '2025-09-10', 'Ù†Ø´Ø'),
(2, '6', '2025-09-10', '2025-09-27', '1'),
(3, '6', '2025-09-16', '2025-09-17', '1'),
(4, '4', '2025-02-06', '2025-12-29', '1'),
(5, '1', '2014-07-01', '2010-06-08', '1'),
(6, '5', '2025-09-01', '2025-09-30', '1'),
(7, '3', '2025-09-18', '2025-09-22', '1'),
(8, '2', '2025-09-01', '2025-09-30', '1');

-- --------------------------------------------------------

--
-- Table structure for table `equipments`
--

CREATE TABLE `equipments` (
  `id` int(11) NOT NULL,
  `suppliers` varchar(10) NOT NULL,
  `code` varchar(100) NOT NULL,
  `type` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `status` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `equipments`
--

INSERT INTO `equipments` (`id`, `suppliers`, `code`, `type`, `name`, `status`) VALUES
(1, '6', 'TQR12', 'Ø­ÙØ§Ø±', '23', 'Ù…ØªØ§Ø­Ø©'),
(2, '6', 'TQR11', 'Ø­ÙØ§Ø±', '26', 'Ù…ØªØ§Ø­Ø©'),
(3, '5', 'EX120', 'Ù‚Ù„Ø§Ø¨', '08', 'Ù…ØªØ§Ø­Ø©'),
(4, '5', 'EX110', 'Ù‚Ù„Ø§Ø¨', '09', 'Ù…ØªØ§Ø­Ø©');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `client` varchar(150) NOT NULL,
  `location` varchar(200) NOT NULL,
  `total` varchar(50) NOT NULL,
  `create_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `name`, `client`, `location`, `total`, `create_at`) VALUES
(1, 'Ø§Ù„Ø±ÙˆØ³ÙŠØ©', 'Ù…Ø­Ù…Ø¯ Ø³ÙŠØ¯', 'Ø§Ù„Ø®Ø±Ø·ÙˆÙ…', '4000', '2025-09-01 22:19:54'),
(2, 'Ù…Ø´Ø±ÙˆØ¹ ÙˆØ§Ø¯ÙŠ Ø¯Ø¬Ù„Ø©', 'Ø­Ø³Ù† Ø³ÙŠØ¯', 'Ø¯Ø§Ø±ÙÙˆØ±', '3000', '2025-09-01 22:21:26'),
(3, 'Ù…Ø´Ø±ÙˆØ¹ Ø³Ø§Ø¨ØªÙˆØ±', 'Ø§Ø³Ø­Ø§Ù‚ Ø³ÙŠØ¯', 'Ø§Ù„Ù‚Ø¶Ø§Ø±Ù', '5000', '2025-09-01 22:22:14'),
(4, 'Ù…Ø´Ø±ÙˆØ¹ Ø§Ù„Ù…Ù†Ø§Ù‚Ù„', 'Ø¨ÙƒØ±ÙŠ Ø­Ø³Ù† Ø§Ø­Ù…Ø¯', 'Ø§Ù„Ù…Ù†Ø§Ù‚Ù„', '4500', '2025-09-01 22:23:04'),
(5, 'ÙˆØ§Ø¯ÙŠ Ø§Ù„Ø³ÙŠÙ„ÙƒÙˆÙ† ', 'Ø­Ø§ØªÙ… Ø§Ù„Ø­Ø§Ø¬ Ø§Ù„Ø·ÙˆÙŠÙ„', 'ÙƒØ³Ù„Ø§', '5400', '2025-09-01 22:23:54'),
(6, 'Ù…Ø´Ø±ÙˆØ¹ Ø§Ù„Ø±Ø§Ø¬Ø­', 'Ø®Ø§Ù„Ø¯ Ø¹ÙˆØ¶ Ø§Ù„Ù„Ù‡', 'Ø§Ù„Ø¬Ø²ÙŠØ±Ø©', '8000', '2025-09-01 22:32:46');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `status` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `phone`, `status`) VALUES
(1, 'Ù…Ø­Ù…Ø¯', '01123475758', 'Ù†Ø´Ø·'),
(2, 'Ø­Ø³Ù† Ø³ÙŠØ¯', '0987878787', 'Ù†Ø´Ø·'),
(3, 'Ø±Ø§Ø´Ø¯ Ø§Ù„Ù…Ø§Ø¬Ø¯', '0928293983', 'Ù†Ø´Ø·'),
(4, 'Ù…Ø­Ù…Ø¯ ÙØ¤Ø§Ø¯', '01123475758', 'Ù†Ø´Ø·'),
(5, 'ØªØ§Ù…Ø± Ø¹ÙˆØ¶ Ø¹Ù…Ø±', '0915657579', 'Ù†Ø´Ø·'),
(6, 'Ø³Ø§Ù…Ø­ Ø¨ÙƒØ±ÙŠ Ø§Ù„Ø¨Ù„Ø©', '0915657579', 'Ù†Ø´Ø·');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `equipments`
--
ALTER TABLE `equipments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
--
-- AUTO_INCREMENT for table `equipments`
--
ALTER TABLE `equipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
