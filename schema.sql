CREATE DATABASE IF NOT EXISTS mpesa_app CHARACTER SET utf8mb4;
USE mpesa_app;

CREATE TABLE IF NOT EXISTS payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_ref VARCHAR(40) NOT NULL,
  phone VARCHAR(15) NOT NULL,
  amount INT UNSIGNED NOT NULL,
  merchant_request_id VARCHAR(60) NULL,
  checkout_request_id VARCHAR(60) NULL UNIQUE,
  status ENUM('pending','success','failed','cancelled') NOT NULL DEFAULT 'pending',
  result_code INT NULL,
  result_desc VARCHAR(255) NULL,
  mpesa_receipt VARCHAR(30) NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Raw callbacks kept for audit/debugging
CREATE TABLE IF NOT EXISTS mpesa_callbacks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payload JSON NOT NULL,
  received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
