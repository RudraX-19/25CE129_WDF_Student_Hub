-- ============================================================================
-- StudentHub Portal - Practical 8 Database Schema & Seed Data
-- Course: Web Development Fundamentals (ITUE203)
-- Schema: studenthub_db
-- Features: 3NF Normalization, PK/FK Constraints, Indices, Seed Data,
--           and Stored Procedures (Advanced Extension)
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `studenthub_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `studenthub_db`;

-- ----------------------------------------------------------------------------
-- 1. Table: courses (Lookup / Parent Table for Academic Programs)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `registrations`;
DROP TABLE IF EXISTS `contact_inquiries`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `courses`;

CREATE TABLE `courses` (
    `course_id` INT AUTO_INCREMENT PRIMARY KEY,
    `course_code` VARCHAR(20) NOT NULL UNIQUE,
    `course_name` VARCHAR(100) NOT NULL,
    `department` VARCHAR(100) NOT NULL,
    `credits` INT NOT NULL DEFAULT 4,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Table: students (Child of courses, Parent of registrations)
-- ----------------------------------------------------------------------------
CREATE TABLE `students` (
    `student_id` INT AUTO_INCREMENT PRIMARY KEY,
    `roll_no` VARCHAR(30) NOT NULL UNIQUE,
    `fullname` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `mobile` VARCHAR(15) NOT NULL,
    `course_id` INT NOT NULL,
    `year_of_study` INT NOT NULL,
    `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `gpa` DECIMAL(3,2) DEFAULT 0.00,
    `status` ENUM('Active', 'Graduated', 'Suspended') DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_students_course`
        FOREIGN KEY (`course_id`)
        REFERENCES `courses` (`course_id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT `chk_year_range`
        CHECK (`year_of_study` BETWEEN 1 AND 4)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_students_roll` ON `students` (`roll_no`);
CREATE INDEX `idx_students_email` ON `students` (`email`);

-- ----------------------------------------------------------------------------
-- 3. Table: events (Campus & Academic Events Catalog)
-- ----------------------------------------------------------------------------
CREATE TABLE `events` (
    `event_id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_code` VARCHAR(30) NOT NULL UNIQUE,
    `title` VARCHAR(150) NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `event_date` DATE NOT NULL,
    `event_time` TIME NOT NULL,
    `venue` VARCHAR(100) NOT NULL,
    `organizer` VARCHAR(100) NOT NULL,
    `max_participants` INT NOT NULL DEFAULT 100,
    `registered_count` INT NOT NULL DEFAULT 0,
    `status` ENUM('Upcoming', 'Ongoing', 'Completed', 'Cancelled') DEFAULT 'Upcoming',
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_events_date` ON `events` (`event_date`);
CREATE INDEX `idx_events_category` ON `events` (`category`);

