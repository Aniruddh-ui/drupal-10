-- FOSSEE Event Registration Module
-- Database Schema Dump
-- Drupal 10/11 Compatible
-- 
-- This file contains the SQL statements to manually create the module's tables.
-- Note: In Drupal, tables are automatically created via hook_schema() when the module is enabled.
-- This dump is provided for reference and manual testing purposes.

-- --------------------------------------------------------
-- Table: fossee_event_config
-- Description: Stores event definitions created by administrators
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `fossee_event_config` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary key: Unique event identifier.',
  `event_name` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'The display name of the event.',
  `category` VARCHAR(128) NOT NULL DEFAULT '' COMMENT 'Event category for AJAX filtering.',
  `start_date` INT(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Unix timestamp: Registration window opens.',
  `end_date` INT(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Unix timestamp: Registration window closes.',
  `event_date` INT(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Unix timestamp: The actual event date.',
  `created` INT(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Unix timestamp: Record creation time.',
  `changed` INT(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Unix timestamp: Record last modification time.',
  PRIMARY KEY (`id`),
  KEY `category` (`category`),
  KEY `start_date` (`start_date`),
  KEY `end_date` (`end_date`),
  KEY `event_date` (`event_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores event definitions for the FOSSEE Event module.';

-- --------------------------------------------------------
-- Table: fossee_event_registration
-- Description: Stores user registration submissions for events
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `fossee_event_registration` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary key: Unique registration identifier.',
  `event_id` INT(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Foreign key: References fossee_event_config.id.',
  `name` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Participant full name.',
  `email` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Participant email address.',
  `department` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Participant department or organization.',
  `event_date` INT(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Unix timestamp: The selected event date.',
  `created` INT(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Unix timestamp: Registration submission time.',
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  UNIQUE KEY `email_event_date` (`email`, `event_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores user registration submissions for events.';

-- --------------------------------------------------------
-- Sample Data (Optional - for testing)
-- --------------------------------------------------------

-- Sample Event 1: Python Workshop
INSERT INTO `fossee_event_config` (`event_name`, `category`, `start_date`, `end_date`, `event_date`, `created`, `changed`) VALUES
('Python Workshop 2024', 'Workshop', UNIX_TIMESTAMP('2024-01-01 00:00:00'), UNIX_TIMESTAMP('2024-12-31 23:59:59'), UNIX_TIMESTAMP('2024-06-15 09:00:00'), UNIX_TIMESTAMP(NOW()), UNIX_TIMESTAMP(NOW()));

-- Sample Event 2: Data Science Seminar
INSERT INTO `fossee_event_config` (`event_name`, `category`, `start_date`, `end_date`, `event_date`, `created`, `changed`) VALUES
('Data Science Seminar', 'Seminar', UNIX_TIMESTAMP('2024-01-01 00:00:00'), UNIX_TIMESTAMP('2024-12-31 23:59:59'), UNIX_TIMESTAMP('2024-07-20 10:00:00'), UNIX_TIMESTAMP(NOW()), UNIX_TIMESTAMP(NOW()));

-- Sample Event 3: Open Source Conference
INSERT INTO `fossee_event_config` (`event_name`, `category`, `start_date`, `end_date`, `event_date`, `created`, `changed`) VALUES
('FOSS Conference 2024', 'Conference', UNIX_TIMESTAMP('2024-01-01 00:00:00'), UNIX_TIMESTAMP('2024-12-31 23:59:59'), UNIX_TIMESTAMP('2024-08-10 09:00:00'), UNIX_TIMESTAMP(NOW()), UNIX_TIMESTAMP(NOW()));

-- Sample Registration 1
INSERT INTO `fossee_event_registration` (`event_id`, `name`, `email`, `department`, `event_date`, `created`) VALUES
(1, 'John Doe', 'john.doe@example.com', 'Computer Science', UNIX_TIMESTAMP('2024-06-15 09:00:00'), UNIX_TIMESTAMP(NOW()));

-- Sample Registration 2
INSERT INTO `fossee_event_registration` (`event_id`, `name`, `email`, `department`, `event_date`, `created`) VALUES
(2, 'Jane Smith', 'jane.smith@example.com', 'Information Technology', UNIX_TIMESTAMP('2024-07-20 10:00:00'), UNIX_TIMESTAMP(NOW()));

-- --------------------------------------------------------
-- Notes for Evaluators:
-- --------------------------------------------------------
-- 
-- 1. These tables are automatically created by Drupal when you enable the module.
--    You do NOT need to run this SQL manually in a standard Drupal installation.
--
-- 2. To enable the module:
--    drush en fossee_event -y
--    drush cr
--
-- 3. The sample data above uses date ranges that may need adjustment for testing.
--    Modify the start_date and end_date values to current dates for active registration.
--
-- 4. The unique constraint on (email, event_date) prevents duplicate registrations.
--
-- --------------------------------------------------------
