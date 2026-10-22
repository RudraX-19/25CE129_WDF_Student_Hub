-- ============================================================================
-- StudentHub Portal - Practical 8 & 9 Database Schema & Seed Data
-- Course: Web Development Fundamentals (ITUE203)
-- Schema: studenthub_db
-- Practical 8 Features: 3NF Normalization, PK/FK Constraints, Indices,
--                       Seed Data, Stored Procedures (Advanced Extension)
-- Practical 9 Features: users table, password_hash(), duplicate checks,
--                       role-based access (admin/student), audit_log table
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `studenthub_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `studenthub_db`;

-- ----------------------------------------------------------------------------
-- 1. Table: courses (Lookup / Parent Table for Academic Programs)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `audit_log`;
DROP TABLE IF EXISTS `registrations`;
DROP TABLE IF EXISTS `contact_inquiries`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `courses`;
DROP TABLE IF EXISTS `users`;

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
    `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
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
    `poster_image` VARCHAR(255) DEFAULT NULL,
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

-- ----------------------------------------------------------------------------
-- 6. Table: audit_log (Advanced Extension: Security audit trail for events & admin actions)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_log` (
    `log_id` INT AUTO_INCREMENT PRIMARY KEY,
    `action` VARCHAR(50) NOT NULL,
    `table_name` VARCHAR(50) NOT NULL,
    `record_id` INT NOT NULL,
    `performed_by` VARCHAR(100) DEFAULT 'admin',
    `details` TEXT,
    `ip_address` VARCHAR(45) DEFAULT '127.0.0.1',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_audit_action` (`action`),
    INDEX `idx_audit_table_record` (`table_name`, `record_id`),
    INDEX `idx_audit_performed_by` (`performed_by`),
    INDEX `idx_audit_created_at` (`created_at`)
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
INSERT INTO `students` (`student_id`, `roll_no`, `fullname`, `email`, `mobile`, `course_id`, `year_of_study`, `gender`, `password_hash`, `gpa`, `status`, `is_deleted`) VALUES
(1,  '25CE129', 'Rudra Vaghasiya',  'rudra.vaghasiya@college.edu',  '9876543210', 1, 3, 'Male',   '$2y$10$e7m0tG1oMv0fFjH3UqJbOex5P4wH0rT9.n1rGzS5WqP9l0tM5u3yC', 8.90, 'Active',    0),
(2,  '25CS045', 'Ananya Shah',      'ananya.shah@college.edu',      '9823456789', 2, 2, 'Female', '$2y$10$w8k2rE4uXq9vZbK5LpQdTeu8W2rY6.a7xI0mPnQ1oT3uV7bX9z2vB', 9.20, 'Active',    0),
(3,  '25CE078', 'Rohan Mehta',      'rohan.mehta@college.edu',      '9811223344', 1, 4, 'Male',   '$2y$10$k1p9rT3vY0wAaB8CcDdEeFf1G2h3I4j5K6l7M8n9O0pQrStUvWxYz', 8.50, 'Active',    0),
(4,  '25PH012', 'Priya Sharma',     'priya.sharma@college.edu',     '9844556677', 4, 1, 'Female', '$2y$10$z9y8x7w6v5u4t3s2r1q0pOnMlKjIhGfEdCbAaZ9y8x7w6v5u4t3s2', 8.75, 'Active',    0),
(5,  '25MA033', 'Karan Dave',       'karan.dave@college.edu',       '9898012345', 3, 2, 'Male',   '$2y$10$GvoRH8fKe4YKaCW8jq31muDjxT9Nl3Y0hVl5kLz98xKaFWvrK4A0i', 7.95, 'Active',    0),
(6,  '25CE001', 'Aarav Patel',      'aarav.patel@college.edu',      '9876500001', 1, 1, 'Male',   '$2y$10$e7m0tG1oMv0fFjH3UqJbOex5P4wH0rT9.n1rGzS5WqP9l0tM5u3yC', 8.40, 'Active',    0),
(7,  '25CS014', 'Diya Trivedi',     'diya.trivedi@college.edu',     '9876500002', 2, 3, 'Female', '$2y$10$e7m0tG1oMv0fFjH3UqJbOex5P4wH0rT9.n1rGzS5WqP9l0tM5u3yC', 9.10, 'Active',    0),
(8,  '25CE042', 'Harsh Solanki',    'harsh.solanki@college.edu',    '9876500003', 1, 2, 'Male',   '$2y$10$e7m0tG1oMv0fFjH3UqJbOex5P4wH0rT9.n1rGzS5WqP9l0tM5u3yC', 7.65, 'Active',    0),
(9,  '25BI009', 'Sneha Desai',      'sneha.desai@college.edu',      '9876500004', 5, 2, 'Female', '$2y$10$e7m0tG1oMv0fFjH3UqJbOex5P4wH0rT9.n1rGzS5WqP9l0tM5u3yC', 8.85, 'Active',    0),
(10, '25AR003', 'Vikram Rathod',    'vikram.rathod@college.edu',    '9876500005', 6, 1, 'Male',   '$2y$10$e7m0tG1oMv0fFjH3UqJbOex5P4wH0rT9.n1rGzS5WqP9l0tM5u3yC', 8.20, 'Active',    0),
(11, '25CS089', 'Tanvi Parekh',     'tanvi.parekh@college.edu',     '9876500006', 2, 4, 'Female', '$2y$10$e7m0tG1oMv0fFjH3UqJbOex5P4wH0rT9.n1rGzS5WqP9l0tM5u3yC', 9.45, 'Graduated', 0),
(12, '25CE110', 'Manish Gadhvi',    'manish.gadhvi@college.edu',    '9876500007', 1, 3, 'Male',   '$2y$10$e7m0tG1oMv0fFjH3UqJbOex5P4wH0rT9.n1rGzS5WqP9l0tM5u3yC', 6.90, 'Suspended', 0),
(13, '25MA018', 'Kavita Iyer',      'kavita.iyer@college.edu',      '9876500008', 3, 3, 'Female', '$2y$10$e7m0tG1oMv0fFjH3UqJbOex5P4wH0rT9.n1rGzS5WqP9l0tM5u3yC', 8.60, 'Active',    0),
(14, '25PH025', 'Nikhil Verma',     'nikhil.verma@college.edu',     '9876500009', 4, 2, 'Male',   '$2y$10$e7m0tG1oMv0fFjH3UqJbOex5P4wH0rT9.n1rGzS5WqP9l0tM5u3yC', 7.80, 'Active',    0),
(15, '25CS061', 'Riddhi Bhatt',     'riddhi.bhatt@college.edu',     '9876500010', 2, 1, 'Female', '$2y$10$e7m0tG1oMv0fFjH3UqJbOex5P4wH0rT9.n1rGzS5WqP9l0tM5u3yC', 8.95, 'Active',    0),
(16, '25CE095', 'Yash Parmar',      'yash.parmar@college.edu',      '9876500011', 1, 4, 'Male',   '$2y$10$e7m0tG1oMv0fFjH3UqJbOex5P4wH0rT9.n1rGzS5WqP9l0tM5u3yC', 8.35, 'Graduated', 0),
(17, '25BI019', 'Pooja Chawla',     'pooja.chawla@college.edu',     '9876500012', 5, 3, 'Female', '$2y$10$e7m0tG1oMv0fFjH3UqJbOex5P4wH0rT9.n1rGzS5WqP9l0tM5u3yC', 8.15, 'Active',    0),
(18, '25AR011', 'Devansh Rawal',    'devansh.rawal@college.edu',    '9876500013', 6, 2, 'Male',   '$2y$10$e7m0tG1oMv0fFjH3UqJbOex5P4wH0rT9.n1rGzS5WqP9l0tM5u3yC', 7.50, 'Active',    0),
(19, '25CS102', 'Simran Kaur',      'simran.kaur@college.edu',      '9876500014', 2, 3, 'Female', '$2y$10$e7m0tG1oMv0fFjH3UqJbOex5P4wH0rT9.n1rGzS5WqP9l0tM5u3yC', 9.05, 'Active',    0),
(20, '25CE135', 'Deepak Chauhan',   'deepak.chauhan@college.edu',   '9876500015', 1, 2, 'Male',   '$2y$10$e7m0tG1oMv0fFjH3UqJbOex5P4wH0rT9.n1rGzS5WqP9l0tM5u3yC', 8.10, 'Active',    0),
(21, '25MA044', 'Meera Nambiar',    'meera.nambiar@college.edu',    '9876500016', 3, 4, 'Female', '$2y$10$e7m0tG1oMv0fFjH3UqJbOex5P4wH0rT9.n1rGzS5WqP9l0tM5u3yC', 9.30, 'Graduated', 0),
(22, '25PH034', 'Aditya Sen',       'aditya.sen@college.edu',       '9876500017', 4, 3, 'Male',   '$2y$10$e7m0tG1oMv0fFjH3UqJbOex5P4wH0rT9.n1rGzS5WqP9l0tM5u3yC', 7.90, 'Active',    0),
(23, '25BI028', 'Ishita Goswami',   'ishita.goswami@college.edu',   '9876500018', 5, 1, 'Female', '$2y$10$e7m0tG1oMv0fFjH3UqJbOex5P4wH0rT9.n1rGzS5WqP9l0tM5u3yC', 8.70, 'Active',    0),
(24, '25CS120', 'Chirag Vora',      'chirag.vora@college.edu',      '9876500019', 2, 4, 'Male',   '$2y$10$e7m0tG1oMv0fFjH3UqJbOex5P4wH0rT9.n1rGzS5WqP9l0tM5u3yC', 8.45, 'Graduated', 0),
(25, '25CE140', 'Bhavik Barot',     'bhavik.barot@college.edu',     '9876500020', 1, 1, 'Male',   '$2y$10$e7m0tG1oMv0fFjH3UqJbOex5P4wH0rT9.n1rGzS5WqP9l0tM5u3yC', 8.00, 'Active',    0);