-- ----------------------------------------------------------------------------
-- 4. Table: registrations (Join Table for M:N relationship: students <-> events)
-- ----------------------------------------------------------------------------
CREATE TABLE `registrations` (
    `registration_id` INT AUTO_INCREMENT PRIMARY KEY,
    `reg_code` VARCHAR(40) NOT NULL UNIQUE,
    `student_id` INT NOT NULL,
    `event_id` INT NOT NULL,
    `registration_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `attendance_status` ENUM('Registered', 'Attended', 'Absent', 'Cancelled') DEFAULT 'Registered',
    `notes` VARCHAR(255) DEFAULT NULL,
    CONSTRAINT `fk_registrations_student`
        FOREIGN KEY (`student_id`)
        REFERENCES `students` (`student_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT `fk_registrations_event`
        FOREIGN KEY (`event_id`)
        REFERENCES `events` (`event_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT `uq_student_event`
        UNIQUE (`student_id`, `event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_reg_student` ON `registrations` (`student_id`);
CREATE INDEX `idx_reg_event` ON `registrations` (`event_id`);

-- ----------------------------------------------------------------------------
-- 5. Table: contact_inquiries (Portal Feedback & Queries)
-- ----------------------------------------------------------------------------
CREATE TABLE `contact_inquiries` (
    `inquiry_id` INT AUTO_INCREMENT PRIMARY KEY,
    `ticket_code` VARCHAR(40) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `subject` VARCHAR(100) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('Pending Review', 'In Progress', 'Resolved', 'Closed') DEFAULT 'Pending Review',
    `ip_address` VARCHAR(45) DEFAULT '127.0.0.1',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SEED DATA
-- ============================================================================

-- Seed: Courses
INSERT INTO `courses` (`course_id`, `course_code`, `course_name`, `department`, `credits`) VALUES
(1, 'CE', 'Computer Engineering', 'Department of Computer Engineering', 4),
(2, 'CS', 'Computer Science', 'Department of Computer Science', 4),
(3, 'MATH', 'Mathematics & Computing', 'Department of Mathematics', 3),
(4, 'PHYSICS', 'Applied Physics', 'Department of Physical Sciences', 3),
(5, 'BIOLOGY', 'Bioinformatics & Biotechnology', 'Department of Life Sciences', 3),
(6, 'ARTS', 'Digital Arts & Humanities', 'Department of Humanities', 2);

-- Seed: Students
INSERT INTO `students` (`student_id`, `roll_no`, `fullname`, `email`, `mobile`, `course_id`, `year_of_study`, `gender`, `password_hash`, `gpa`, `status`) VALUES
(1, '25CE129', 'Rudra Vaghasiya', 'rudra.vaghasiya@college.edu', '9876543210', 1, 3, 'Male', '$2y$10$e7m0tG1oMv0fFjH3UqJbOex5P4wH0rT9.n1rGzS5WqP9l0tM5u3yC', 8.90, 'Active'),
(2, '25CS045', 'Ananya Shah', 'ananya.shah@college.edu', '9823456789', 2, 2, 'Female', '$2y$10$w8k2rE4uXq9vZbK5LpQdTeu8W2rY6.a7xI0mPnQ1oT3uV7bX9z2vB', 9.20, 'Active'),
(3, '25CE078', 'Rohan Mehta', 'rohan.mehta@college.edu', '9811223344', 1, 4, 'Male', '$2y$10$k1p9rT3vY0wAaB8CcDdEeFf1G2h3I4j5K6l7M8n9O0pQrStUvWxYz', 8.50, 'Active'),
(4, '25PH012', 'Priya Sharma', 'priya.sharma@college.edu', '9844556677', 4, 1, 'Female', '$2y$10$z9y8x7w6v5u4t3s2r1q0pOnMlKjIhGfEdCbAaZ9y8x7w6v5u4t3s2', 8.75, 'Active'),
(5, '25MA033', 'Karan Dave', 'karan.dave@college.edu', '9898012345', 3, 2, 'Male', '$2y$10$GvoRH8fKe4YKaCW8jq31muDjxT9Nl3Y0hVl5kLz98xKaFWvrK4A0i', 7.95, 'Active');

-- Seed: Events
INSERT INTO `events` (`event_id`, `event_code`, `title`, `category`, `event_date`, `event_time`, `venue`, `organizer`, `max_participants`, `registered_count`, `status`, `description`) VALUES
(1, 'EVT-TECH-01', 'Annual Hackathon 2026', 'Technical', '2026-11-15', '09:00:00', 'Central Computing Lab', 'Coding Club', 120, 2, 'Upcoming', '24-hour full-stack and AI hackathon for university students.'),
(2, 'EVT-ROBO-02', 'RoboWars & Drone Racing', 'Technical', '2026-11-20', '10:30:00', 'University Sports Arena', 'Robotics Society', 80, 1, 'Upcoming', 'Autonomous line follower and wireless bot combat championships.'),
(3, 'EVT-CULT-03', 'Rhythm & Beats Cultural Fest', 'Cultural', '2026-12-05', '17:00:00', 'Open Air Amphitheatre', 'Student Council', 350, 1, 'Upcoming', 'Annual intra-college music, drama, and choreo fest.'),
(4, 'EVT-ACAD-04', 'Cloud & DevOps Workshop', 'Academic', '2026-11-10', '14:00:00', 'Seminar Hall A', 'CE Department', 60, 1, 'Upcoming', 'Hands-on masterclass on containerization, CI/CD, and cloud infra.');

-- Seed: Registrations
INSERT INTO `registrations` (`registration_id`, `reg_code`, `student_id`, `event_id`, `attendance_status`, `notes`) VALUES
(1, 'REG-2026-EVT01', 1, 1, 'Registered', 'Lead full-stack developer'),
(2, 'REG-2026-EVT02', 2, 1, 'Registered', 'UI/UX specialist'),
(3, 'REG-2026-EVT03', 1, 4, 'Registered', 'CE semester 3 student'),
(4, 'REG-2026-EVT04', 3, 2, 'Registered', 'Bot chassis pilot'),
(5, 'REG-2026-EVT05', 4, 3, 'Registered', 'Vocal performer');

-- Seed: Contact Inquiries
INSERT INTO `contact_inquiries` (`inquiry_id`, `ticket_code`, `name`, `email`, `subject`, `message`, `status`) VALUES
(1, 'TKT-2026-1001', 'Dev Patel', 'dev.patel@college.edu', 'Academic Enquiry', 'When will the midterm examination schedule for Computer Engineering Semester 3 be released?', 'Resolved'),
(2, 'TKT-2026-1002', 'Isha Joshi', 'isha.joshi@college.edu', 'Technical Support', 'Unable to download syllabus PDF from the Course Details module. Please assist.', 'In Progress'),
(3, 'TKT-2026-1003', 'Karan Dave', 'karan.dave@college.edu', 'Admission Related', 'Inquiry regarding document verification dates for lateral entry candidates.', 'Pending Review');

-- ============================================================================
-- ADVANCED EXTENSION: STORED PROCEDURES
-- ============================================================================

DROP PROCEDURE IF EXISTS `sp_register_student_for_event`;
DROP PROCEDURE IF EXISTS `sp_get_student_summary`;
DROP PROCEDURE IF EXISTS `sp_get_upcoming_events`;

DELIMITER //

-- 1. Stored Procedure: Register Student for an Event with Capacity and Duplicate Check
CREATE PROCEDURE `sp_register_student_for_event`(
    IN  p_student_id INT,
    IN  p_event_id   INT,
    IN  p_notes      VARCHAR(255),
    OUT p_status     VARCHAR(50),
    OUT p_message    VARCHAR(255),
    OUT p_reg_code   VARCHAR(40)
)
proc_body: BEGIN
    DECLARE v_already_reg INT DEFAULT 0;
    DECLARE v_event_exists INT DEFAULT 0;
    DECLARE v_max_cap INT DEFAULT 0;
    DECLARE v_cur_cap INT DEFAULT 0;
    DECLARE v_student_active INT DEFAULT 0;
    DECLARE v_generated_code VARCHAR(40);

    -- Exit handler for rollback on unexpected SQL error
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_status  = 'ERROR';
        SET p_message = 'An unexpected SQL error occurred during event registration.';
        SET p_reg_code = NULL;
    END;

    START TRANSACTION;

    -- Verify Student exists and is Active
    SELECT COUNT(*) INTO v_student_active
    FROM `students`
    WHERE `student_id` = p_student_id AND `status` = 'Active';

    IF v_student_active = 0 THEN
        SET p_status   = 'FAIL';
        SET p_message  = 'Student does not exist or account is inactive.';
        SET p_reg_code = NULL;
        ROLLBACK;
        LEAVE proc_body;
    END IF;

    -- Verify Event exists and capacity
    SELECT COUNT(*), IFNULL(MAX(`max_participants`), 0), IFNULL(MAX(`registered_count`), 0)
    INTO v_event_exists, v_max_cap, v_cur_cap
    FROM `events`
    WHERE `event_id` = p_event_id AND `status` = 'Upcoming';

    IF v_event_exists = 0 THEN
        SET p_status   = 'FAIL';
        SET p_message  = 'Event not found or registration is closed.';
        SET p_reg_code = NULL;
        ROLLBACK;
        LEAVE proc_body;
    END IF;

    -- Check if seat is available
    IF v_cur_cap >= v_max_cap THEN
        SET p_status   = 'FAIL';
        SET p_message  = 'Event registration is fully booked.';
        SET p_reg_code = NULL;
        ROLLBACK;
        LEAVE proc_body;
    END IF;

    -- Check for duplicate registration
    SELECT COUNT(*) INTO v_already_reg
    FROM `registrations`
    WHERE `student_id` = p_student_id AND `event_id` = p_event_id;

    IF v_already_reg > 0 THEN
        SET p_status   = 'FAIL';
        SET p_message  = 'Student is already registered for this event.';
        SET p_reg_code = NULL;
        ROLLBACK;
        LEAVE proc_body;
    END IF;

    -- Generate unique registration code
    SET v_generated_code = CONCAT('REG-', YEAR(CURDATE()), '-', LPAD(FLOOR(RAND() * 90000) + 10000, 5, '0'));

    -- Insert registration
    INSERT INTO `registrations` (`reg_code`, `student_id`, `event_id`, `attendance_status`, `notes`)
    VALUES (v_generated_code, p_student_id, p_event_id, 'Registered', p_notes);

    -- Increment event participant counter
    UPDATE `events`
    SET `registered_count` = `registered_count` + 1
    WHERE `event_id` = p_event_id;

    COMMIT;

    SET p_status   = 'SUCCESS';
    SET p_message  = 'Student successfully registered for event.';
    SET p_reg_code = v_generated_code;
END //

-- 2. Stored Procedure: Get Student Academic & Extracurricular Summary
CREATE PROCEDURE `sp_get_student_summary`(
    IN p_student_id INT
)
BEGIN
    SELECT 
        s.`student_id`,
        s.`roll_no`,
        s.`fullname`,
        s.`email`,
        s.`mobile`,
        c.`course_name`,
        c.`department`,
        s.`year_of_study`,
        s.`gender`,
        s.`gpa`,
        s.`status`,
        COUNT(r.`registration_id`) AS `total_events_registered`,
        SUM(CASE WHEN r.`attendance_status` = 'Attended' THEN 1 ELSE 0 END) AS `events_attended`
    FROM `students` s
    INNER JOIN `courses` c ON s.`course_id` = c.`course_id`
    LEFT JOIN `registrations` r ON s.`student_id` = r.`student_id`
    WHERE s.`student_id` = p_student_id
    GROUP BY s.`student_id`, c.`course_id`;
END //

-- 3. Stored Procedure: Fetch All Active Upcoming Events with Seat Availability
CREATE PROCEDURE `sp_get_upcoming_events`()
BEGIN
    SELECT 
        `event_id`,
        `event_code`,
        `title`,
        `category`,
        `event_date`,
        `event_time`,
        `venue`,
        `organizer`,
        `max_participants`,
        `registered_count`,
        (`max_participants` - `registered_count`) AS `available_seats`,
        `status`
    FROM `events`
    WHERE `status` = 'Upcoming' AND `event_date` >= CURDATE()
    ORDER BY `event_date` ASC, `event_time` ASC;
END //

DELIMITER ;
