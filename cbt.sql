-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 05, 2025 at 04:48 PM
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
-- Database: `cbt`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `content` text NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `content`, `user_id`, `created_at`, `updated_at`) VALUES
(2, '<p>Exams will start on 20th of August signed Management</p>', 1, '2025-05-15 19:47:39', '2025-05-15 19:47:39'),
(3, '<p>Annoucment again</p><p><br></p>', 1, '2025-05-16 13:21:52', '2025-05-16 13:21:52'),
(4, '<p>Test will star on monday</p>', 1, '2025-05-20 20:25:29', '2025-05-20 20:25:29');

-- --------------------------------------------------------

--
-- Table structure for table `announcement_user`
--

CREATE TABLE `announcement_user` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `announcement_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `announcement_user`
--

INSERT INTO `announcement_user` (`id`, `announcement_id`, `user_id`, `read_at`, `created_at`, `updated_at`) VALUES
(1, 3, 1, NULL, '2025-05-16 13:37:06', '2025-05-16 13:37:06'),
(2, 2, 1, NULL, '2025-05-16 13:37:06', '2025-05-16 13:37:06'),
(3, 3, 4, NULL, '2025-05-17 14:47:33', '2025-05-17 14:47:33'),
(4, 2, 4, NULL, '2025-05-17 14:47:33', '2025-05-17 14:47:33'),
(5, 3, 2, NULL, '2025-05-17 15:19:31', '2025-05-17 15:19:31'),
(6, 2, 2, NULL, '2025-05-17 15:19:31', '2025-05-17 15:19:31'),
(7, 3, 7, NULL, '2025-05-17 20:48:21', '2025-05-17 20:48:21'),
(8, 2, 7, NULL, '2025-05-17 20:48:21', '2025-05-17 20:48:21'),
(9, 3, 13, NULL, '2025-05-18 15:57:30', '2025-05-18 15:57:30'),
(10, 2, 13, NULL, '2025-05-18 15:57:30', '2025-05-18 15:57:30'),
(11, 3, 15, NULL, '2025-05-20 13:10:33', '2025-05-20 13:10:33'),
(12, 2, 15, NULL, '2025-05-20 13:10:33', '2025-05-20 13:10:33'),
(13, 3, 16, NULL, '2025-05-20 14:15:05', '2025-05-20 14:15:05'),
(14, 2, 16, NULL, '2025-05-20 14:15:05', '2025-05-20 14:15:05'),
(15, 3, 18, NULL, '2025-05-20 19:56:15', '2025-05-20 19:56:15'),
(16, 2, 18, NULL, '2025-05-20 19:56:15', '2025-05-20 19:56:15'),
(17, 4, 1, NULL, '2025-05-20 20:25:29', '2025-05-20 20:25:29'),
(18, 4, 2, NULL, '2025-05-28 16:47:22', '2025-05-28 16:47:22'),
(19, 4, 7, NULL, '2025-05-28 17:07:36', '2025-05-28 17:07:36'),
(20, 4, 19, NULL, '2025-05-29 11:21:46', '2025-05-29 11:21:46'),
(21, 3, 19, NULL, '2025-05-29 11:21:46', '2025-05-29 11:21:46'),
(22, 2, 19, NULL, '2025-05-29 11:21:46', '2025-05-29 11:21:46'),
(23, 4, 20, NULL, '2025-06-04 13:51:10', '2025-06-04 13:51:10'),
(24, 3, 20, NULL, '2025-06-04 13:51:10', '2025-06-04 13:51:10'),
(25, 2, 20, NULL, '2025-06-04 13:51:10', '2025-06-04 13:51:10');

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cache`
--

INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('laravel_cache_shuffled_questions_16_2', 'a:4:{i:0;i:48;i:1;i:49;i:2;i:51;i:3;i:50;}', 1748620681),
('laravel_cache_shuffled_questions_16_7', 'a:4:{i:0;i:48;i:1;i:49;i:2;i:51;i:3;i:50;}', 1748620933),
('laravel_cache_shuffled_questions_20_20', 'a:6:{i:0;i:56;i:1;i:59;i:2;i:61;i:3;i:60;i:4;i:57;i:5;i:58;}', 1749131838),
('laravel_cache_shuffled_questions_7_2', 'a:4:{i:0;i:27;i:1;i:29;i:2;i:30;i:3;i:28;}', 1748622488);

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `class_user`
--

CREATE TABLE `class_user` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `school_class_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `class_user`
--

INSERT INTO `class_user` (`id`, `user_id`, `school_class_id`, `created_at`, `updated_at`) VALUES
(8, 4, 7, NULL, NULL),
(9, 4, 8, NULL, NULL),
(12, 15, 7, NULL, NULL),
(13, 15, 8, NULL, NULL),
(14, 15, 9, NULL, NULL),
(15, 16, 1, NULL, NULL),
(16, 16, 3, NULL, NULL),
(17, 16, 4, NULL, NULL),
(18, 18, 1, NULL, NULL),
(19, 18, 3, NULL, NULL),
(20, 18, 4, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `course_name` varchar(255) NOT NULL,
  `section_id` bigint(20) UNSIGNED NOT NULL,
  `added_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `course_name`, `section_id`, `added_by`, `created_at`, `updated_at`) VALUES
