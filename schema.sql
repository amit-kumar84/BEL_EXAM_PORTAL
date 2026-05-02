-- BEL Kotdwar Exam Portal - MySQL Schema
-- Run this ONCE via phpMyAdmin (Import tab) or:
--   mysql -u root -p < schema.sql

CREATE DATABASE IF NOT EXISTS bel_exam_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bel_exam_portal;

DROP TABLE IF EXISTS violations;
DROP TABLE IF EXISTS attempt_answers;
DROP TABLE IF EXISTS attempts;
DROP TABLE IF EXISTS question_options;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS exam_assignments;
DROP TABLE IF EXISTS exams;
DROP TABLE IF EXISTS password_reset;
DROP TABLE IF EXISTS admin_activity_logs;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  role           ENUM('admin','student') NOT NULL,
  name           VARCHAR(150) NOT NULL,
  email          VARCHAR(150) NOT NULL UNIQUE,
  username       VARCHAR(80)  NOT NULL UNIQUE,
  password_hash  VARCHAR(255) NOT NULL,
  plain_password VARCHAR(80)  NULL,    -- visible to admin (for hall ticket / sharing)
  roll_number    VARCHAR(80)  NULL,
  dob            DATE         NULL,
  category       ENUM('internal','external') NULL,
  photo_path     VARCHAR(255) NULL,
  is_super       TINYINT(1)   DEFAULT 0,
  created_by     INT          NULL,
  created_at     DATETIME     DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_roll (roll_number)
) ENGINE=InnoDB;

CREATE TABLE exams (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  exam_name         VARCHAR(200) NOT NULL,
  duration_minutes  INT NOT NULL,
  max_attempts      INT NOT NULL DEFAULT 1,
  start_time        DATETIME NOT NULL,
  end_time          DATETIME NOT NULL,
  total_marks       INT NULL,
  instructions      TEXT NULL,
  created_by        INT NULL,
  created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_window (start_time, end_time)
) ENGINE=InnoDB;

CREATE TABLE exam_assignments (
  user_id INT NOT NULL,
  exam_id INT NOT NULL,
  assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, exam_id),
  CONSTRAINT fk_ea_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ea_e FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE questions (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  exam_id         INT NOT NULL,
  question_type   ENUM('mcq','multi_select','true_false','short_answer','numeric') NOT NULL,
  question_text   TEXT NOT NULL,
  correct_text    TEXT NULL,
  correct_numeric DOUBLE NULL,
  correct_bool    TINYINT(1) NULL,
  marks           DECIMAL(6,2) NOT NULL DEFAULT 1,
  negative_marks  DECIMAL(6,2) NOT NULL DEFAULT 0,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_exam (exam_id),
  CONSTRAINT fk_q_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE question_options (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  question_id INT NOT NULL,
  opt_order   INT NOT NULL,
  opt_text    TEXT NOT NULL,
  is_correct  TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_q (question_id),
  CONSTRAINT fk_opt_q FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE attempts (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  exam_id      INT NOT NULL,
  attempt_no   INT NOT NULL DEFAULT 1,
  started_at   DATETIME NOT NULL,
  ends_at      DATETIME NOT NULL,
  submitted_at DATETIME NULL,
  status       ENUM('in_progress','submitted') NOT NULL DEFAULT 'in_progress',
  score        DECIMAL(8,2) NULL,
  total        DECIMAL(8,2) NULL,
  KEY idx_user_exam (user_id, exam_id),
  KEY idx_status (status),
  CONSTRAINT fk_a_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_a_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE attempt_answers (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  attempt_id     INT NOT NULL,
  question_id    INT NOT NULL,
  selected_json  TEXT NULL,      -- {"selected":[1,2]} or {"bool":true} or {"text":"..."} or {"numeric":3.14}
  marked_review  TINYINT(1) DEFAULT 0,
  is_correct     TINYINT(1) NULL,
  UNIQUE KEY uq_aq (attempt_id, question_id),
  CONSTRAINT fk_aa_att FOREIGN KEY (attempt_id) REFERENCES attempts(id) ON DELETE CASCADE,
  CONSTRAINT fk_aa_q   FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE violations (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  attempt_id  INT NOT NULL,
  user_id     INT NOT NULL,
  event_type  VARCHAR(64) NOT NULL,
  description VARCHAR(255) NULL,
  event_time  DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_att (attempt_id),
  CONSTRAINT fk_v_att FOREIGN KEY (attempt_id) REFERENCES attempts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE password_reset (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  token      VARCHAR(80) NOT NULL UNIQUE,
  user_id    INT NOT NULL,
  expires_at DATETIME NOT NULL,
  used       TINYINT(1) DEFAULT 0,
  CONSTRAINT fk_pr_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE admin_activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NULL,
  admin_name VARCHAR(150) NOT NULL,
  admin_email VARCHAR(150) NOT NULL,
  action VARCHAR(80) NOT NULL,
  details TEXT NULL,
  page VARCHAR(255) NULL,
  request_method VARCHAR(12) NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_admin (admin_id),
  KEY idx_action (action),
  KEY idx_created (created_at)
) ENGINE=InnoDB;

-- Seed super admin (password: Admin@123 — bcrypt hash)
INSERT INTO users (role, name, email, username, password_hash, is_super, created_at)
VALUES ('admin', 'BEL Kotdwar Super Admin', 'admin@belkotdwar.in', 'superadmin',
        '$2y$10$rLUYXqsA6Z5yrG1vQvQZruP0LhXh3x4fJOVt.bEEpJqqnl4ZsW2QW', 1, NOW());
-- NOTE: On first login with 'admin@belkotdwar.in' / 'Admin@123', if hash mismatch the app auto-reseeds via config.php.