-- Seed: Events
INSERT INTO `events` (`event_id`, `event_code`, `title`, `category`, `event_date`, `event_time`, `venue`, `organizer`, `max_participants`, `registered_count`, `status`, `description`, `poster_image`) VALUES
(1, 'EVT-TECH-01', 'Annual Hackathon 2026', 'Technical', '2026-11-15', '09:00:00', 'Central Computing Lab', 'Coding Club', 120, 2, 'Upcoming', '24-hour full-stack and AI hackathon for university students.', 'poster_hackathon_demo.jpg'),
(2, 'EVT-ROBO-02', 'RoboWars & Drone Racing', 'Technical', '2026-11-20', '10:30:00', 'University Sports Arena', 'Robotics Society', 80, 1, 'Upcoming', 'Autonomous line follower and wireless bot combat championships.', 'poster_robowars_demo.jpg'),
(3, 'EVT-CULT-03', 'Rhythm & Beats Cultural Fest', 'Cultural', '2026-12-05', '17:00:00', 'Open Air Amphitheatre', 'Student Council', 350, 1, 'Upcoming', 'Annual intra-college music, drama, and choreo fest.', 'poster_cultural_demo.jpg'),
(4, 'EVT-ACAD-04', 'Cloud & DevOps Workshop', 'Academic', '2026-11-10', '14:00:00', 'Seminar Hall A', 'CE Department', 60, 1, 'Upcoming', 'Hands-on masterclass on containerization, CI/CD, and cloud infra.', 'poster_cloud_demo.jpg'),
(5, 'EVT-SPRT-05', 'Inter-College Football Tournament', 'Sports', '2026-11-25', '08:00:00', 'University Main Stadium', 'Sports Committee', 200, 0, 'Upcoming', 'State-level football tournament with 16 participating university teams.', NULL),
(6, 'EVT-WORK-06', 'AI & Deep Learning Symposium', 'Workshop', '2026-12-12', '10:00:00', 'Auditorium Complex', 'IEEE Student Branch', 150, 0, 'Upcoming', 'Guest lectures and hands-on lab on LLMs, transformers, and neural architectures.', NULL);

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

