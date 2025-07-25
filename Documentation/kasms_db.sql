-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jul 09, 2025 at 01:27 PM
-- Server version: 8.0.41
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kasms_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_leave`
--

DROP TABLE IF EXISTS `academic_leave`;
CREATE TABLE IF NOT EXISTS `academic_leave` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `reason` text NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student` (`student_id`,`semester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clearance`
--

DROP TABLE IF EXISTS `clearance`;
CREATE TABLE IF NOT EXISTS `clearance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL,
  `reason` text,
  `department_status` enum('Pending','Cleared','Rejected') DEFAULT 'Pending',
  `finance_status` enum('Pending','Cleared','Rejected') DEFAULT 'Pending',
  `registrar_status` enum('Pending','Cleared','Rejected') DEFAULT 'Pending',
  `request_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
CREATE TABLE IF NOT EXISTS `courses` (
  `course_id` int NOT NULL AUTO_INCREMENT,
  `course` varchar(100) NOT NULL,
  `department` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`course_id`),
  KEY `idx_course_name` (`course`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`course_id`, `course`, `department`, `created_at`, `updated_at`) VALUES
(1, 'D.Clinical Medicine', 'D.Clinical Medicine', '2025-06-19 14:06:52', '2025-07-07 09:46:58'),
(2, 'D.Nursing', 'D.Nursing', '2025-06-19 14:06:52', '2025-07-07 09:47:25');

-- --------------------------------------------------------

--
-- Table structure for table `coursework`
--

DROP TABLE IF EXISTS `coursework`;
CREATE TABLE IF NOT EXISTS `coursework` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL,
  `unit_code` varchar(20) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `cat1` int DEFAULT NULL,
  `version` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student_unit` (`student_id`,`unit_code`,`semester`),
  KEY `unit_code` (`unit_code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `coursework`
--

INSERT INTO `coursework` (`id`, `student_id`, `unit_code`, `semester`, `cat1`, `version`, `created_at`, `updated_at`) VALUES
(4, 'ADM1002', 'DCM1_MED101', '2025-S1', 8, 0, '2025-07-09 06:44:34', '2025-07-09 06:44:34');

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

DROP TABLE IF EXISTS `enrollments`;
CREATE TABLE IF NOT EXISTS `enrollments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL,
  `unit_code` varchar(20) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `enrollment_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student_unit` (`student_id`,`unit_code`,`semester`),
  KEY `unit_code` (`unit_code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exam_audit`
--

DROP TABLE IF EXISTS `exam_audit`;
CREATE TABLE IF NOT EXISTS `exam_audit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL,
  `unit_code` varchar(20) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `audit_type` enum('Result','Appeal','Correction') NOT NULL,
  `details` json NOT NULL,
  `status` enum('Pending','Resolved','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student_unit` (`student_id`,`unit_code`,`semester`),
  KEY `unit_code` (`unit_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exam_card`
--

DROP TABLE IF EXISTS `exam_card`;
CREATE TABLE IF NOT EXISTS `exam_card` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `eligible` tinyint(1) NOT NULL DEFAULT '0',
  `issuance_date` date DEFAULT NULL,
  `units` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student_semester` (`student_id`,`semester`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exam_results`
--

DROP TABLE IF EXISTS `exam_results`;
CREATE TABLE IF NOT EXISTS `exam_results` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL,
  `unit_code` varchar(20) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `final_exam` int DEFAULT NULL,
  `final_grade` varchar(2) DEFAULT NULL,
  `version` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student_unit` (`student_id`,`unit_code`,`semester`),
  KEY `unit_code` (`unit_code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `exam_results`
--

INSERT INTO `exam_results` (`id`, `student_id`, `unit_code`, `semester`, `final_exam`, `final_grade`, `version`, `created_at`, `updated_at`) VALUES
(4, 'ADM1002', 'DCM1_MED101', '2025-S1', 35, NULL, 0, '2025-07-09 06:44:34', '2025-07-09 06:44:34');

-- --------------------------------------------------------

--
-- Table structure for table `fees`
--

DROP TABLE IF EXISTS `fees`;
CREATE TABLE IF NOT EXISTS `fees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `amount_owed` decimal(10,2) NOT NULL DEFAULT '0.00',
  `amount_paid` decimal(10,2) NOT NULL DEFAULT '0.00',
  `balance` decimal(10,2) GENERATED ALWAYS AS ((`amount_owed` - `amount_paid`)) STORED,
  `version` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student_semester` (`student_id`,`semester`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `fees`
--

INSERT INTO `fees` (`id`, `student_id`, `semester`, `amount_owed`, `amount_paid`, `version`, `created_at`, `updated_at`) VALUES
(9, 'ADM1001', '2025-S1', -150000.00, 30000.00, 0, '2025-07-03 08:56:15', '2025-07-09 12:29:11'),
(10, 'ADM1002', '2025-S1', -150000.00, -250000.00, 0, '2025-07-03 13:32:46', '2025-07-09 08:49:02');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `payment_id` int NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` datetime NOT NULL,
  `receipt_number` varchar(50) NOT NULL,
  `service_type` varchar(100) NOT NULL,
  `payment_method` varchar(50) DEFAULT 'Cash',
  `semester` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  UNIQUE KEY `receipt_number` (`receipt_number`),
  KEY `idx_student_semester` (`student_id`,`semester`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `student_id`, `amount`, `payment_date`, `receipt_number`, `service_type`, `payment_method`, `semester`, `created_at`, `updated_at`) VALUES
(6, 'ADM1001', 40000.00, '2025-07-09 12:29:11', 'REC17520641516590', 'Fee Payment', 'Cash', '2025-S1', '2025-07-09 12:29:11', '2025-07-09 12:29:11');

--
-- Triggers `payments`
--
DROP TRIGGER IF EXISTS `update_student_finance`;
DELIMITER $$
CREATE TRIGGER `update_student_finance` AFTER INSERT ON `payments` FOR EACH ROW BEGIN
    UPDATE students
    SET total_paid = total_paid + NEW.amount,
        total_billed = total_billed + (SELECT COALESCE(SUM(amount_owed), 0) FROM fees WHERE student_id = NEW.student_id AND semester = NEW.semester)
    WHERE student_id = NEW.student_id;

    UPDATE fees
    SET amount_paid = amount_paid + NEW.amount
    WHERE student_id = NEW.student_id AND semester = NEW.semester;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `registrations`
--

DROP TABLE IF EXISTS `registrations`;
CREATE TABLE IF NOT EXISTS `registrations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `stage` varchar(50) NOT NULL,
  `unit_code` varchar(20) DEFAULT NULL,
  `unit_name` varchar(100) DEFAULT NULL,
  `registration_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `course` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_student_semester` (`student_id`,`semester`),
  KEY `unit_code` (`unit_code`)
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `registrations`
--

INSERT INTO `registrations` (`id`, `student_id`, `semester`, `stage`, `unit_code`, `unit_name`, `registration_date`, `created_at`, `updated_at`, `course`) VALUES
(46, 'ADM1001', '2025-S1', 'Year 1 Semester 1', 'DN1_NUR101', 'Anatomy and Physiology I', '2025-07-03', '2025-07-03 08:56:15', '2025-07-03 08:56:15', 'D.Nursing'),
(47, 'ADM1001', '2025-S1', 'Year 1 Semester 1', 'DN1_NUR102', 'Microbiology and Parasitology', '2025-07-03', '2025-07-03 08:56:15', '2025-07-03 08:56:15', 'D.Nursing'),
(48, 'ADM1001', '2025-S1', 'Year 1 Semester 1', 'DN1_NUR103', 'Biochemistry', '2025-07-03', '2025-07-03 08:56:15', '2025-07-03 08:56:15', 'D.Nursing'),
(49, 'ADM1001', '2025-S1', 'Year 1 Semester 1', 'DN1_NUR104', 'Basic Nursing Skills I', '2025-07-03', '2025-07-03 08:56:15', '2025-07-03 08:56:15', 'D.Nursing'),
(50, 'ADM1001', '2025-S1', 'Year 1 Semester 1', 'DN1_NUR105', 'Communication Skills', '2025-07-03', '2025-07-03 08:56:15', '2025-07-03 08:56:15', 'D.Nursing'),
(51, 'ADM1001', '2025-S1', 'Year 1 Semester 1', 'DN1_NUR106', 'First Aid and BLS', '2025-07-03', '2025-07-03 08:56:15', '2025-07-03 08:56:15', 'D.Nursing'),
(52, 'ADM1002', '2025-S1', 'Year 1 Semester 1', 'DCM1_MED101', 'Anatomy', '2025-07-03', '2025-07-03 13:32:46', '2025-07-03 13:32:46', 'D.Clinical Medicine'),
(53, 'ADM1002', '2025-S1', 'Year 1 Semester 1', 'DCM1_MED102', 'Physiology', '2025-07-03', '2025-07-03 13:32:46', '2025-07-03 13:32:46', 'D.Clinical Medicine'),
(54, 'ADM1002', '2025-S1', 'Year 1 Semester 1', 'DCM1_MED103', 'Biochemistry', '2025-07-03', '2025-07-03 13:32:46', '2025-07-03 13:32:46', 'D.Clinical Medicine'),
(55, 'ADM1002', '2025-S1', 'Year 1 Semester 1', 'DCM1_MED104', 'Microbiology', '2025-07-03', '2025-07-03 13:32:46', '2025-07-03 13:32:46', 'D.Clinical Medicine'),
(56, 'ADM1002', '2025-S1', 'Year 1 Semester 1', 'DCM1_MED105', 'Parasitology and Entomology', '2025-07-03', '2025-07-03 13:32:46', '2025-07-03 13:32:46', 'D.Clinical Medicine'),
(57, 'ADM1002', '2025-S1', 'Year 1 Semester 1', 'DCM1_MED106', 'Pharmacology I', '2025-07-03', '2025-07-03 13:32:46', '2025-07-03 13:32:46', 'D.Clinical Medicine'),
(58, 'ADM1002', '2025-S1', 'Year 1 Semester 1', 'DCM1_MED107', 'Community Health I', '2025-07-03', '2025-07-03 13:32:46', '2025-07-03 13:32:46', 'D.Clinical Medicine');

--
-- Triggers `registrations`
--
DROP TRIGGER IF EXISTS `update_fees_on_registration`;
DELIMITER $$
CREATE TRIGGER `update_fees_on_registration` AFTER INSERT ON `registrations` FOR EACH ROW BEGIN
    INSERT INTO fees (student_id, semester, amount_owed, amount_paid, version)
    SELECT NEW.student_id, NEW.semester, 50000.00, 0.00, 0
    WHERE NOT EXISTS (
        SELECT 1 FROM fees WHERE student_id = NEW.student_id AND semester = NEW.semester
    );

    UPDATE students
    SET total_billed = total_billed + 50000.00
    WHERE student_id = NEW.student_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` int DEFAULT NULL,
  `data` json DEFAULT NULL,
  `last_accessed` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `data`, `last_accessed`, `created_at`) VALUES
('35e47ta648iiprh2mibis5o2pf', 41, '\"user_id|i:41;role|s:7:\\\"student\\\";student_id|s:7:\\\"ADM1001\\\";\"', '2025-07-09 08:50:09', '2025-07-09 08:50:09'),
('3hn0ekiq1lu65eddv3b85d8vd8', 4, '\"user_id|i:4;role|s:7:\\\"finance\\\";student_id|N;\"', '2025-07-09 13:10:04', '2025-07-09 13:10:04'),
('5vscl839vce22qtkl45dmaj803', 3, '\"user_id|i:3;role|s:3:\\\"hod\\\";student_id|N;\"', '2025-07-09 09:50:50', '2025-07-09 09:50:50'),
('j9bnhl07moko6gu6a7b56slbse', NULL, '{}', '2025-07-09 09:48:05', '2025-07-09 09:48:05'),
('oea3di58ar7hfgttogvgi25782', 43, '\"user_id|i:43;role|s:10:\\\"instructor\\\";student_id|N;\"', '2025-07-09 09:12:58', '2025-07-09 09:12:58'),
('oqc6ba2n0o9hqjqt1gmahievra', NULL, '{}', '2025-07-09 08:51:46', '2025-07-09 08:51:46');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
CREATE TABLE IF NOT EXISTS `students` (
  `student_id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `year_of_study` int NOT NULL,
  `id_number` varchar(50) NOT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `date_of_birth` date NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `email_address` varchar(255) NOT NULL,
  `postal_address` varchar(255) NOT NULL,
  `total_billed` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_paid` decimal(10,2) NOT NULL DEFAULT '0.00',
  `balance` decimal(10,2) GENERATED ALWAYS AS ((`total_billed` - `total_paid`)) STORED,
  `confirmed` tinyint(1) NOT NULL DEFAULT '0',
  `course_id` int DEFAULT NULL,
  `enrollment_year` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `disability` enum('Yes','No') DEFAULT 'No',
  `course` varchar(50) NOT NULL,
  `base_fee` decimal(12,2) DEFAULT '100000.00',
  `current_semester_fee` decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `id_number` (`id_number`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_email_address` (`email_address`),
  KEY `course_id` (`course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `name`, `year_of_study`, `id_number`, `gender`, `date_of_birth`, `phone_number`, `email_address`, `postal_address`, `total_billed`, `total_paid`, `confirmed`, `course_id`, `enrollment_year`, `created_at`, `updated_at`, `disability`, `course`, `base_fee`, `current_semester_fee`) VALUES
('ADM1001', 'CATE WANJIKU', 1, '11443274', 'Female', '2004-12-02', '714432744', 'cate@gmail.com', '1443', -50000.00, 130000.00, 1, NULL, 2025, '2025-07-02 07:31:20', '2025-07-09 12:29:11', 'No', 'D.Nursing', 100000.00, NULL),
('ADM1002', 'ESTHER KWAMBOKA', 1, '21414835', 'Female', '2000-02-14', '114148354', 'esther@gmail.com', '1414', 100000.00, 50000.00, 1, NULL, 2025, '2025-07-02 08:34:12', '2025-07-09 08:49:02', 'No', 'D.Clinical Medicine', 100000.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_survey`
--

DROP TABLE IF EXISTS `student_survey`;
CREATE TABLE IF NOT EXISTS `student_survey` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `response` json NOT NULL,
  `submission_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student_semester` (`student_id`,`semester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

DROP TABLE IF EXISTS `units`;
CREATE TABLE IF NOT EXISTS `units` (
  `unit_code` varchar(20) NOT NULL,
  `unit_name` varchar(100) NOT NULL,
  `course_id` int DEFAULT NULL,
  `instructor_id` int DEFAULT NULL,
  `credits` int NOT NULL DEFAULT '3',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `course` varchar(255) NOT NULL,
  PRIMARY KEY (`unit_code`),
  KEY `idx_instructor_id` (`instructor_id`),
  KEY `course_id` (`course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`unit_code`, `unit_name`, `course_id`, `instructor_id`, `credits`, `created_at`, `updated_at`, `course`) VALUES
('DCM1_MED101', 'Anatomy', 1, 43, 4, '2025-06-23 15:12:36', '2025-07-09 06:36:13', 'D.Clinical Medicine'),
('DCM1_MED102', 'Physiology', 1, NULL, 3, '2025-06-23 15:12:36', '2025-07-02 09:53:41', 'D.Clinical Medicine'),
('DCM1_MED103', 'Biochemistry', 1, NULL, 3, '2025-06-23 15:12:36', '2025-07-02 09:53:41', 'D.Clinical Medicine'),
('DCM1_MED104', 'Microbiology', 1, NULL, 3, '2025-06-23 15:12:36', '2025-07-02 09:53:41', 'D.Clinical Medicine'),
('DCM1_MED105', 'Parasitology and Entomology', 1, NULL, 3, '2025-06-23 15:12:36', '2025-07-02 09:53:41', 'D.Clinical Medicine'),
('DCM1_MED106', 'Pharmacology I', 1, 43, 3, '2025-06-23 15:12:36', '2025-07-09 06:36:13', 'D.Clinical Medicine'),
('DCM1_MED107', 'Community Health I', 1, NULL, 3, '2025-06-23 15:12:36', '2025-07-02 09:53:41', 'D.Clinical Medicine'),
('DCM2_MED201', 'General Medicine I', 1, NULL, 4, '2025-06-23 15:12:36', '2025-07-02 09:53:41', 'D.Clinical Medicine'),
('DCM2_MED202', 'Surgery I', 1, NULL, 4, '2025-06-23 15:12:36', '2025-07-02 09:53:41', 'D.Clinical Medicine'),
('DCM2_MED203', 'Pediatrics I', 1, NULL, 3, '2025-06-23 15:12:36', '2025-07-02 09:53:41', 'D.Clinical Medicine'),
('DCM2_MED204', 'Obstetrics & Gynecology I', 1, NULL, 3, '2025-06-23 15:12:36', '2025-07-02 09:53:41', 'D.Clinical Medicine'),
('DCM2_MED205', 'Psychiatry I', 1, NULL, 3, '2025-06-23 15:12:36', '2025-07-02 09:53:41', 'D.Clinical Medicine'),
('DCM2_MED206', 'Radiology', 1, NULL, 2, '2025-06-23 15:12:36', '2025-07-02 09:53:41', 'D.Clinical Medicine'),
('DCM3_MED301', 'General Medicine II', 1, NULL, 4, '2025-06-23 15:12:36', '2025-07-02 09:53:41', 'D.Clinical Medicine'),
('DCM3_MED302', 'Surgery II', 1, NULL, 4, '2025-06-23 15:12:36', '2025-07-02 09:53:41', 'D.Clinical Medicine'),
('DCM3_MED303', 'Pediatrics II', 1, NULL, 3, '2025-06-23 15:12:36', '2025-07-02 09:53:41', 'D.Clinical Medicine'),
('DCM3_MED304', 'Obstetrics & Gynecology II', 1, NULL, 3, '2025-06-23 15:12:36', '2025-07-02 09:53:41', 'D.Clinical Medicine'),
('DCM3_MED305', 'Emergency & Critical Care', 1, NULL, 3, '2025-06-23 15:12:36', '2025-07-02 09:53:41', 'D.Clinical Medicine'),
('DCM3_MED306', 'Research Project', 1, NULL, 3, '2025-06-23 15:12:36', '2025-07-02 09:53:41', 'D.Clinical Medicine'),
('DN1_NUR101', 'Anatomy and Physiology I', 2, NULL, 4, '2025-06-23 15:12:03', '2025-07-02 09:53:41', 'D.Nursing'),
('DN1_NUR102', 'Microbiology and Parasitology', 2, NULL, 3, '2025-06-23 15:12:03', '2025-07-02 09:53:41', 'D.Nursing'),
('DN1_NUR103', 'Biochemistry', 2, NULL, 3, '2025-06-23 15:12:03', '2025-07-02 09:53:41', 'D.Nursing'),
('DN1_NUR104', 'Basic Nursing Skills I', 2, NULL, 3, '2025-06-23 15:12:03', '2025-07-02 09:53:41', 'D.Nursing'),
('DN1_NUR105', 'Communication Skills', 2, NULL, 2, '2025-06-23 15:12:03', '2025-07-02 09:53:41', 'D.Nursing'),
('DN1_NUR106', 'First Aid and BLS', 2, NULL, 2, '2025-06-23 15:12:03', '2025-07-02 09:53:41', 'D.Nursing'),
('DN1_NUR107', 'Community Health Nursing I', 2, NULL, 3, '2025-06-23 15:12:03', '2025-07-02 09:53:41', 'D.Nursing'),
('DN2_NUR201', 'Medical-Surgical Nursing I', 2, NULL, 4, '2025-06-23 15:12:03', '2025-07-02 09:53:41', 'D.Nursing'),
('DN2_NUR202', 'Pharmacology I', 2, NULL, 3, '2025-06-23 15:12:03', '2025-07-02 09:53:41', 'D.Nursing'),
('DN2_NUR203', 'Midwifery I', 2, NULL, 3, '2025-06-23 15:12:03', '2025-07-02 09:53:41', 'D.Nursing'),
('DN2_NUR204', 'Pediatric Nursing I', 2, NULL, 3, '2025-06-23 15:12:03', '2025-07-02 09:53:41', 'D.Nursing'),
('DN2_NUR205', 'Mental Health Nursing I', 2, NULL, 3, '2025-06-23 15:12:03', '2025-07-02 09:53:41', 'D.Nursing'),
('DN2_NUR206', 'Community Health Nursing II', 2, NULL, 3, '2025-06-23 15:12:03', '2025-07-02 09:53:41', 'D.Nursing'),
('DN3_NUR301', 'Medical-Surgical Nursing II', 2, NULL, 4, '2025-06-23 15:12:03', '2025-07-02 09:53:41', 'D.Nursing'),
('DN3_NUR302', 'Midwifery II', 2, NULL, 3, '2025-06-23 15:12:03', '2025-07-02 09:53:41', 'D.Nursing'),
('DN3_NUR303', 'Pediatric Nursing II', 2, NULL, 3, '2025-06-23 15:12:03', '2025-07-02 09:53:41', 'D.Nursing'),
('DN3_NUR304', 'Mental Health Nursing II', 2, NULL, 3, '2025-06-23 15:12:03', '2025-07-02 09:53:41', 'D.Nursing'),
('DN3_NUR305', 'Leadership and Management in Nursing', 2, NULL, 2, '2025-06-23 15:12:03', '2025-07-02 09:53:41', 'D.Nursing'),
('DN3_NUR306', 'Research Project', 2, NULL, 3, '2025-06-23 15:12:03', '2025-07-02 09:53:41', 'D.Nursing');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','instructor','hod','finance','registrar','admin') NOT NULL,
  `student_id` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_username` (`username`),
  KEY `student_id` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `student_id`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$h3xszxcW2tr0kflhERuTVuv.WBchJgJELkZyTey3Q1/.LGVmfDwNu', 'admin', NULL, '2025-07-05 16:56:11', '2025-07-08 13:22:53'),
(2, 'instructor', '', 'instructor', NULL, '2025-06-19 14:06:53', '2025-07-09 08:58:35'),
(3, 'H.O.D NURSING', '$2y$10$BauozWNgy0peYM3pbs9DZuvDsPXCojU/r4GT3Rh3L8k7YeqA0LHY.', 'hod', NULL, '2025-06-19 14:06:53', '2025-07-08 13:14:45'),
(4, 'FINANCE', '$2y$10$Ay1c14/1Mt.etvo2xEmXSe10.dCmiPZ4lLtLJEQzkG/tkqs2Xxbue', 'finance', NULL, '2025-06-19 14:06:53', '2025-07-09 08:14:57'),
(13, 'registrar', '$2y$10$Bps4sdBdyAWBhp1SrQ4nz.UTja3MR2QKRKA8IGDNHJOtPlnAT.uou', 'registrar', NULL, '2025-06-19 14:06:53', '2025-07-08 13:07:15'),
(41, 'ADM1001', '$2y$10$7DoJZPZea9LdtDBhX302t.o0K86Sbg3C1QP4QmHj2Oo/7cLlIctZC', 'student', 'ADM1001', '2025-07-02 07:31:20', '2025-07-03 11:21:44'),
(42, 'ADM1002', '$2y$10$sv8D6JmCcwe6Hlk6J7yLWeK7MrLp8tvoyqPQmwQUxOGLQvrJ9ErMC', 'student', 'ADM1002', '2025-07-02 08:34:12', '2025-07-07 14:19:58'),
(43, 'pha1', '$2y$10$k23fZo6Fs.9WjmPr1EJzb.w8/QrFnqTN3R.iyqXRGs1nQkPGr/k5S', 'instructor', NULL, '2025-07-07 14:22:10', '2025-07-08 05:13:44'),
(44, 'H.O.D CLINICAL MEDICINE', '$2y$10$U54ztcnneGhPk0fiZjm.NeGbc.zgy87n9vvhPFok12USIE6xJipim', 'hod', NULL, '2025-07-08 13:50:38', '2025-07-08 13:58:55');

-- --------------------------------------------------------

--
-- Table structure for table `user_details`
--

DROP TABLE IF EXISTS `user_details`;
CREATE TABLE IF NOT EXISTS `user_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `id_number` varchar(20) NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `designation` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `user_details`
--

INSERT INTO `user_details` (`id`, `user_id`, `name`, `id_number`, `gender`, `phone`, `email`, `designation`, `created_at`, `updated_at`) VALUES
(1, 1, 'Anna Kengere', '12345678', 'Female', '0711954609', 'admin@kasms.ac.ke', 'System Administrator', '2025-07-05 16:56:11', '2025-07-08 13:03:05'),
(2, 2, 'instructor', '234156', 'Male', '0712345678', 'john@example.com', 'instructor', '2025-07-06 18:43:15', '2025-07-09 08:58:35'),
(3, 3, 'ROSMARY OMAYO', '89076245', 'Female', '0725664990', 'rosemary@gmail.com', 'H.O.D NURSING', '2025-07-06 19:01:41', '2025-07-08 11:43:29'),
(4, 4, 'Brian Ochieng', '7890234', 'Male', '0156734223', 'finance@gmail.com', 'finance', '2025-07-06 19:01:41', '2025-07-08 13:05:35'),
(13, 13, 'DAVID ONSONGO', '45362891', 'Male', '0723969650', 'registrar@gmail.com', 'registrar', '2025-07-06 19:04:20', '2025-07-08 13:07:15'),
(41, 41, 'CATE WANJIKU', '11443274', 'Female', '714432744', 'cate@gmail.com', 'student', '2025-07-06 19:01:41', '2025-07-07 12:55:52'),
(42, 42, 'ESTHER KWAMBOKA', '21414835', 'Female', '114148354', 'esther@gmail.com', 'student', '2025-07-06 19:01:41', '2025-07-07 12:56:39'),
(43, 43, 'MEROBINA BOSIBORI', '21414835', 'Female', '0786456323', 'mero@gmail.com', 'Instrutor', '2025-07-07 14:22:10', '2025-07-09 08:06:36'),
(44, 44, 'MOSES OCHIENG', '77828782819', 'Male', '0725825131', 'clinicalmedicine@kasms.ac.ke', 'H.O.D CLINICAL MEDICINE', '2025-07-08 13:50:38', '2025-07-08 14:17:59');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `academic_leave`
--
ALTER TABLE `academic_leave`
  ADD CONSTRAINT `academic_leave_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `clearance`
--
ALTER TABLE `clearance`
  ADD CONSTRAINT `clearance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `coursework`
--
ALTER TABLE `coursework`
  ADD CONSTRAINT `coursework_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `coursework_ibfk_2` FOREIGN KEY (`unit_code`) REFERENCES `units` (`unit_code`) ON DELETE CASCADE;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`unit_code`) REFERENCES `units` (`unit_code`) ON DELETE CASCADE;

--
-- Constraints for table `exam_audit`
--
ALTER TABLE `exam_audit`
  ADD CONSTRAINT `exam_audit_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_audit_ibfk_2` FOREIGN KEY (`unit_code`) REFERENCES `units` (`unit_code`) ON DELETE CASCADE;

--
-- Constraints for table `exam_card`
--
ALTER TABLE `exam_card`
  ADD CONSTRAINT `exam_card_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `exam_results`
--
ALTER TABLE `exam_results`
  ADD CONSTRAINT `exam_results_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_results_ibfk_2` FOREIGN KEY (`unit_code`) REFERENCES `units` (`unit_code`) ON DELETE CASCADE;

--
-- Constraints for table `fees`
--
ALTER TABLE `fees`
  ADD CONSTRAINT `fees_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `registrations_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `registrations_ibfk_3` FOREIGN KEY (`unit_code`) REFERENCES `units` (`unit_code`) ON DELETE SET NULL;

--
-- Constraints for table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE SET NULL;

--
-- Constraints for table `student_survey`
--
ALTER TABLE `student_survey`
  ADD CONSTRAINT `student_survey_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `units`
--
ALTER TABLE `units`
  ADD CONSTRAINT `units_ibfk_1` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `units_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE SET NULL;

--
-- Constraints for table `user_details`
--
ALTER TABLE `user_details`
  ADD CONSTRAINT `user_details_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
