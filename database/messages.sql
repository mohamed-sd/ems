-- ====================================================
-- جدول الرسائل الداخلية - Internal Messages Table
-- نظام المراسلات الداخلي - EMS Chat System
-- ====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Table structure for table `messages`
--

CREATE TABLE IF NOT EXISTS `messages` (
  `id`                  INT(11)       NOT NULL AUTO_INCREMENT        COMMENT 'المعرف الفريد',
  `company_id`          INT(11)       NOT NULL                       COMMENT 'رقم الشركة - لعزل الرسائل بين الشركات',
  `sender_id`           INT(11)       NOT NULL                       COMMENT 'رقم المرسل (users.id)',
  `receiver_id`         INT(11)       NOT NULL                       COMMENT 'رقم المستلم (users.id)',
  `message`             TEXT          NOT NULL                       COMMENT 'نص الرسالة',
  `is_read`             TINYINT(1)    NOT NULL  DEFAULT 0            COMMENT '0=غير مقروءة، 1=مقروءة',
  `read_at`             DATETIME      NULL      DEFAULT NULL         COMMENT 'وقت القراءة',
  `created_at`          DATETIME      NOT NULL  DEFAULT CURRENT_TIMESTAMP COMMENT 'وقت الإرسال',
  `is_deleted_sender`   TINYINT(1)    NOT NULL  DEFAULT 0            COMMENT 'حُذفت من قِبل المرسل',
  `is_deleted_receiver` TINYINT(1)    NOT NULL  DEFAULT 0            COMMENT 'حُذفت من قِبل المستلم',
  PRIMARY KEY (`id`),
  KEY `idx_msg_sender`       (`sender_id`),
  KEY `idx_msg_receiver`     (`receiver_id`),
  KEY `idx_msg_company`      (`company_id`),
  KEY `idx_msg_read`         (`is_read`),
  KEY `idx_msg_created`      (`created_at`),
  KEY `idx_msg_conversation` (`sender_id`, `receiver_id`, `company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='الرسائل الداخلية بين مستخدمي الشركة';

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