-- ============================================================================
-- PRACTICAL 9: users table (standalone auth table), audit_log
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 6. Table: users (Standalone auth table for Practical 9 secure registration)
--    Supports role-based access: 'student' | 'admin'
--    password stored as bcrypt hash via password_hash()
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `user_id`        INT AUTO_INCREMENT PRIMARY KEY,
    `username`       VARCHAR(50)  NOT NULL UNIQUE,
    `email`          VARCHAR(150) NOT NULL UNIQUE,
    `password_hash`  VARCHAR(255) NOT NULL COMMENT 'bcrypt via password_hash()',
    `role`           ENUM('student','admin') NOT NULL DEFAULT 'student',
    `full_name`      VARCHAR(100) NOT NULL,
    `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `last_login_at`  TIMESTAMP    NULL DEFAULT NULL COMMENT 'Practical 10 Intermediate: updated on every login',
    `created_at`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Practical 9+10: Secure user registration, login, bcrypt hashing, RBAC';

CREATE INDEX `idx_users_email`    ON `users` (`email`);
CREATE INDEX `idx_users_username` ON `users` (`username`);
CREATE INDEX `idx_users_role`     ON `users` (`role`);

-- Seed: Admin user and Student user (password: Admin@123)
INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `full_name`) VALUES
('admin', 'admin@studenthub.edu',
 '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm',
 'admin', 'Portal Administrator'),
('25ce129', 'rudra.vaghasiya@college.edu',
 '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm',
 'student', 'Rudra Vaghasiya');

-- ----------------------------------------------------------------------------
-- 7. Table: remember_tokens (Persistent Login)
-- ----------------------------------------------------------------------------
--    Stores hashed remember-me tokens for persistent login.
--    Token is stored as SHA-256 hash — plaintext never stored.
--    Token rotated on each use to prevent replay attacks.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `remember_tokens` (
    `token_id`   INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT          NOT NULL,
    `token_hash` VARCHAR(64)  NOT NULL UNIQUE COMMENT 'SHA-256 hash of plaintext token',
    `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP    NOT NULL,
    CONSTRAINT `fk_remember_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `users` (`user_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Practical 10 Advanced: Persistent remember-me tokens (hashed, rotating)';

