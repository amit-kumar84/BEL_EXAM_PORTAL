-- Schema upgrade for existing installs (run ONCE in phpMyAdmin if upgrading)
USE bel_exam_portal;

ALTER TABLE users ADD COLUMN plain_password VARCHAR(80) NULL AFTER password_hash;
ALTER TABLE users ADD COLUMN photo_path VARCHAR(255) NULL AFTER category;

CREATE TABLE IF NOT EXISTS exam_assignments (
  user_id INT NOT NULL,
  exam_id INT NOT NULL,
  assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, exam_id),
  CONSTRAINT fk_ea_u FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ea_e FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_activity_logs (
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
