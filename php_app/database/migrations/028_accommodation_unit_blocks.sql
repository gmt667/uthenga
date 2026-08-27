-- Physical-room maintenance blocks reduce the pooled nightly ledger.
CREATE TABLE IF NOT EXISTS tie_accommodation_unit_blocks (
  id CHAR(36) NOT NULL PRIMARY KEY,
  property_id CHAR(36) NOT NULL,
  unit_id CHAR(36) NOT NULL,
  room_type_id BIGINT UNSIGNED NOT NULL,
  task_id CHAR(36) NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  status ENUM('ACTIVE','RELEASED','CANCELLED') NOT NULL DEFAULT 'ACTIVE',
  created_by VARCHAR(30) NOT NULL,
  released_by VARCHAR(30) NULL,
  released_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tie_accommodation_unit_task_block (unit_id, task_id),
  KEY idx_tie_accommodation_block_inventory (room_type_id, start_date, end_date, status),
  KEY idx_tie_accommodation_block_property (property_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