(2, 'English', 4, 1, '2025-05-11 06:41:55', '2025-05-11 06:41:55'),
(3, 'Physics', 4, 1, '2025-05-11 06:42:05', '2025-05-11 06:42:05'),
(5, 'Biology', 5, 1, '2025-05-11 07:00:54', '2025-05-11 07:00:54'),
(6, 'Mathematics', 4, 1, '2025-05-20 14:36:30', '2025-05-20 14:36:30'),
(7, 'MATHEMATICS', 5, 1, '2025-05-20 14:42:00', '2025-05-20 14:42:00'),
(8, 'COMPUTER SCIENCE', 4, 1, '2025-05-20 19:54:07', '2025-05-20 19:54:07');

-- --------------------------------------------------------

--
-- Table structure for table `course_user`
--

CREATE TABLE `course_user` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `course_user`
--

INSERT INTO `course_user` (`id`, `course_id`, `user_id`, `created_at`, `updated_at`) VALUES
(2, 2, 16, NULL, NULL),
(3, 5, 15, NULL, NULL),
(4, 5, 4, NULL, NULL),
(5, 7, 4, NULL, NULL),
(6, 2, 18, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2025_05_10_142015_create_sections_table', 2),
(5, '2025_05_10_193829_add_user_type_to_users_table', 3),
(6, '2025_05_10_195553_add_added_by_to_users_table', 4),
(7, '2025_05_10_200344_add_is_active_and_login_attempts_to_users_table', 5),
(8, '2025_05_11_044056_create_classes_table', 6),
(9, '2025_05_11_044700_create_school_classes_table', 7),
(10, '2025_05_11_073415_create_courses_table', 8),
(11, '2025_05_11_161717_create_tests_table', 9),
(12, '2025_05_11_171308_add_class_id_to_tests_table', 10),
(13, '2025_05_11_182242_create_questions_table', 11),
(14, '2025_05_12_144942_add_options_and_not_question_to_questions_table', 12),
(15, '2025_05_12_150047_make_question_columns_nullable', 13),
(16, '2025_05_13_024407_add_columns_to_tests_table', 14),
(17, '2025_05_13_113702_add_class_id_and_gender_to_users_table', 15),
(18, '2025_05_13_171011_create_test_submissions_table', 16),
(19, '2025_05_14_021732_add_scheduled_date_to_tests_table', 17),
(20, '2025_05_14_024534_add_submission_date_to_tests_table', 18),
(21, '2025_05_14_093750_add_approval_fields_to_tests_table', 19),
(22, '2025_05_14_141555_add_scheduled_by_to_tests_table', 20),
(23, '2025_05_14_161740_add_is_started_to_tests_table', 21),
(24, '2025_05_14_173235_create_students_exams_table', 22),
(25, '2025_05_14_174805_add_duration_to_students_exams_table', 23),
(26, '2025_05_15_095745_add_is_submited_to_students_exams_table', 24),
(27, '2025_05_15_195549_create_announcements_table', 25),
(28, '2025_05_15_205544_create_announcement_user_table', 26),
(29, '2025_05_17_153730_add_pass_mark_to_tests_table', 27),
(30, '2025_05_17_202117_change_question_to_longtext_in_questions_table', 28),
(31, '2025_05_18_172644_add_is_passed_to_students_exams_table', 29),
(32, '2025_05_19_194427_create_section_user_table', 30),
(33, '2025_05_19_194514_create_class_user_table', 30),
(34, '2025_05_20_143454_create_course_user_table', 31),
(35, '2025_05_29_121727_create_user_question_orders_table', 32);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `test_id` bigint(20) UNSIGNED NOT NULL,
  `question` longtext NOT NULL,
  `answer` varchar(255) DEFAULT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `not_question` tinyint(1) DEFAULT NULL,
  `mark` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `questions`
--

INSERT INTO `questions` (`id`, `test_id`, `question`, `answer`, `options`, `not_question`, `mark`, `created_at`, `updated_at`) VALUES
(27, 7, '<p data-start=\"121\" data-end=\"150\" class=\"\"><strong data-start=\"121\" data-end=\"150\">The Importance of Reading</strong></p><p>\r\n</p><p data-start=\"152\" data-end=\"549\" class=\"\">Reading is one of the most valuable skills a person can develop. It opens doors to knowledge, improves vocabulary, and strengthens the imagination. People who read regularly tend to perform better in school and have a deeper understanding of the world around them. Whether it\'s fiction, nonfiction, or even newspapers, reading allows us to explore new ideas and cultures without leaving our homes.</p>', NULL, '{\"A\":null}', 1, NULL, '2025-05-15 10:24:37', '2025-05-15 10:24:37'),
(28, 7, '<p>According to the passage, what are two benefits of reading?</p>', 'A', '{\"A\":\"Improves vocabulary and strengthens imagination\",\"B\":\"Makes you taller and stronger\",\"C\":\"Helps you cook better meals\",\"D\":\"Teaches you to play sports\"}', NULL, 3, '2025-05-15 10:25:48', '2025-05-15 10:28:41'),
(29, 7, '<p>What types of reading materials are mentioned in the passage?</p>', 'B', '{\"A\":\"Magazines and comic books\",\"B\":\"Fiction, nonfiction, and newspapers\",\"C\":\"Text messages and emails\",\"D\":\"Advertisements and posters\"}', NULL, 3, '2025-05-15 10:26:56', '2025-05-15 10:28:54'),
(30, 7, '<p>How does reading help us understand the world better?</p>', 'A', '{\"A\":\"By allowing us to explore new ideas and cultures\",\"B\":\"By making us watch more movies\",\"C\":\"By helping us travel physically to new places\",\"D\":\"By teaching us to memorize facts\"}', NULL, 4, '2025-05-15 10:28:02', '2025-05-15 10:29:06'),
(38, 8, '<h6><strong data-start=\"194\" data-end=\"262\">Section A: Multiple Choice Questions (Grammar and Comprehension)</strong></h6><p>\r\n</p><p data-start=\"265\" data-end=\"454\" class=\"\"><strong data-start=\"265\" data-end=\"282\">Instructions:</strong><br data-start=\"282\" data-end=\"285\">\r\nRead each question carefully and choose the option that best answers the question or completes the sentence. Shade the letter of the correct answer on your answer sheet.</p>', NULL, '{\"A\":null}', 1, NULL, '2025-05-17 15:04:47', '2025-05-17 15:04:47'),
(39, 8, '<p>Choose the sentence that is grammatically correct</p>', 'C', '{\"A\":\"She don\\u2019t like going to the market on Sundays.\",\"B\":\"She doesn\\u2019t likes going to the market on Sundays.\",\"C\":\"She doesn\\u2019t like going to the market on Sundays\",\"D\":\"She not like going to the market on Sundays.\"}', NULL, 5, '2025-05-17 15:06:06', '2025-05-17 15:06:06');
INSERT INTO `questions` (`id`, `test_id`, `question`, `answer`, `options`, `not_question`, `mark`, `created_at`, `updated_at`) VALUES
(40, 8, '<p data-start=\"813\" data-end=\"867\" class=\"\"><img src=\"data:image/jpeg;base64,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\" data-filename=\"Direct Wholesale High Quality Production Line Milky Powder Free White Latex Gloves.jpeg\" style=\"width: 25%; float: right;\" class=\"note-float-right\"><strong data-start=\"813\" data-end=\"867\"><br></strong></p><p data-start=\"813\" data-end=\"867\" class=\"\"><strong data-start=\"813\" data-end=\"867\">Read the passage and answer the question below:</strong></p><p>\r\n</p><p data-start=\"869\" data-end=\"1029\" class=\"\"><em data-start=\"869\" data-end=\"1029\">“Although it was raining heavily, the children insisted on playing outside. Their laughter echoed through the neighborhood, unaffected by the gloomy weather.”</em></p>', 'A', '{\"A\":null}', 1, NULL, '2025-05-17 15:06:23', '2025-05-17 19:34:52');
INSERT INTO `questions` (`id`, `test_id`, `question`, `answer`, `options`, `not_question`, `mark`, `created_at`, `updated_at`) VALUES
(41, 8, '<p>What does the word “insisted” most likely mean in this context?</p>', 'C', '{\"A\":\"Forced someone to do something\",\"B\":\"Requested politely\",\"C\":\"Demanded firmly\",\"D\":\"Suggested casually\"}', NULL, 5, '2025-05-17 15:07:39', '2025-05-17 15:07:39'),
(42, 11, '<p>sdfsdgsd</p>', NULL, '{\"A\":null}', NULL, NULL, '2025-05-17 15:18:14', '2025-05-17 15:18:14'),
(43, 12, '<p>Instruction</p>', NULL, '{\"A\":null}', 1, NULL, '2025-05-18 16:07:01', '2025-05-18 16:07:01'),
(44, 12, '<p>question 1</p>', 'A', '{\"A\":\"a\",\"B\":\"b\"}', NULL, 2, '2025-05-18 16:07:17', '2025-05-18 16:07:53'),
(45, 13, '<p>question</p>', 'A', '{\"A\":\"a\",\"B\":\"b\"}', NULL, 10, '2025-05-18 16:39:48', '2025-05-18 16:39:48'),
(46, 14, '<p>Question</p>', 'A', '{\"A\":\"A\",\"B\":\"B\"}', NULL, 1, '2025-05-18 16:50:40', '2025-05-18 16:50:40'),
(47, 15, '<p>Question 1</p>', 'A', '{\"A\":\"a\",\"B\":\"b\"}', NULL, 2, '2025-05-18 17:52:07', '2025-05-18 17:52:07'),
(48, 16, '<p data-start=\"296\" data-end=\"346\" class=\"\"><strong data-start=\"296\" data-end=\"312\">Time Allowed</strong>: 15 minutes<br data-start=\"324\" data-end=\"327\">\r\n<strong data-start=\"327\" data-end=\"343\">Instructions</strong>:</p><p>\r\n</p><ul data-start=\"347\" data-end=\"504\">\r\n<li data-start=\"347\" data-end=\"380\" class=\"\">\r\n<p data-start=\"349\" data-end=\"380\" class=\"\">Read each question carefully.</p>\r\n</li>\r\n<li data-start=\"381\" data-end=\"424\" class=\"\">\r\n<p data-start=\"383\" data-end=\"424\" class=\"\">Choose the best answer (A, B, C, or D).</p>\r\n</li>\r\n<li data-start=\"425\" data-end=\"463\" class=\"\">\r\n<p data-start=\"427\" data-end=\"463\" class=\"\">Each correct answer earns 1 point.</p>\r\n</li>\r\n<li data-start=\"464\" data-end=\"504\" class=\"\">\r\n<p data-start=\"466\" data-end=\"504\" class=\"\">No negative marking for wrong answers.</p></li></ul>', NULL, '{\"A\":null}', 1, NULL, '2025-05-18 18:32:59', '2025-05-18 18:32:59'),
(49, 16, '<p>Section 1: Grammar &amp; Vocabulary (Questions 1–5)</p>', NULL, '{\"A\":null}', 1, NULL, '2025-05-18 18:33:18', '2025-05-18 18:33:18'),
(50, 16, '<p><strong data-start=\"568\" data-end=\"636\">Choose the correct form of the verb to complete the sentence:</strong><br data-start=\"636\" data-end=\"639\">\r\n\"If he ___ harder, he would have passed the exam.\"</p>', 'C', '{\"A\":\"studies\",\"B\":\"studied\",\"C\":\"had studied\",\"D\":\"will study\"}', NULL, 50, '2025-05-18 18:34:16', '2025-05-18 18:34:16'),
(51, 16, '<p><strong data-start=\"781\" data-end=\"842\">Which word is the synonym of “rapid” in this sentence?</strong><br data-start=\"842\" data-end=\"845\">\r\n\"The rapid response of the team saved many lives.\"</p>', 'C', '{\"A\":\"delayed\",\"B\":\"slow\",\"C\":\"quick\",\"D\":\"gentle\"}', NULL, 50, '2025-05-18 18:35:23', '2025-05-18 18:35:23'),
(52, 18, '<p>Question instruction</p>', NULL, '{\"A\":null}', 1, NULL, '2025-05-20 19:28:11', '2025-05-20 19:28:11'),
(53, 19, '<p>Answer All Questions</p>', NULL, '{\"A\":null}', 1, NULL, '2025-05-20 20:04:21', '2025-05-20 20:04:21'),
(54, 19, '<p>What is a noun?</p>', 'A', '{\"A\":\"Name of a place animal or things\",\"B\":\"name of Tinubu\"}', NULL, 10, '2025-05-20 20:06:01', '2025-05-20 20:07:11'),
(55, 19, '<p>What is an adverb?</p>', 'B', '{\"A\":\"Value\",\"B\":\"value b\"}', NULL, 10, '2025-05-20 20:06:56', '2025-05-20 20:06:56'),
(56, 20, '<h3 data-start=\"124\" data-end=\"141\">Instructions:</h3><p>\r\n</p><ul data-start=\"143\" data-end=\"402\">\r\n<li data-start=\"143\" data-end=\"177\">\r\n<p data-start=\"145\" data-end=\"177\">This exam has <strong data-start=\"159\" data-end=\"174\">5 questions</strong>.</p>\r\n</li>\r\n<li data-start=\"178\" data-end=\"211\">\r\n<p data-start=\"180\" data-end=\"211\">Read each question carefully.</p>\r\n</li>\r\n<li data-start=\"212\" data-end=\"285\">\r\n<p data-start=\"214\" data-end=\"285\">Choose the <strong data-start=\"225\" data-end=\"243\">correct answer</strong> from the options given (A, B, C, or D).</p>\r\n</li>\r\n<li data-start=\"286\" data-end=\"330\">\r\n<p data-start=\"288\" data-end=\"330\">Only select <strong data-start=\"300\" data-end=\"314\">one answer</strong> per question.</p>\r\n</li>\r\n<li data-start=\"331\" data-end=\"372\">\r\n<p data-start=\"333\" data-end=\"372\">When you finish, submit your answers.</p>\r\n</li>\r\n<li data-start=\"373\" data-end=\"402\">\r\n<p data-start=\"375\" data-end=\"402\">Do your best and good luck!</p></li></ul>', NULL, '{\"A\":null}', 1, NULL, '2025-06-04 13:07:33', '2025-06-04 13:07:33'),
(57, 20, '<p>What is the value of \\( 2^3 + 3^2 \\)?</p><div><br></div>', 'B', '{\"A\":\"2\",\"B\":\"10\",\"C\":\"5\",\"D\":\"\\\\(4^2 \\\\)\"}', NULL, 2, '2025-06-04 13:26:05', '2025-06-04 13:38:39'),
(58, 20, '<p>What is 25 + 17?</p>', 'A', '{\"A\":\"42\",\"B\":\"43\",\"C\":\"44\",\"D\":\"45\"}', NULL, 2, '2025-06-04 13:28:40', '2025-06-04 13:28:40'),
(59, 20, '<p>Which shape has 4 equal sides and 4 right angles?</p>', 'C', '{\"A\":\"Rectangle\",\"B\":\"Triangle\",\"C\":\"Square\",\"D\":\"Circle\"}', NULL, 2, '2025-06-04 13:29:35', '2025-06-04 13:29:35'),
(60, 20, '<p>If you have 3 groups of 4 apples, how many apples do you have in total?</p>', 'B', '{\"A\":\"7\",\"B\":\"12\",\"C\":\"15\",\"D\":\"24\"}', NULL, 2, '2025-06-04 13:30:20', '2025-06-04 13:30:20'),
(61, 20, '<p>Which of the following is an improper fraction?</p>', 'B', '{\"A\":\"\\\\( \\\\frac{3}{4} \\\\)\",\"B\":\"\\\\( \\\\frac{5}{2} \\\\)\",\"C\":\"\\\\( \\\\frac{1}{3} \\\\)\",\"D\":\"\\\\(\\\\frac{2}{5} \\\\)\"}', NULL, 2, '2025-06-04 13:33:55', '2025-06-04 13:33:55');

-- --------------------------------------------------------

--
-- Table structure for table `school_classes`
--

CREATE TABLE `school_classes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `section_id` bigint(20) UNSIGNED NOT NULL,
  `added_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `school_classes`
--

INSERT INTO `school_classes` (`id`, `name`, `section_id`, `added_by`, `created_at`, `updated_at`) VALUES
(1, 'JSS1', 4, 1, '2025-05-11 05:15:45', '2025-05-11 06:01:03'),
(3, 'JSS2', 4, 1, '2025-05-11 05:16:58', '2025-05-11 06:15:57'),
(4, 'JSS3', 4, 1, '2025-05-11 05:17:10', '2025-05-11 06:02:19'),
(7, 'SSS2', 5, 1, '2025-05-11 06:02:46', '2025-05-11 06:17:33'),
(8, 'SSS1', 5, 1, '2025-05-11 06:02:55', '2025-05-11 06:02:55'),
(9, 'SSS3', 5, 1, '2025-05-11 06:18:46', '2025-05-11 06:18:46'),
(10, 'Basic 4', 6, 1, '2025-05-20 19:52:51', '2025-05-20 19:52:51');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `section_name` varchar(255) NOT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `section_name`, `created_by`, `created_at`, `updated_at`) VALUES
(4, 'Junior Secondary', 1, '2025-05-10 18:07:27', '2025-05-10 18:07:27'),
(5, 'Senior Secondary', 1, '2025-05-10 18:07:35', '2025-05-10 18:07:35'),
(6, 'Primary Section', 1, '2025-05-20 19:51:50', '2025-05-20 19:51:50');

