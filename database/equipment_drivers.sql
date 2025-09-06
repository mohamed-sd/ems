-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 06, 2025 at 12:42 PM
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
-- Database: `equipation_manage`
--

-- --------------------------------------------------------

--
-- Table structure for table `equipment_drivers`
--

CREATE TABLE `equipment_drivers` (
  `id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `equipment_drivers`
--

INSERT INTO `equipment_drivers` (`id`, `equipment_id`, `driver_id`) VALUES
(1, 5, 1),
(2, 5, 2),
(4, 5, 3);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `equipment_drivers`
--
ALTER TABLE `equipment_drivers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `equipment_id` (`equipment_id`,`driver_id`),
  ADD KEY `driver_id` (`driver_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `equipment_drivers`
--
ALTER TABLE `equipment_drivers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `equipment_drivers`
--
ALTER TABLE `equipment_drivers`
  ADD CONSTRAINT `equipment_drivers_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `equipment_drivers_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
