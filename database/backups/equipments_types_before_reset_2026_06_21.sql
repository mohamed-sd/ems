-- MySQL dump 10.13  Distrib 8.4.7, for Win64 (x86_64)
--
-- Host: localhost    Database: equipation_manage
-- ------------------------------------------------------
-- Server version	8.4.7

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `equipments_types`
--

DROP TABLE IF EXISTS `equipments_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `equipments_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `form` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','inactive','','') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipments_types`
--

LOCK TABLES `equipments_types` WRITE;
/*!40000 ALTER TABLE `equipments_types` DISABLE KEYS */;
INSERT INTO `equipments_types` VALUES (1,'1','حفار','active','2026-04-07 11:49:34','2026-04-07 11:49:34'),(2,'2','قلاب','active','2026-04-07 11:49:43','2026-04-07 11:49:43'),(3,'3','خرامة','active','2026-05-01 07:56:22','2026-05-01 07:56:22'),(4,'1','لودر','active','2026-06-16 15:50:11','2026-06-16 15:50:11'),(5,'1','جريدر','active','2026-06-16 15:50:11','2026-06-16 15:50:11'),(6,'1','دوزر','active','2026-06-16 15:50:11','2026-06-16 15:50:11'),(7,'1','شيول','active','2026-06-16 15:50:11','2026-06-16 15:50:11'),(8,'1','رافعة','active','2026-06-16 15:50:11','2026-06-16 15:50:11'),(9,'1','حفّاضة','active','2026-06-16 15:50:11','2026-06-16 15:50:11'),(10,'1','مولّد كهرباء','active','2026-06-16 15:50:11','2026-06-16 15:50:11'),(11,'1','ضاغط هواء','active','2026-06-16 15:50:11','2026-06-16 15:50:11'),(12,'2','شاحنة','active','2026-06-16 15:50:11','2026-06-16 15:50:11'),(13,'2','صهريج','active','2026-06-16 15:50:11','2026-06-16 15:50:11'),(14,'1','أخرى','active','2026-06-16 15:50:11','2026-06-16 15:50:11');
/*!40000 ALTER TABLE `equipments_types` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-21  0:30:35