-- --------------------------------------------------------

--
-- Table structure for table `section_user`
--

CREATE TABLE `section_user` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `section_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `section_user`
--

INSERT INTO `section_user` (`id`, `user_id`, `section_id`, `created_at`, `updated_at`) VALUES
(5, 4, 5, NULL, NULL),
(7, 16, 4, NULL, NULL),
(8, 15, 5, NULL, NULL),
(9, 18, 4, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('NaAzON06dyJ9zHj5BqYZNG1RmWSZhFZ1vkiCvEED', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiMkFpMXBwYXoxUEFwM0RlQ0lla2lrQWNmd2pWQ0ZXNmxSYjBtYjBOTSI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7fX0=', 1749050943);

-- --------------------------------------------------------

--
-- Table structure for table `students_exams`
--

CREATE TABLE `students_exams` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `class_id` bigint(20) UNSIGNED NOT NULL,
  `test_id` bigint(20) UNSIGNED NOT NULL,
  `start_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_time` timestamp NULL DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `exhausted_time` timestamp NULL DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `is_submited` tinyint(1) NOT NULL DEFAULT 0,
  `test_total_score` int(11) DEFAULT NULL,
  `is_passed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students_exams`
--

INSERT INTO `students_exams` (`id`, `user_id`, `class_id`, `test_id`, `start_time`, `end_time`, `duration`, `exhausted_time`, `score`, `is_submited`, `test_total_score`, `is_passed`, `created_at`, `updated_at`) VALUES
(28, 2, 1, 7, '2025-05-29 17:19:39', NULL, 5, NULL, NULL, 0, NULL, 0, NULL, NULL),
(29, 20, 10, 20, '2025-06-04 13:57:17', '2025-06-04 13:59:08', 15, '2025-06-04 13:59:08', 8, 1, 18, 1, NULL, '2025-06-04 13:59:08');

-- --------------------------------------------------------

--
-- Table structure for table `tests`
--

CREATE TABLE `tests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `test_name` varchar(255) NOT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `section_id` bigint(20) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `class_id` bigint(20) UNSIGNED NOT NULL,
  `test_type` varchar(255) NOT NULL,
  `duration` int(11) DEFAULT NULL,
  `pass_mark` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_submitted` tinyint(1) NOT NULL DEFAULT 0,
  `submitted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `approval_date` timestamp NULL DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `scheduled_date` datetime DEFAULT NULL,
  `is_started` tinyint(1) DEFAULT NULL,
  `scheduled_by` bigint(20) UNSIGNED DEFAULT NULL,
  `submission_date` timestamp NULL DEFAULT NULL,
  `comments` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tests`
--

INSERT INTO `tests` (`id`, `test_name`, `created_by`, `section_id`, `course_id`, `class_id`, `test_type`, `duration`, `pass_mark`, `created_at`, `updated_at`, `is_submitted`, `submitted_by`, `is_approved`, `approval_date`, `approved_by`, `scheduled_date`, `is_started`, `scheduled_by`, `submission_date`, `comments`) VALUES
(7, 'MOCK EXAM JSS1 ENGLISH', 4, 4, 2, 1, 'multiple_choice', 5, 6, '2025-05-15 10:24:05', '2025-05-29 17:19:39', 1, 4, 1, '2025-05-15 10:38:43', 1, '2025-05-29 18:19:00', 1, 2, '2025-05-15 10:31:27', NULL),
(8, 'BECE MOCK EXAM FOR JSS1', 4, 4, 2, 1, 'multiple_choice', 10, 5, '2025-05-15 13:53:54', '2025-05-17 20:46:55', 1, 4, 1, '2025-05-17 20:46:55', 2, NULL, NULL, NULL, '2025-05-17 20:41:32', 'change to the correct; Subject Name'),
(10, 'BECE MOCK ENGLISH JSS2', 1, 4, 2, 3, 'multiple_choice', 3, 1, '2025-05-17 14:46:44', '2025-05-28 17:43:14', 1, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(11, 'MOCK EXAM ENGLISH', 4, 4, 2, 3, 'multiple_choice', 5, 5, '2025-05-17 14:50:01', '2025-05-28 17:43:09', 1, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(12, 'MOCK TEST', 1, 4, 2, 1, 'multiple_choice', 2, 2, '2025-05-18 16:06:24', '2025-05-28 17:38:44', 1, 1, 1, '2025-05-18 16:09:09', 1, NULL, 0, NULL, '2025-05-18 16:08:06', NULL),
(13, 'MOCK ENGLISH', 1, 4, 2, 1, 'multiple_choice', 1, 10, '2025-05-18 16:38:51', '2025-05-28 17:43:03', 1, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(14, 'TESTING TEST', 1, 4, 2, 1, 'multiple_choice', 2, 1, '2025-05-18 16:50:06', '2025-05-28 17:38:37', 1, 1, 1, '2025-05-18 16:51:14', 1, NULL, 0, NULL, '2025-05-18 16:50:56', NULL),
(15, 'TESTA MOCK', 1, 4, 2, 1, 'multiple_choice', 2, 2, '2025-05-18 17:51:37', '2025-05-28 17:38:33', 1, 1, 1, '2025-05-18 17:52:28', 1, NULL, 0, NULL, '2025-05-18 17:52:16', NULL),
(16, 'MOCK IELTS ENGLISH EXAM', 4, 4, 2, 1, 'multiple_choice', 15, 100, '2025-05-18 18:32:39', '2025-05-29 16:26:12', 1, 4, 1, '2025-05-18 18:39:30', 2, NULL, 0, NULL, '2025-05-18 18:36:27', NULL),
(17, 'FIRST TERM EXAM', 16, 4, 2, 1, 'multiple_choice', 30, 70, '2025-05-20 14:17:32', '2025-05-28 17:22:43', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(18, 'MATHEMATICS EXAM', 4, 5, 7, 7, 'multiple_choice', 15, 20, '2025-05-20 14:48:29', '2025-05-20 14:48:29', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(19, 'MOCK BECE ENGLISH', 18, 4, 2, 1, 'multiple_choice', 2, 10, '2025-05-20 20:00:31', '2025-05-28 17:33:19', 1, 18, 1, '2025-05-20 20:14:11', 1, NULL, 0, NULL, '2025-05-20 20:13:39', 'forgot to add instruction'),
(20, 'BASIC 4 EXAM', 1, 6, 6, 10, 'multiple_choice', 15, 5, '2025-06-04 12:51:10', '2025-06-04 13:57:17', 1, 1, 1, '2025-06-04 13:50:03', 1, '2025-06-04 14:55:00', 1, 1, '2025-06-04 13:48:45', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `test_submissions`
--

CREATE TABLE `test_submissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `class_id` bigint(20) UNSIGNED DEFAULT NULL,
  `test_id` bigint(20) UNSIGNED NOT NULL,
  `question_id` bigint(20) UNSIGNED NOT NULL,
  `answer` varchar(1) NOT NULL,
  `student_answer` varchar(1) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `test_submissions`
