-- ============================================================================
-- BEL Kotdwar Examination Portal — CONSOLIDATED SCHEMA
-- ----------------------------------------------------------------------------
-- This single file creates the entire database from scratch. All previous
-- upgrade scripts (schema_upgrade.sql, schema_upgrade_hindi.sql,
-- schema_upgrade_phase3.sql) are merged here. The app also keeps runtime
-- auto-migrations in includes/helpers.php (ensure_*_migrations) so you rarely
-- need to run this manually — but for a clean fresh install, use:
--
--   mysql -u root -p < schema.sql
--
-- or import via phpMyAdmin → Import tab.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS bel_exam_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bel_exam_portal;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS violations;
DROP TABLE IF EXISTS attempt_answers;
DROP TABLE IF EXISTS attempts;
DROP TABLE IF EXISTS question_options;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS exam_admin_access;
DROP TABLE IF EXISTS exam_assignments;
DROP TABLE IF EXISTS exams;
DROP TABLE IF EXISTS password_reset;
DROP TABLE IF EXISTS admin_activity_logs;
DROP TABLE IF EXISTS admin_permissions;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- USERS (admins + students)
-- ----------------------------------------------------------------------------
CREATE TABLE users (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  role             ENUM('admin','student') NOT NULL,
  name             VARCHAR(150) NOT NULL,
  email            VARCHAR(150) NOT NULL,
  username         VARCHAR(80)  NOT NULL,
  password_hash    VARCHAR(255) NOT NULL,
  plain_password   VARCHAR(80)  NULL,    -- students only (admin hall-ticket view). Always NULL for admins.
  roll_number      VARCHAR(80)  NULL,
  dob              DATE         NULL,
  category         ENUM('internal','external') NULL,
  photo_path       VARCHAR(255) NULL,
  is_super         TINYINT(1)   NOT NULL DEFAULT 0,
  created_by       INT          NULL,
  created_at       DATETIME     DEFAULT CURRENT_TIMESTAMP,
  -- Soft-delete columns
  deleted_at       DATETIME     NULL,
  deleted_by       INT          NULL,
  deleted_by_name  VARCHAR(150) NULL,
  deleted_by_email VARCHAR(150) NULL,
  UNIQUE KEY uq_email (email),
  UNIQUE KEY uq_username (username),
  UNIQUE KEY uq_roll (roll_number),
  KEY idx_role_deleted (role, deleted_at)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- ADMIN PERMISSIONS (super-admin configures per-admin rights)
-- ----------------------------------------------------------------------------
CREATE TABLE admin_permissions (
  admin_id          INT PRIMARY KEY,
  perms             TEXT NULL,             -- JSON matrix { students: {create,edit,delete}, ... }
  view_all_exams    TINYINT(1) NOT NULL DEFAULT 0,
  view_all_students TINYINT(1) NOT NULL DEFAULT 0,
  updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ap_u FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- EXAMS
-- ----------------------------------------------------------------------------
CREATE TABLE exams (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  exam_name         VARCHAR(200) NOT NULL,
  exam_code         VARCHAR(80)  NULL UNIQUE,
  duration_minutes  INT NOT NULL,
  max_attempts      INT NOT NULL DEFAULT 1,
  start_time        DATETIME NOT NULL,
  end_time          DATETIME NOT NULL,
  total_marks       INT  NULL,
  instructions      TEXT NULL,
  instructions_hi   TEXT NULL,              -- bilingual
  -- Per-exam proctor / violation switches (super-admin controlled, JSON)
  violation_config  TEXT NULL,
  force_fullscreen  TINYINT(1) NOT NULL DEFAULT 1,
  max_violations    INT        NOT NULL DEFAULT 5,
  created_by        INT NULL,
  created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
  -- Soft-delete
  deleted_at        DATETIME NULL,
  deleted_by        INT      NULL,
  deleted_by_name   VARCHAR(150) NULL,
  deleted_by_email  VARCHAR(150) NULL,
  KEY idx_window (start_time, end_time),
  KEY idx_exams_active (start_time, end_time, deleted_at)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- EXAM <-> STUDENT assignments
-- ----------------------------------------------------------------------------
CREATE TABLE exam_assignments (
  user_id     INT NOT NULL,
  exam_id     INT NOT NULL,
  assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, exam_id),
  KEY idx_assignments_exam (exam_id),
  CONSTRAINT fk_ea_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ea_e FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- EXAM <-> ADMIN access grants (granular super-admin → admin delegation)
-- ----------------------------------------------------------------------------
CREATE TABLE exam_admin_access (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  exam_id      INT NOT NULL,
  admin_id     INT NOT NULL,
  access_level ENUM('view','edit','full') NOT NULL DEFAULT 'view',
  granted_by   INT NULL,
  granted_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_exam_admin (exam_id, admin_id),
  KEY idx_admin (admin_id),
  KEY idx_exam  (exam_id),
  CONSTRAINT fk_eaa_exam FOREIGN KEY (exam_id)  REFERENCES exams(id) ON DELETE CASCADE,
  CONSTRAINT fk_eaa_adm  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- QUESTIONS (bilingual EN/HI)
-- ----------------------------------------------------------------------------
CREATE TABLE questions (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  exam_id           INT NOT NULL,
  question_type     ENUM('mcq','multi_select','true_false','short_answer','numeric') NOT NULL,
  question_text     TEXT NOT NULL,
  question_text_hi  TEXT NULL,              -- bilingual Hindi
  correct_text      TEXT NULL,
  correct_text_hi   TEXT NULL,              -- bilingual Hindi
  correct_numeric   DOUBLE NULL,
  correct_bool      TINYINT(1) NULL,
  marks             DECIMAL(6,2) NOT NULL DEFAULT 1,
  negative_marks    DECIMAL(6,2) NOT NULL DEFAULT 0,
  created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
  -- Soft-delete
  deleted_at        DATETIME NULL,
  deleted_by        INT      NULL,
  deleted_by_name   VARCHAR(150) NULL,
  deleted_by_email  VARCHAR(150) NULL,
  KEY idx_exam (exam_id),
  CONSTRAINT fk_q_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE question_options (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  question_id INT NOT NULL,
  opt_order   INT NOT NULL,
  opt_text    TEXT NOT NULL,
  opt_text_hi TEXT NULL,                    -- bilingual Hindi
  is_correct  TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at  DATETIME NULL,
  deleted_by  INT NULL,
  KEY idx_q (question_id),
  CONSTRAINT fk_opt_q FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- ATTEMPTS + ANSWERS
-- ----------------------------------------------------------------------------
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
  deleted_at   DATETIME NULL,
  deleted_by   INT NULL,
  KEY idx_user_exam (user_id, exam_id),
  KEY idx_status    (status),
  KEY idx_attempts_status (exam_id, status),
  CONSTRAINT fk_a_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_a_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE attempt_answers (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  attempt_id     INT NOT NULL,
  question_id    INT NOT NULL,
  selected_json  TEXT NULL,                  -- {"selected":[1,2]} | {"bool":true} | {"text":"..."} | {"numeric":3.14}
  marked_review  TINYINT(1) DEFAULT 0,
  is_correct     TINYINT(1) NULL,
  UNIQUE KEY uq_aq (attempt_id, question_id),
  CONSTRAINT fk_aa_att FOREIGN KEY (attempt_id) REFERENCES attempts(id) ON DELETE CASCADE,
  CONSTRAINT fk_aa_q   FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- VIOLATIONS (proctor log)
-- ----------------------------------------------------------------------------
CREATE TABLE violations (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  attempt_id  INT NOT NULL,
  user_id     INT NOT NULL,
  event_type  VARCHAR(64) NOT NULL,
  description VARCHAR(255) NULL,
  event_time  DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_att (attempt_id),
  KEY idx_violations_attempt (attempt_id, event_time),
  CONSTRAINT fk_v_att FOREIGN KEY (attempt_id) REFERENCES attempts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- PASSWORD RESET tokens
-- ----------------------------------------------------------------------------
CREATE TABLE password_reset (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  token      VARCHAR(80) NOT NULL UNIQUE,
  user_id    INT NOT NULL,
  expires_at DATETIME NOT NULL,
  used       TINYINT(1) DEFAULT 0,
  CONSTRAINT fk_pr_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- ADMIN ACTIVITY LOGS (audit trail)
-- ----------------------------------------------------------------------------
CREATE TABLE admin_activity_logs (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  admin_id       INT NULL,
  admin_name     VARCHAR(150) NOT NULL,
  admin_email    VARCHAR(150) NOT NULL,
  action         VARCHAR(80)  NOT NULL,
  details        TEXT NULL,
  page           VARCHAR(255) NULL,
  request_method VARCHAR(12)  NULL,
  ip_address     VARCHAR(45)  NULL,
  user_agent     VARCHAR(255) NULL,
  created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_admin   (admin_id),
  KEY idx_action  (action),
  KEY idx_created (created_at)
) ENGINE=InnoDB;

-- ============================================================================
-- SEED DATA — super admin
-- Default password: Admin@123  (bcrypt hashed)
-- On first login the application verifies against the bcrypt hash; if you change
-- ADMIN_PASSWORD in includes/config.php BEFORE first run, ensureSuperAdmin()
-- will use that value to seed instead.
-- ============================================================================
INSERT INTO users (role, name, email, username, password_hash, is_super, created_at)
VALUES ('admin',
        'BEL Kotdwar Super Admin',
        'admin@belkotdwar.in',
        'superadmin',
        '$2y$10$rLUYXqsA6Z5yrG1vQvQZruP0LhXh3x4fJOVt.bEEpJqqnl4ZsW2QW',
        1,
        NOW());
