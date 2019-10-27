-- --------------------------------------------------------
-- Host:                         192.168.10.112
-- Server version:               10.1.41-MariaDB-0ubuntu0.18.04.1 - Ubuntu 18.04
-- Server OS:                    debian-linux-gnu
-- HeidiSQL Version:             10.2.0.5599
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;


-- Dumping database structure for testdb
DROP DATABASE IF EXISTS `testdb`;
CREATE DATABASE IF NOT EXISTS `testdb` /*!40100 DEFAULT CHARACTER SET utf8mb4 */;
USE `testdb`;

-- Dumping structure for table testdb.Category
DROP TABLE IF EXISTS `Category`;
CREATE TABLE IF NOT EXISTS `Category` (
  `categoryId` decimal(9,0) NOT NULL,
  `categoryName` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleteFlag` decimal(1,0) NOT NULL DEFAULT '0' COMMENT '0:valid 1:logical delete',
  `created_at` DATETIME  DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`categoryId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Owner of Products';

-- Dumping data for table testdb.Category: ~2 rows (approximately)
/*!40000 ALTER TABLE `Category` DISABLE KEYS */;
REPLACE INTO `Category` (`categoryId`, `categoryName`, `deleteFlag`) VALUES
	(1, 'category A', 0),
	(2, 'category B', 0);
/*!40000 ALTER TABLE `Category` ENABLE KEYS */;

-- Dumping structure for table testdb.Product
DROP TABLE IF EXISTS `Product`;
CREATE TABLE IF NOT EXISTS `Product` (
  `productId` decimal(15,0) NOT NULL,
  `categoryId` decimal(9,0) NOT NULL COMMENT 'belongs to Category',
  `productCode` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'barcode tag',
  `productName` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(8,2) DEFAULT NULL,
  `cost` decimal(14,5) DEFAULT NULL,
  `deleteFlag` decimal(1,0) NOT NULL DEFAULT '0' COMMENT '0:valid 1:logical delete',
  `created_at` DATETIME  DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`productId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Products of Category';

-- Dumping data for table testdb.Product: ~1 rows (approximately)
/*!40000 ALTER TABLE `Product` DISABLE KEYS */;
REPLACE INTO `Product` (`productId`, `categoryId`, `productCode`, `productName`, `price`, `cost`, `deleteFlag`) VALUES
	(100, 1, '000010100', 'product 1', NULL, NULL, 0);
/*!40000 ALTER TABLE `Product` ENABLE KEYS */;

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