--

INSERT INTO `test_submissions` (`id`, `user_id`, `class_id`, `test_id`, `question_id`, `answer`, `student_answer`, `submitted_at`, `created_at`, `updated_at`) VALUES
(51, 20, 10, 20, 59, 'C', 'C', '2025-06-04 13:58:08', '2025-06-04 13:58:05', '2025-06-04 13:58:08'),
(52, 20, 10, 20, 61, 'B', 'B', '2025-06-04 13:58:28', '2025-06-04 13:58:28', '2025-06-04 13:58:28'),
(53, 20, 10, 20, 60, 'B', 'B', '2025-06-04 13:58:38', '2025-06-04 13:58:38', '2025-06-04 13:58:38'),
(54, 20, 10, 20, 57, 'B', 'D', '2025-06-04 13:58:45', '2025-06-04 13:58:45', '2025-06-04 13:58:45'),
(55, 20, 10, 20, 58, 'A', 'A', '2025-06-04 13:58:54', '2025-06-04 13:58:54', '2025-06-04 13:58:54');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `class_id` bigint(20) UNSIGNED DEFAULT NULL,
  `gender` varchar(255) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `user_type` tinyint(4) NOT NULL DEFAULT 0,
  `added_by` bigint(20) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `login_attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 3
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `class_id`, `gender`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `user_type`, `added_by`, `is_active`, `login_attempts`) VALUES
(1, 'Enyinnaya Osuoha', 'enyinnayaosuoha@gmail.com', NULL, NULL, NULL, '$2y$12$ZIJT83M0CuABdNslAtcJjeOBBowNg/p4YlJu82rr2tP5S/5D5dbK.', NULL, '2025-05-10 11:57:35', '2025-05-28 17:04:48', 1, NULL, 1, 3),
(2, 'Jonathan Doe', 'mayday22.ic@gmail.com', NULL, NULL, NULL, '$2y$12$jaeomosFW7BI7wMtSdgHm.uEWHPN6f5DTExOxdlIRM15STL6mfwse', NULL, '2025-05-10 18:57:33', '2025-05-29 12:14:07', 2, 1, 1, 3),
(4, 'OBY EZEKWESILI', 'obyeze@gmail.com', NULL, NULL, NULL, '$2y$12$1s2ela1r62pPVHARP9pqYORRTpaHdbovSNtCjcAI8OQP0VZdJAGmS', NULL, '2025-05-11 03:04:19', '2025-05-20 08:49:28', 3, 1, 1, 3),
(5, 'Bayo Onanuga', 'bayo@gmail.com', NULL, NULL, NULL, '$2y$12$tSVo15vhqch6kHKhvHo1g.Ca3uZrxYXC5VO1qxBacBbwbNgUUiAZK', NULL, '2025-05-11 03:10:43', '2025-05-28 17:03:38', 2, 1, 0, 3),
(7, 'CHIOMA CIROMA', 'chioma@gmail.com', 1, 'Female', NULL, '$2y$12$Xr5BM.S4PgbrS0v2arLE2OPUY2//93H6ydS4pUZW74aiKqCr9UTCO', NULL, '2025-05-13 11:05:50', '2025-05-18 17:53:31', 4, NULL, 1, 3),
(9, 'TAIWO AWONIYI', 'taiwo@gmail.com', 9, 'Male', NULL, '$2y$12$kAkMjU4hveZirg0lotzQ0OpYnOLxvyzro3xd9CaU1Jen9uQztI5U2', NULL, '2025-05-13 12:01:18', '2025-05-15 10:29:54', 4, 1, 1, 3),
(13, 'VIVIAN DIKE', 'viviandike@gmail.com', 3, 'Female', NULL, '$2y$12$E04TzkBlyEySvRp.CqSe2OrHh5CTcSoyW2iu/wnwFRkG.2hOm41zC', NULL, '2025-05-15 15:08:35', '2025-05-15 15:08:35', 4, 2, 1, 3),
(15, 'OLUREMI TINUBU', 'oluremi@gmail.com', NULL, NULL, NULL, '$2y$12$5TeU0ixDnyEAyvSr2wDQtebTA1mSe/WFKARxTAjBeN3non/dCt0yG', NULL, '2025-05-19 18:57:35', '2025-05-20 08:47:11', 3, 1, 1, 3),
(16, 'JOHN DIKE', 'johndike@gmail.com', NULL, NULL, NULL, '$2y$12$6nu3t/lplk1eoiJAcLyD1uxEAHpvDq6KRMafkQpPbiqBNP0ze/cqi', NULL, '2025-05-20 13:46:01', '2025-05-20 13:46:01', 3, 1, 1, 3),
(17, 'john doe', 'johndoe2@gmail.com', NULL, NULL, NULL, '$2y$12$4QqE3lrJ5lOx91/GCw9fTuU5ewXleGZgupFuG9nECSs0zgih6C2Iy', NULL, '2025-05-20 19:46:03', '2025-05-20 19:46:03', 2, 1, 1, 3),
(18, 'HOPE UZODIMMA', 'hope@gmail.com', NULL, NULL, NULL, '$2y$12$vyt/CSv.ieLvUtgeLtnHu.KpKBbVRNBEtaO6f2LQL7wmIpB0gDC9K', NULL, '2025-05-20 19:50:08', '2025-05-20 19:51:01', 3, 1, 1, 3),
(19, 'ENYINNAYA OSUOHA', 'enyinnaya@gmail.com', 1, 'Male', NULL, '$2y$12$Hn3vmYhdZB90GLwnGW5KeOii50OhK4KaQNVnLmlF4Frn9a/iwMiQO', NULL, '2025-05-29 11:21:27', '2025-05-29 11:21:27', 4, 2, 1, 3),
(20, 'DAVID OYEDEPO', 'davidoyedepo@gmail.com', 10, 'Male', NULL, '$2y$12$n7HPaQDxsd5mU4Q2pTOJUeZvnvqzk3RD8TEego6SqG19m8.3rIjyy', NULL, '2025-06-04 12:36:10', '2025-06-04 12:36:10', 4, 1, 1, 3);

-- --------------------------------------------------------

--
-- Table structure for table `user_question_orders`
--

CREATE TABLE `user_question_orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `test_id` bigint(20) UNSIGNED NOT NULL,
  `question_order` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`question_order`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_question_orders`
--

INSERT INTO `user_question_orders` (`id`, `user_id`, `test_id`, `question_order`, `created_at`, `updated_at`) VALUES
(1, 7, 16, '[48,49,51,50]', '2025-05-29 11:18:39', '2025-05-29 11:18:39'),
(2, 19, 16, '[48,49,51,50]', '2025-05-29 11:21:55', '2025-05-29 11:21:55');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `announcements_user_id_foreign` (`user_id`);

--
-- Indexes for table `announcement_user`
--
ALTER TABLE `announcement_user`
  ADD PRIMARY KEY (`id`),
  ADD KEY `announcement_user_announcement_id_foreign` (`announcement_id`),
  ADD KEY `announcement_user_user_id_foreign` (`user_id`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `class_user`
--
ALTER TABLE `class_user`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_user_user_id_foreign` (`user_id`),
  ADD KEY `class_user_school_class_id_foreign` (`school_class_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `courses_section_id_foreign` (`section_id`),
  ADD KEY `courses_added_by_foreign` (`added_by`);

--
-- Indexes for table `course_user`
--
ALTER TABLE `course_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `course_user_course_id_user_id_unique` (`course_id`,`user_id`),
  ADD KEY `course_user_user_id_foreign` (`user_id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `questions_test_id_foreign` (`test_id`);

--
-- Indexes for table `school_classes`
--
ALTER TABLE `school_classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `school_classes_section_id_foreign` (`section_id`),
  ADD KEY `school_classes_added_by_foreign` (`added_by`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sections_created_by_foreign` (`created_by`);

--
-- Indexes for table `section_user`
--
ALTER TABLE `section_user`
  ADD PRIMARY KEY (`id`),
  ADD KEY `section_user_user_id_foreign` (`user_id`),
  ADD KEY `section_user_section_id_foreign` (`section_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `students_exams`
--
ALTER TABLE `students_exams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `students_exams_user_id_foreign` (`user_id`);

--
-- Indexes for table `tests`
--
ALTER TABLE `tests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tests_created_by_foreign` (`created_by`),
  ADD KEY `tests_section_id_foreign` (`section_id`),
  ADD KEY `tests_course_id_foreign` (`course_id`),
  ADD KEY `tests_class_id_foreign` (`class_id`),
  ADD KEY `tests_approved_by_foreign` (`approved_by`),
  ADD KEY `tests_submitted_by_foreign` (`submitted_by`),
  ADD KEY `tests_scheduled_by_foreign` (`scheduled_by`);

--
-- Indexes for table `test_submissions`
--
ALTER TABLE `test_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `test_submissions_user_id_foreign` (`user_id`),
  ADD KEY `test_submissions_test_id_foreign` (`test_id`),
  ADD KEY `test_submissions_question_id_foreign` (`question_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD KEY `users_added_by_foreign` (`added_by`),
  ADD KEY `users_class_id_foreign` (`class_id`);

--
-- Indexes for table `user_question_orders`
--
ALTER TABLE `user_question_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_question_orders_user_id_test_id_unique` (`user_id`,`test_id`),
  ADD KEY `user_question_orders_test_id_foreign` (`test_id`),
  ADD KEY `user_question_orders_user_id_test_id_index` (`user_id`,`test_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `announcement_user`
--
ALTER TABLE `announcement_user`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `class_user`
--
ALTER TABLE `class_user`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `course_user`
--
ALTER TABLE `course_user`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `school_classes`
--
ALTER TABLE `school_classes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `section_user`
--
ALTER TABLE `section_user`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `students_exams`
--
ALTER TABLE `students_exams`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `tests`
--
ALTER TABLE `tests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `test_submissions`
--
ALTER TABLE `test_submissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `user_question_orders`
--
ALTER TABLE `user_question_orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `announcement_user`
--
ALTER TABLE `announcement_user`
  ADD CONSTRAINT `announcement_user_announcement_id_foreign` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcement_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `class_user`
--
ALTER TABLE `class_user`
  ADD CONSTRAINT `class_user_school_class_id_foreign` FOREIGN KEY (`school_class_id`) REFERENCES `school_classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `courses_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `course_user`
--
ALTER TABLE `course_user`
  ADD CONSTRAINT `course_user_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `questions_test_id_foreign` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `school_classes`
--
ALTER TABLE `school_classes`
  ADD CONSTRAINT `school_classes_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `school_classes_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `section_user`
--
ALTER TABLE `section_user`
  ADD CONSTRAINT `section_user_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `section_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students_exams`
--
ALTER TABLE `students_exams`
  ADD CONSTRAINT `students_exams_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tests`
--
ALTER TABLE `tests`
  ADD CONSTRAINT `tests_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tests_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `school_classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tests_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tests_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tests_scheduled_by_foreign` FOREIGN KEY (`scheduled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tests_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tests_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `test_submissions`
--
ALTER TABLE `test_submissions`
  ADD CONSTRAINT `test_submissions_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `test_submissions_test_id_foreign` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `test_submissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `school_classes` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_question_orders`
--
ALTER TABLE `user_question_orders`
  ADD CONSTRAINT `user_question_orders_test_id_foreign` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_question_orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