CREATE INDEX `idx_remember_user`   ON `remember_tokens` (`user_id`);
CREATE INDEX `idx_remember_expires` ON `remember_tokens` (`expires_at`);

-- ============================================================================
-- Seed Data: audit_log entries
-- ============================================================================

-- Seed: Initial audit_log entries for demonstration
INSERT IGNORE INTO `audit_log` (`log_id`, `action`, `table_name`, `record_id`, `performed_by`, `details`, `ip_address`) VALUES
(1,  'LOGIN',           'users',       1,  'admin',   'Admin user logged in',                           '127.0.0.1'),
(2,  'CREATE',          'students',    1,  'admin',   'Student Rudra Vaghasiya created (25CE129)',       '127.0.0.1'),
(3,  'CREATE',          'events',      1,  'admin',   'Event Annual Hackathon 2026 created',             '127.0.0.1'),
(4,  'UPDATE',          'events',      2,  'admin',   'Event RoboWars max_participants updated to 80',   '127.0.0.1'),
(5,  'CREATE',          'students',    2,  'admin',   'Student Ananya Shah created (25CS045)',           '127.0.0.1'),
(6,  'DELETE',          'registrations',4, 'admin',   'Registration REG-2026-EVT04 removed',            '127.0.0.1'),
(7,  'TOGGLE_STATUS',   'users',       2,  'admin',   'User student01 set to Inactive',                 '127.0.0.1'),
(8,  'TOGGLE_STATUS',   'users',       2,  'admin',   'User student01 set to Active',                   '127.0.0.1'),
(9,  'VIEW_DASHBOARD',  'admin_dashboard',0,'admin',  'Admin viewed dashboard',                         '127.0.0.1'),
(10, 'LOGIN',           'users',       1,  'admin',   'Admin session renewed via remember-me token',    '127.0.0.1');
