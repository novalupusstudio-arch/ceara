
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

/*!40000 DROP DATABASE IF EXISTS `ceara`*/;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `ceara` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `ceara`;
DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `operation` varchar(120) NOT NULL,
  `entity` varchar(120) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `audit_log` WRITE;
/*!40000 ALTER TABLE `audit_log` DISABLE KEYS */;
INSERT INTO `audit_log` VALUES (1,1,'PROCESSING_CREATE','processing_lots',1,NULL,'Acceptat','2026-06-15 17:47:36'),(2,1,'PURCHASE_CREATE','purchase_lots',1,NULL,'Achizitie','2026-06-15 17:47:44'),(3,1,'STORE_UPDATE','stores',1,NULL,'BC','2026-06-15 18:10:06'),(4,1,'STORE_CREATE','stores',2,NULL,'CJ','2026-06-15 18:10:33'),(5,1,'USER_CREATE','users',2,NULL,'Robert','2026-06-15 18:10:52'),(6,1,'PROCESSOR_CREATE','processors',2,NULL,'Procesator Test PJ','2026-06-15 18:18:26'),(7,1,'PROCESSING_CREATE','processing_lots',2,NULL,'Acceptat','2026-06-15 18:23:12'),(8,1,'PROCESSOR_UPDATE','processors',1,NULL,'Boca','2026-06-15 18:24:24'),(9,1,'PROCESSING_SEND_FACTORY','processing_lots',2,'Acceptat','Predat Fabricii','2026-06-15 18:33:51'),(10,1,'PROCESSING_CREATE','processing_lots',3,NULL,'In Validare','2026-06-15 18:36:55'),(11,1,'PROCESSING_CREATE','processing_lots',4,NULL,'In Validare','2026-06-15 18:54:34'),(12,1,'PROCESSING_CREATE','processing_lots',5,NULL,'Acceptat','2026-06-15 19:01:14'),(13,1,'PROCESSING_CREATE','processing_lots',6,NULL,'Acceptat','2026-06-15 19:04:09'),(14,1,'PROCESSING_CREATE','processing_lots',7,NULL,'Acceptat','2026-06-15 19:05:41'),(15,1,'PROCESSING_ACCEPT','processing_lots',4,'In Validare','Acceptat','2026-06-15 19:36:16'),(16,1,'PROCESSING_REJECT','processing_lots',3,'In Validare','Respins','2026-06-15 20:07:10'),(17,1,'PROCESSING_RETURN','processing_lots',3,'Respins','Returnat','2026-06-15 20:07:18'),(18,1,'PROCESSING_CREATE','processing_lots',8,NULL,'In Validare','2026-06-15 20:11:56'),(19,1,'FACTORY_BATCH_CREATE','factory_batches',1,NULL,'FAB-20260615-FFCB1B','2026-06-15 20:34:40'),(20,1,'PROCESSING_ACCEPT','processing_lots',8,'In Validare','Acceptat','2026-06-15 20:35:44'),(21,1,'FACTORY_BATCH_CREATE','factory_batches',2,NULL,'FAB-20260615-2AAABC','2026-06-15 20:36:46');
/*!40000 ALTER TABLE `audit_log` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_type` enum('PF','PJ') NOT NULL DEFAULT 'PF',
  `name` varchar(160) NOT NULL,
  `phone` varchar(80) NOT NULL DEFAULT '',
  `address` varchar(255) NOT NULL DEFAULT '',
  `cui` varchar(40) NOT NULL DEFAULT '',
  `representative` varchar(160) NOT NULL DEFAULT '',
  `known_customer` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (1,'PF','Client Test','0700000000','','','',1,'2026-06-15 17:47:36'),(2,'PF','gig','1234567899','','','',1,'2026-06-15 18:23:11'),(3,'PF','Robert R','0741106588','Cluj_n','','',0,'2026-06-15 18:36:55'),(4,'PF','dfg','543','345','','',1,'2026-06-15 19:01:13'),(6,'PJ','sdfgsdgf','523452345','dsfgds','3434','dfgdsg',1,'2026-06-15 19:05:39');
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `document_series`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_series` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `store_id` int(11) NOT NULL,
  `document_type` varchar(40) NOT NULL,
  `series` varchar(80) NOT NULL,
  `next_number` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_store_doc` (`store_id`,`document_type`),
  CONSTRAINT `document_series_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `document_series` WRITE;
/*!40000 ALTER TABLE `document_series` DISABLE KEYS */;
INSERT INTO `document_series` VALUES (1,1,'PV-CUST','PV-CUST-GEST1',7),(2,1,'FACT','FACT-GEST1',7),(3,1,'BON','BON-GEST1',2),(4,1,'PV-FAG','PV-FAG-GEST1',6),(5,1,'PV-RET','PV-RET-GEST1',1),(6,1,'AVIZ','AVIZ-GEST1',3),(7,1,'NIR','NIR-GEST1',4),(8,1,'BORD','BORD-GEST1',2),(9,2,'PV-CUST','PV-CUST-CJ',3),(10,2,'FACT','FACT-CJ',2),(11,2,'BON','BON-CJ',1),(12,2,'PV-FAG','PV-FAG-CJ',2),(13,2,'PV-RET','PV-RET-CJ',2),(14,2,'AVIZ','AVIZ-CJ',2),(15,2,'NIR','NIR-CJ',2),(16,2,'BORD','BORD-CJ',1);
/*!40000 ALTER TABLE `document_series` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_type` varchar(40) NOT NULL,
  `series` varchar(80) NOT NULL,
  `number` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `reference_type` varchar(60) NOT NULL,
  `reference_id` int(11) NOT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'mock',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `store_id` (`store_id`),
  CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
INSERT INTO `documents` VALUES (1,'PV-CUST','PV-CUST-GEST1',1,1,'processing_lot',1,'mock','PV predare in custodie','2026-06-15 17:47:36'),(2,'FACT','FACT-GEST1',1,1,'processing_lot',1,'mock','Factura serviciu mock','2026-06-15 17:47:36'),(3,'PV-FAG','PV-FAG-GEST1',1,1,'processing_lot',1,'mock','PV predare faguri','2026-06-15 17:47:36'),(4,'BORD','BORD-GEST1',1,1,'purchase_lot',1,'mock','Document achizitie mock','2026-06-15 17:47:44'),(5,'NIR','NIR-GEST1',1,1,'purchase_lot',1,'mock','NIR materie prima','2026-06-15 17:47:44'),(6,'PV-CUST','PV-CUST-CJ',1,2,'processing_lot',2,'mock','PV predare in custodie','2026-06-15 18:23:11'),(7,'FACT','FACT-CJ',1,2,'processing_lot',2,'mock','Factura serviciu mock','2026-06-15 18:23:11'),(8,'PV-FAG','PV-FAG-CJ',1,2,'processing_lot',2,'mock','PV predare faguri','2026-06-15 18:23:12'),(9,'AVIZ','AVIZ-CJ',1,2,'processing_lot',2,'mock','Document generat mock','2026-06-15 18:33:50'),(10,'NIR','NIR-CJ',1,2,'processing_lot',2,'mock','Document generat mock','2026-06-15 18:33:51'),(11,'PV-CUST','PV-CUST-CJ',2,2,'processing_lot',3,'mock','PV predare in custodie','2026-06-15 18:36:55'),(12,'PV-CUST','PV-CUST-GEST1',2,1,'processing_lot',4,'mock','PV predare in custodie','2026-06-15 18:54:34'),(13,'PV-CUST','PV-CUST-GEST1',3,1,'processing_lot',5,'mock','PV predare in custodie','2026-06-15 19:01:14'),(14,'FACT','FACT-GEST1',2,1,'processing_lot',5,'mock','Factura serviciu mock','2026-06-15 19:01:14'),(15,'PV-FAG','PV-FAG-GEST1',2,1,'processing_lot',5,'mock','PV predare faguri','2026-06-15 19:01:14'),(19,'PV-CUST','PV-CUST-GEST1',5,1,'processing_lot',7,'mock','PV predare in custodie','2026-06-15 19:05:40'),(20,'FACT','FACT-GEST1',4,1,'processing_lot',7,'mock','Factura serviciu mock','2026-06-15 19:05:40'),(21,'PV-FAG','PV-FAG-GEST1',4,1,'processing_lot',7,'mock','PV predare faguri','2026-06-15 19:05:40'),(22,'FACT','FACT-GEST1',5,1,'processing_lot',4,'mock','Document generat mock','2026-06-15 19:36:16'),(23,'PV-FAG','PV-FAG-GEST1',5,1,'processing_lot',4,'mock','Document generat mock','2026-06-15 19:36:16'),(24,'PV-RET','PV-RET-CJ',1,2,'processing_lot',3,'mock','Document generat mock','2026-06-15 20:07:18'),(25,'PV-CUST','PV-CUST-GEST1',6,1,'processing_lot',8,'mock','PV predare in custodie','2026-06-15 20:11:55'),(26,'AVIZ','AVIZ-GEST1',1,1,'factory_batch',1,'mock','Aviz catre procesator','2026-06-15 20:34:40'),(27,'NIR','NIR-GEST1',2,1,'factory_batch',1,'mock','NIR aviz procesator','2026-06-15 20:34:40'),(28,'FACT','FACT-GEST1',6,1,'processing_lot',8,'mock','Document generat mock','2026-06-15 20:35:44'),(29,'BON','BON-GEST1',1,1,'processing_lot',8,'mock','Document generat mock','2026-06-15 20:35:44'),(30,'AVIZ','AVIZ-GEST1',2,1,'factory_batch',2,'mock','Aviz catre procesator','2026-06-15 20:36:46'),(31,'NIR','NIR-GEST1',3,1,'factory_batch',2,'mock','NIR aviz procesator','2026-06-15 20:36:46');
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `factory_batch_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory_batch_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) NOT NULL,
  `processing_lot_id` int(11) NOT NULL,
  `wax_g` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `batch_id` (`batch_id`),
  KEY `processing_lot_id` (`processing_lot_id`),
  CONSTRAINT `factory_batch_items_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `factory_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `factory_batch_items_ibfk_2` FOREIGN KEY (`processing_lot_id`) REFERENCES `processing_lots` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `factory_batch_items` WRITE;
/*!40000 ALTER TABLE `factory_batch_items` DISABLE KEYS */;
INSERT INTO `factory_batch_items` VALUES (1,1,8,15000,'2026-06-15 20:34:40'),(2,2,7,4000,'2026-06-15 20:36:45');
/*!40000 ALTER TABLE `factory_batch_items` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `factory_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factory_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_number` varchar(40) NOT NULL,
  `processor_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `wax_g` int(11) NOT NULL,
  `foundation_g` int(11) NOT NULL,
  `processing_cost_cents` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `batch_number` (`batch_number`),
  KEY `processor_id` (`processor_id`),
  KEY `store_id` (`store_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `factory_batches_ibfk_1` FOREIGN KEY (`processor_id`) REFERENCES `processors` (`id`),
  CONSTRAINT `factory_batches_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
  CONSTRAINT `factory_batches_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `factory_batches` WRITE;
/*!40000 ALTER TABLE `factory_batches` DISABLE KEYS */;
INSERT INTO `factory_batches` VALUES (1,'FAB-20260615-FFCB1B',1,1,15000,14700,12000,1,'2026-06-15 20:34:40'),(2,'FAB-20260615-2AAABC',1,1,4000,3920,3200,1,'2026-06-15 20:36:45');
/*!40000 ALTER TABLE `factory_batches` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `inventory_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `movement_type` varchar(80) NOT NULL,
  `qty_g` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `reference_type` varchar(60) NOT NULL,
  `reference_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `store_id` (`store_id`),
  CONSTRAINT `inventory_transactions_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `inventory_transactions` WRITE;
/*!40000 ALTER TABLE `inventory_transactions` DISABLE KEYS */;
INSERT INTO `inventory_transactions` VALUES (1,'wax_custody',12345,1,'processing_lot',1,'Ceara client in custodie','2026-06-15 17:47:36'),(2,'wax_owned',8750,1,'purchase_lot',1,'Ceara cumparata','2026-06-15 17:47:44'),(3,'wax_custody',15000,2,'processing_lot',2,'Ceara client in custodie','2026-06-15 18:23:11'),(4,'wax_custody',15000,2,'processing_lot',3,'Ceara client in custodie','2026-06-15 18:36:55'),(5,'wax_custody',15000,1,'processing_lot',4,'Ceara client in custodie','2026-06-15 18:54:34'),(6,'wax_custody',4000,1,'processing_lot',5,'Ceara client in custodie','2026-06-15 19:01:14'),(8,'wax_custody',4000,1,'processing_lot',7,'Ceara client in custodie','2026-06-15 19:05:39'),(9,'wax_custody',25000,1,'processing_lot',8,'Ceara client in custodie','2026-06-15 20:11:54'),(10,'wax_custody',-15000,1,'factory_batch',1,'Ceara trimisa la procesator','2026-06-15 20:34:40'),(11,'foundation_operational',14700,1,'factory_batch',1,'Faguri primiti de la procesator','2026-06-15 20:34:40'),(12,'wax_custody',-4000,1,'factory_batch',2,'Ceara trimisa la procesator','2026-06-15 20:36:46'),(13,'foundation_operational',3920,1,'factory_batch',2,'Faguri primiti de la procesator','2026-06-15 20:36:46');
/*!40000 ALTER TABLE `inventory_transactions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `code` varchar(80) NOT NULL,
  `label` varchar(160) NOT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES ('AUDIT_VIEW','Vizualizare audit'),('PROCESSING_ACCEPT','Acceptare procesare'),('PROCESSING_CREATE','Creare procesare'),('PROCESSING_REJECT','Respingere procesare'),('PROCESSOR_MANAGE','Administrare procesatori'),('PURCHASE_CREATE','Creare achizitii'),('REPORT_VIEW','Vizualizare rapoarte'),('STORE_MANAGE','Administrare gestiuni'),('USER_CREATE','Creare utilizatori'),('USER_EDIT','Editare utilizatori'),('USER_RESET_PASSWORD','Resetare parole');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `processing_lot_status_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `processing_lot_status_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lot_id` int(11) NOT NULL,
  `status` enum('In Validare','Acceptat','Predat Fabricii','Respins','Returnat') NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `lot_id` (`lot_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `processing_lot_status_events_ibfk_1` FOREIGN KEY (`lot_id`) REFERENCES `processing_lots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `processing_lot_status_events_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `processing_lot_status_events` WRITE;
/*!40000 ALTER TABLE `processing_lot_status_events` DISABLE KEYS */;
INSERT INTO `processing_lot_status_events` VALUES (1,3,'Respins',1,'2026-06-15 20:07:10'),(2,3,'Returnat',1,'2026-06-15 20:07:17'),(3,8,'In Validare',1,'2026-06-15 20:11:55'),(4,8,'Acceptat',1,'2026-06-15 20:35:43'),(5,7,'Predat Fabricii',1,'2026-06-15 20:36:46');
/*!40000 ALTER TABLE `processing_lot_status_events` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `processing_lots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `processing_lots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lot_number` varchar(40) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `status` enum('In Validare','Acceptat','Predat Fabricii','Respins','Returnat') NOT NULL,
  `gross_g` int(11) NOT NULL,
  `factory_sent_g` int(11) NOT NULL DEFAULT 0,
  `processing_price_cents` int(11) NOT NULL DEFAULT 0,
  `shrinkage_pct` decimal(6,3) NOT NULL DEFAULT 0.000,
  `foundation_g` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `processor_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `lot_number` (`lot_number`),
  KEY `customer_id` (`customer_id`),
  KEY `store_id` (`store_id`),
  KEY `processor_id` (`processor_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `processing_lots_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `processing_lots_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
  CONSTRAINT `processing_lots_ibfk_3` FOREIGN KEY (`processor_id`) REFERENCES `processors` (`id`),
  CONSTRAINT `processing_lots_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `processing_lots` WRITE;
/*!40000 ALTER TABLE `processing_lots` DISABLE KEYS */;
INSERT INTO `processing_lots` VALUES (1,'PROC-20260615-21B276',1,'Acceptat',12345,0,0,0.000,12345,1,1,1,'2026-06-15 17:47:36'),(2,'PROC-20260615-A2A38D',2,'Predat Fabricii',15000,0,0,0.000,15000,2,1,1,'2026-06-15 18:23:11'),(3,'PROC-20260615-7A9BFC',3,'Returnat',15000,0,800,2.000,14700,2,1,1,'2026-06-15 18:36:55'),(4,'PROC-20260615-59BEA9',3,'Acceptat',15000,0,800,2.000,14700,1,1,1,'2026-06-15 18:54:34'),(5,'PROC-20260615-6703D7',4,'Acceptat',4000,0,800,2.000,3920,1,1,1,'2026-06-15 19:01:14'),(7,'PROC-20260615-3D0079',6,'Predat Fabricii',4000,4000,800,2.000,3920,1,1,1,'2026-06-15 19:05:39'),(8,'PROC-20260615-FBC632',3,'Acceptat',25000,15000,800,2.000,24500,1,1,1,'2026-06-15 20:11:54');
/*!40000 ALTER TABLE `processing_lots` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `processors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `processors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(160) NOT NULL,
  `cui` varchar(40) NOT NULL DEFAULT '',
  `address` varchar(255) NOT NULL DEFAULT '',
  `contact` varchar(160) NOT NULL DEFAULT '',
  `processing_price_cents` int(11) NOT NULL DEFAULT 0,
  `exchange_shrinkage_pct` decimal(6,3) NOT NULL DEFAULT 0.000,
  `purchase_shrinkage_pct` decimal(6,3) NOT NULL DEFAULT 0.000,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `processors` WRITE;
/*!40000 ALTER TABLE `processors` DISABLE KEYS */;
INSERT INTO `processors` VALUES (1,'Boca','Ro123456','bistrita','',800,2.000,0.000,'2026-06-15 17:47:15'),(2,'Procesator Test PJ','RO987654','Strada Test 1','',1250,3.500,0.000,'2026-06-15 18:18:26');
/*!40000 ALTER TABLE `processors` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `purchase_lots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_lots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lot_number` varchar(40) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `supplier_type` enum('PF','Producator agricol','PFA/SRL') NOT NULL,
  `status` enum('Achizitie','Predat Procesator','Receptionat Faguri','Inchis') NOT NULL,
  `gross_g` int(11) NOT NULL,
  `shrinkage_pct` decimal(6,3) NOT NULL DEFAULT 0.000,
  `foundation_g` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `processor_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `lot_number` (`lot_number`),
  KEY `supplier_id` (`supplier_id`),
  KEY `store_id` (`store_id`),
  KEY `processor_id` (`processor_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `purchase_lots_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `purchase_lots_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
  CONSTRAINT `purchase_lots_ibfk_3` FOREIGN KEY (`processor_id`) REFERENCES `processors` (`id`),
  CONSTRAINT `purchase_lots_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `purchase_lots` WRITE;
/*!40000 ALTER TABLE `purchase_lots` DISABLE KEYS */;
INSERT INTO `purchase_lots` VALUES (1,'ACH-20260615-D686F8',1,'PF','Achizitie',8750,0.000,8750,1,1,1,'2026-06-15 17:47:44');
/*!40000 ALTER TABLE `purchase_lots` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `role_name` enum('admin','operator') NOT NULL,
  `permission_code` varchar(80) NOT NULL,
  `allowed` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`role_name`,`permission_code`),
  KEY `permission_code` (`permission_code`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`permission_code`) REFERENCES `permissions` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES ('admin','AUDIT_VIEW',1),('admin','PROCESSING_ACCEPT',1),('admin','PROCESSING_CREATE',1),('admin','PROCESSING_REJECT',1),('admin','PROCESSOR_MANAGE',1),('admin','PURCHASE_CREATE',1),('admin','REPORT_VIEW',1),('admin','STORE_MANAGE',1),('admin','USER_CREATE',1),('admin','USER_EDIT',1),('admin','USER_RESET_PASSWORD',1),('operator','AUDIT_VIEW',0),('operator','PROCESSING_ACCEPT',0),('operator','PROCESSING_CREATE',1),('operator','PROCESSING_REJECT',0),('operator','PROCESSOR_MANAGE',0),('operator','PURCHASE_CREATE',1),('operator','REPORT_VIEW',1),('operator','STORE_MANAGE',0),('operator','USER_CREATE',0),('operator','USER_EDIT',0),('operator','USER_RESET_PASSWORD',0);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `stores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL,
  `name` varchar(160) NOT NULL,
  `address` varchar(255) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `stores` WRITE;
/*!40000 ALTER TABLE `stores` DISABLE KEYS */;
INSERT INTO `stores` VALUES (1,'BC','Onesti','str. bucium 13 onesti, jud bacau','2026-06-15 17:47:15'),(2,'CJ','Cluj','Traian Vuia 95a, Cluj-Napoca, jud cluj','2026-06-15 18:10:33');
/*!40000 ALTER TABLE `stores` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(160) NOT NULL,
  `supplier_type` enum('PF','Producator agricol','PFA/SRL') NOT NULL,
  `cui` varchar(40) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
INSERT INTO `suppliers` VALUES (1,'Furnizor Test','PF','RO123','2026-06-15 17:47:44');
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `user_stores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_stores` (
  `user_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  PRIMARY KEY (`user_id`,`store_id`),
  KEY `store_id` (`store_id`),
  CONSTRAINT `user_stores_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_stores_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `user_stores` WRITE;
/*!40000 ALTER TABLE `user_stores` DISABLE KEYS */;
INSERT INTO `user_stores` VALUES (1,1),(2,2);
/*!40000 ALTER TABLE `user_stores` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(80) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(160) NOT NULL,
  `role` enum('admin','operator') NOT NULL DEFAULT 'operator',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$10$bZPJbYhCDq0xK/XZvgu5UuZFY1GfpTGuRnOA.uRDuHm8D0KPpn9Jm','Administrator','admin',1,'2026-06-15 17:47:15'),(2,'Robert','$2y$10$0BJCa5rfmkKMWHaJIsUdZOApb9Rp.rDpJkmwNb7NFyuAaFQTrd6Oy','Robert Romascu','operator',1,'2026-06-15 18:10:52');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

