-- =============================================================================
-- Migration 012: Shop Module
-- Uthenga Marketplace - Additive only, no breaking changes
-- Adds tables for shop catalog, cart, orders, riders, delivery, payments,
-- and shop settings. Designed for future suppliers, warehouses, discounts,
-- coupons, loyalty, reviews, and multi-partner logistics.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS shop_categories (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id     BIGINT UNSIGNED NULL,
  name          VARCHAR(160) NOT NULL,
  slug          VARCHAR(180) NOT NULL,
  description   VARCHAR(500) NULL,
  icon          VARCHAR(80) NULL,
  image_url     VARCHAR(500) NULL,
  sort_order    INT NOT NULL DEFAULT 0,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_by    VARCHAR(30) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_shop_categories_slug (slug),
  KEY idx_shop_categories_parent (parent_id),
  KEY idx_shop_categories_active (is_active),
  CONSTRAINT fk_shop_categories_parent FOREIGN KEY (parent_id) REFERENCES shop_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_shop_categories_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS shop_suppliers (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supplier_code VARCHAR(32) NOT NULL,
  name          VARCHAR(180) NOT NULL,
  phone_number  VARCHAR(30) NULL,
  email         VARCHAR(180) NULL,
  address       VARCHAR(300) NULL,
  status        ENUM('active','inactive','pending') NOT NULL DEFAULT 'active',
  notes         TEXT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_shop_suppliers_code (supplier_code),
  KEY idx_shop_suppliers_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS shop_warehouses (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  warehouse_code VARCHAR(32) NOT NULL,
  name          VARCHAR(180) NOT NULL,
  location      VARCHAR(300) NULL,
  phone_number  VARCHAR(30) NULL,
  status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_shop_warehouses_code (warehouse_code),
  KEY idx_shop_warehouses_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS shop_products (
  id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id           BIGINT UNSIGNED NULL,
  supplier_id           BIGINT UNSIGNED NULL,
  warehouse_id          BIGINT UNSIGNED NULL,
  sku                   VARCHAR(60) NOT NULL,
  name                  VARCHAR(220) NOT NULL,
  slug                  VARCHAR(240) NOT NULL,
  short_description     VARCHAR(500) NULL,
  description           LONGTEXT NULL,
  brand                 VARCHAR(120) NULL,
  unit_label            VARCHAR(60) NULL,
  price                 DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  compare_at_price       DECIMAL(15,2) NULL,
  cost_price            DECIMAL(15,2) NULL,
  stock_quantity        INT NOT NULL DEFAULT 0,
  low_stock_threshold   INT NOT NULL DEFAULT 5,
  primary_image_url     VARCHAR(500) NULL,
  secondary_image_url   VARCHAR(500) NULL,
  weight_kg             DECIMAL(10,3) NULL,
  tax_rate_percent      DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  delivery_fee_override DECIMAL(15,2) NULL,
  is_featured           TINYINT(1) NOT NULL DEFAULT 0,
  is_new_arrival        TINYINT(1) NOT NULL DEFAULT 0,
  is_best_seller        TINYINT(1) NOT NULL DEFAULT 0,
  is_promotion          TINYINT(1) NOT NULL DEFAULT 0,
  promotion_label       VARCHAR(120) NULL,
  requires_age_verification TINYINT(1) NOT NULL DEFAULT 0,
  status                ENUM('active','draft','archived','out_of_stock') NOT NULL DEFAULT 'active',
  meta                  JSON NULL,
  created_by            VARCHAR(30) NULL,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at            DATETIME NULL,
  UNIQUE KEY uq_shop_products_sku (sku),
  UNIQUE KEY uq_shop_products_slug (slug),
  KEY idx_shop_products_category (category_id),
  KEY idx_shop_products_supplier (supplier_id),
  KEY idx_shop_products_warehouse (warehouse_id),
  KEY idx_shop_products_status (status),
  KEY idx_shop_products_featured (is_featured, is_best_seller, is_new_arrival),
  KEY idx_shop_products_stock (stock_quantity),
  CONSTRAINT fk_shop_products_category FOREIGN KEY (category_id) REFERENCES shop_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_shop_products_supplier FOREIGN KEY (supplier_id) REFERENCES shop_suppliers(id) ON DELETE SET NULL,
  CONSTRAINT fk_shop_products_warehouse FOREIGN KEY (warehouse_id) REFERENCES shop_warehouses(id) ON DELETE SET NULL,
  CONSTRAINT fk_shop_products_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS shop_product_images (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id    BIGINT UNSIGNED NOT NULL,
  image_url     VARCHAR(500) NOT NULL,
  alt_text      VARCHAR(200) NULL,
  sort_order    INT NOT NULL DEFAULT 0,
  is_primary    TINYINT(1) NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_shop_product_images_product (product_id),
  KEY idx_shop_product_images_primary (product_id, is_primary),
  CONSTRAINT fk_shop_product_images_product FOREIGN KEY (product_id) REFERENCES shop_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS shop_cart_items (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        VARCHAR(30) NULL,
  session_token  VARCHAR(80) NOT NULL,
  product_id     BIGINT UNSIGNED NOT NULL,
  quantity       INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_shop_cart_items_cart_product (session_token, product_id),
  KEY idx_shop_cart_items_user (user_id),
  KEY idx_shop_cart_items_product (product_id),
  CONSTRAINT fk_shop_cart_items_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_shop_cart_items_product FOREIGN KEY (product_id) REFERENCES shop_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS delivery_riders (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rider_code       VARCHAR(32) NOT NULL,
  name             VARCHAR(180) NOT NULL,
  phone_number     VARCHAR(30) NOT NULL,
  bike_registration VARCHAR(60) NULL,
  availability     ENUM('available','busy','offline','inactive') NOT NULL DEFAULT 'available',
  status           ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  user_id          VARCHAR(30) NULL,
  current_location VARCHAR(220) NULL,
  delivery_history_count INT UNSIGNED NOT NULL DEFAULT 0,
  notes            TEXT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_delivery_riders_code (rider_code),
  KEY idx_delivery_riders_availability (availability),
  KEY idx_delivery_riders_status (status),
  CONSTRAINT fk_delivery_riders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS shop_orders (
  id                     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number          VARCHAR(36) NOT NULL,
  user_id               VARCHAR(30) NULL,
  customer_name         VARCHAR(150) NOT NULL,
  customer_email        VARCHAR(180) NOT NULL,
  customer_phone        VARCHAR(30) NOT NULL,
  delivery_address      VARCHAR(300) NOT NULL,
  delivery_instructions TEXT NULL,
  preferred_delivery_time VARCHAR(120) NULL,
  subtotal              DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  delivery_fee          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  discount_amount       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  tax_amount            DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  total_amount          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  currency              CHAR(3) NOT NULL DEFAULT 'MWK',
  payment_method        ENUM('cash_on_delivery','bank_transfer','tnm_mpamba','airtel_money','paychangu') NOT NULL DEFAULT 'cash_on_delivery',
  payment_status        ENUM('pending','authorized','paid','failed','refunded','partially_paid') NOT NULL DEFAULT 'pending',
  order_status          ENUM('pending','confirmed','preparing','assigned_to_rider','out_for_delivery','delivered','cancelled') NOT NULL DEFAULT 'pending',
  fulfillment_status    ENUM('pending','packed','dispatched','completed','cancelled') NOT NULL DEFAULT 'pending',
  assigned_rider_id     BIGINT UNSIGNED NULL,
  payment_reference     VARCHAR(120) NULL,
  notes                 TEXT NULL,
  session_token         VARCHAR(80) NULL,
  placed_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  confirmed_at          DATETIME NULL,
  prepared_at           DATETIME NULL,
  dispatched_at         DATETIME NULL,
  delivered_at          DATETIME NULL,
  cancelled_at          DATETIME NULL,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_shop_orders_number (order_number),
  KEY idx_shop_orders_user (user_id),
  KEY idx_shop_orders_status (order_status, payment_status),
  KEY idx_shop_orders_rider (assigned_rider_id),
  KEY idx_shop_orders_placed (placed_at),
  CONSTRAINT fk_shop_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_shop_orders_rider FOREIGN KEY (assigned_rider_id) REFERENCES delivery_riders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS shop_order_items (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id      BIGINT UNSIGNED NOT NULL,
  product_id    BIGINT UNSIGNED NULL,
  product_name  VARCHAR(220) NOT NULL,
  sku           VARCHAR(60) NULL,
  unit_price    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  quantity      INT UNSIGNED NOT NULL DEFAULT 1,
  line_total    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  image_url     VARCHAR(500) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_shop_order_items_order (order_id),
  KEY idx_shop_order_items_product (product_id),
  CONSTRAINT fk_shop_order_items_order FOREIGN KEY (order_id) REFERENCES shop_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_shop_order_items_product FOREIGN KEY (product_id) REFERENCES shop_products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS shop_deliveries (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id         BIGINT UNSIGNED NOT NULL,
  rider_id         BIGINT UNSIGNED NOT NULL,
  delivery_status  ENUM('assigned','picked_up','out_for_delivery','delivered','failed','cancelled') NOT NULL DEFAULT 'assigned',
  assigned_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  dispatched_at    DATETIME NULL,
  delivered_at     DATETIME NULL,
  completion_notes TEXT NULL,
  tracking_meta    JSON NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_shop_deliveries_order (order_id),
  KEY idx_shop_deliveries_rider (rider_id),
  KEY idx_shop_deliveries_status (delivery_status),
  CONSTRAINT fk_shop_deliveries_order FOREIGN KEY (order_id) REFERENCES shop_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_shop_deliveries_rider FOREIGN KEY (rider_id) REFERENCES delivery_riders(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS shop_payments (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id         BIGINT UNSIGNED NOT NULL,
  payment_method   ENUM('cash_on_delivery','bank_transfer','tnm_mpamba','airtel_money','paychangu') NOT NULL,
  provider         VARCHAR(80) NULL,
  payment_reference VARCHAR(120) NULL,
  amount           DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  currency         CHAR(3) NOT NULL DEFAULT 'MWK',
  payment_status   ENUM('pending','processing','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  gateway_payload  JSON NULL,
  paid_at          DATETIME NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_shop_payments_order (order_id),
  KEY idx_shop_payments_status (payment_status),
  CONSTRAINT fk_shop_payments_order FOREIGN KEY (order_id) REFERENCES shop_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS shop_settings (
  setting_key   VARCHAR(120) NOT NULL PRIMARY KEY,
  setting_value LONGTEXT NOT NULL,
  value_type    ENUM('string','number','boolean','json') NOT NULL DEFAULT 'string',
  description   VARCHAR(255) NULL,
  updated_by    VARCHAR(30) NULL,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_shop_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO shop_settings (setting_key, setting_value, value_type, description) VALUES
('shop_name', 'Uthenga Drinks Shop', 'string', 'Public-facing shop name'),
('shop_tagline', 'Malawi beers, spirits, soft drinks, and chilled imports', 'string', 'Marketing tagline'),
('delivery_fee_mwk', '1500', 'number', 'Default delivery fee in MWK'),
('free_delivery_threshold_mwk', '25000', 'number', 'Subtotal threshold for free delivery'),
('tax_rate_percent', '0', 'number', 'Default tax percentage'),
('order_hold_minutes', '15', 'number', 'Minutes to hold a pending order'),
('cod_enabled', '1', 'boolean', 'Enable cash on delivery'),
('bank_transfer_enabled', '1', 'boolean', 'Enable bank transfer'),
('tnm_mpamba_enabled', '1', 'boolean', 'Enable TNM Mpamba'),
('airtel_money_enabled', '1', 'boolean', 'Enable Airtel Money'),
('paychangu_enabled', '0', 'boolean', 'Enable PayChangu gateway later');

INSERT IGNORE INTO shop_categories (id, name, slug, description, sort_order, is_active) VALUES
(1, 'Wine', 'wine', 'Red, white, sparkling, and special occasion wines.', 10, 1),
(2, 'Beer', 'beer', 'Lagers, stout, and seasonal beers.', 20, 1),
(3, 'Spirits', 'spirits', 'Whisky, gin, vodka, rum, and more.', 30, 1),
(4, 'Soft Drinks', 'soft-drinks', 'Sodas and fizzy refreshments.', 40, 1),
(5, 'Water', 'water', 'Still and sparkling bottled water.', 50, 1),
(6, 'Juice', 'juice', 'Fruit juices and nectar drinks.', 60, 1),
(7, 'Energy Drinks', 'energy-drinks', 'Performance and focus beverages.', 70, 1),
(8, 'Ciders & RTDs', 'ciders-rtds', 'Ready-to-drink ciders, sparkling cocktails, and aperitifs.', 80, 1);

INSERT IGNORE INTO delivery_riders (id, rider_code, name, phone_number, bike_registration, availability, status, current_location) VALUES
(1, 'RDR-0001', 'Amon Phiri', '+265 888 100 101', 'MMB 4012', 'available', 'active', 'Blantyre'),
(2, 'RDR-0002', 'Blessings Banda', '+265 888 100 102', 'MMB 5023', 'busy', 'active', 'Limbe'),
(3, 'RDR-0003', 'Tione Chirwa', '+265 888 100 103', NULL, 'available', 'active', 'Area 10');

INSERT IGNORE INTO shop_suppliers (id, supplier_code, name, phone_number, status) VALUES
(1, 'SUP-0001', 'Uthenga Direct Stock', '+265 111 000 100', 'active');

INSERT IGNORE INTO shop_warehouses (id, warehouse_code, name, location, status) VALUES
(1, 'WH-0001', 'Central Dispatch Store', 'Blantyre', 'active');

INSERT IGNORE INTO shop_products (id, category_id, supplier_id, warehouse_id, sku, name, slug, short_description, description, brand, unit_label, price, compare_at_price, stock_quantity, low_stock_threshold, primary_image_url, secondary_image_url, is_featured, is_new_arrival, is_best_seller, is_promotion, promotion_label, requires_age_verification, status) VALUES
(1, 3, 1, 1, 'GIN-MW-750', 'Malawi Gin 750ml', 'malawi-gin-750ml', 'Flagship local gin for mixed drinks and celebrations.', 'Official Malawi Gin bottle with a crisp botanical profile for mixers, event bars, and premium home service.', 'Malawi Gin', '750ml bottle', 27000, 29500, 36, 8, 'https://castelmalawi.com/storage/2025/02/Packshots_0004_Malawi-gin-1.png', 'https://castelmalawi.com/storage/2025/02/Spirits-of-Malawi-Babies_0002_Gin-3.0-1-1.png', 1, 1, 1, 0, NULL, 1, 'active'),
(2, 3, 1, 1, 'GIN-MW-330', 'Malawi Gin 330ml', 'malawi-gin-330ml', 'Smaller bottle for quick service and cooler packs.', 'A compact Malawi Gin bottle for events, bars, and fast-moving retail shelves.', 'Malawi Gin', '330ml bottle', 7200, NULL, 72, 16, 'https://castelmalawi.com/storage/2025/02/Spirits-of-Malawi-Babies_0002_Gin-3.0-1-1.png', 'https://castelmalawi.com/storage/2025/02/Packshots_0004_Malawi-gin-1.png', 0, 1, 1, 0, NULL, 1, 'active'),
(3, 3, 1, 1, 'BRANDY-MW-750', 'Premier Brandy 750ml', 'premier-brandy-750ml', 'A smooth local brandy for gifting and premium pours.', 'Premier Brandy in a full-size bottle for home bars, events, and premium delivery orders.', 'Premier Brandy', '750ml bottle', 33000, 35000, 28, 6, 'https://castelmalawi.com/storage/2025/02/bigbrandy.png', 'https://castelmalawi.com/storage/2025/02/Spirits-of-Malawi-Babies_0000_Brandy-3.0-1.png', 1, 1, 0, 1, 'Launch offer', 1, 'active'),
(4, 3, 1, 1, 'BRANDY-MW-330', 'Premier Brandy 330ml', 'premier-brandy-330ml', 'Small bottle for minibars and cooler packs.', 'A smaller Premier Brandy bottle for quick service, event stocking, and compact delivery orders.', 'Premier Brandy', '330ml bottle', 11500, NULL, 54, 12, 'https://castelmalawi.com/storage/2025/02/Spirits-of-Malawi-Babies_0000_Brandy-3.0-1.png', 'https://castelmalawi.com/storage/2025/02/bigbrandy.png', 0, 1, 1, 0, NULL, 1, 'active'),
(5, 3, 1, 1, 'VODKA-MW-750', 'Malawi Vodka 750ml', 'malawi-vodka-750ml', 'Clean local vodka for mixed drinks and events.', 'A classic Malawi Vodka bottle for premium mixers and delivery-ready bar stock.', 'Malawi Vodka', '750ml bottle', 26500, 27900, 30, 8, 'https://castelmalawi.com/storage/2025/02/Packshots_0003_Malawi-vodka-1.png', 'https://castelmalawi.com/storage/2025/02/Spirits-of-Malawi-Babies_0001_Vodka-3.0-1.png', 1, 1, 1, 0, NULL, 1, 'active'),
(6, 2, 1, 1, 'BEER-KK-6', 'Kuche Kuche 6 Pack', 'kuche-kuche-6-pack', 'Popular Malawi beer multipack for gatherings.', 'A chilled Kuche Kuche six-pack for parties, events, and home restocking.', 'Kuche Kuche', '6 pack', 16800, 17800, 64, 12, 'https://castelmalawi.com/storage/2025/02/Packshot-KK-1.png', 'https://castelmalawi.com/storage/2025/02/KK-6-PACK-1.png', 1, 0, 1, 0, NULL, 1, 'active'),
(7, 2, 1, 1, 'BEER-CARL-GREEN-640', 'Carlsberg Green 640ml', 'carlsberg-green-640ml', 'Big bottle lager for bars and home delivery.', 'The classic Carlsberg Green bottle ready for chilled delivery across Malawi.', 'Carlsberg Green', '640ml bottle', 4200, NULL, 96, 24, 'https://castelmalawi.com/storage/2025/02/biggreen.png', 'https://castelmalawi.com/storage/2025/02/Carlsberg-Green-1-1.png', 1, 0, 1, 0, NULL, 1, 'active'),
(8, 4, 1, 1, 'SODA-COKE-2L', 'Coca-Cola 2L', 'coca-cola-2l', 'Classic cola for events, meetings, and home service.', 'A family-size Coca-Cola bottle for parties, restaurants, and cooling fridges fast.', 'Coca-Cola', '2L bottle', 5200, NULL, 84, 18, 'https://www.coca-colacompany.com/content/dam/corporate/us/en/tccc/brands/coca-cola-new-picture-1.jpg', NULL, 1, 0, 1, 0, NULL, 0, 'active'),
(9, 4, 1, 1, 'SODA-SPRITE-300', 'Sprite 300ml', 'sprite-300ml', 'Chilled lemon-lime soda for daily service.', 'A crisp Sprite bottle for coolers, events, and fast-moving retail orders.', 'Sprite', '300ml bottle', 1800, NULL, 120, 30, 'https://www.coca-cola.com/content/dam/onexp/ie/en/brands/sprite/ie_sprite_750x750.jpg', NULL, 0, 1, 1, 0, NULL, 0, 'active'),
(10, 4, 1, 1, 'SODA-FANTA-300', 'Fanta Orange 300ml', 'fanta-orange-300ml', 'Bright orange soda for chilled deliveries.', 'A sweet Fanta Orange bottle for fridge packs, events, and takeaway orders.', 'Fanta Orange', '300ml bottle', 1800, NULL, 120, 30, 'https://www.coca-cola.com/content/dam/onexp/cb/en/brands/fanta/fanta-naranja.jpg', NULL, 0, 1, 1, 0, NULL, 0, 'active'),
(11, 1, 1, 1, 'WINE-LINGA-750', 'Linga Fruit Wine 750ml', 'linga-fruit-wine-750ml', 'Sweet local fruit wine for launch events and gifting.', 'A party-friendly fruit wine from Linga for private bars, restaurants, and event delivery.', 'Linga Wine', '750ml bottle', 19500, 21000, 30, 8, 'https://malawiplus.com/wp-content/uploads/2021/07/Linga4.jpg', 'https://malawiplus.com/wp-content/uploads/2021/07/Linga5.jpg', 1, 1, 0, 0, NULL, 1, 'active'),
(12, 8, 1, 1, 'RTD-BRUTAL-FRUIT', 'Brutal Fruit Ruby Apple 275ml', 'brutal-fruit-ruby-apple-275ml', 'Ready-to-drink cider for premium occasions.', 'A chilled Brutal Fruit serving for event bars, premium gatherings, and delivery orders.', 'Brutal Fruit', '275ml bottle', 18900, 19900, 24, 6, 'https://www.brutalfruit.co.za/sites/g/files/seuoyk1971/files/styles/product_preview/public/2023-07/packs_Ruby%20Apple.png?itok=EcTg5v1q', NULL, 0, 1, 0, 1, 'Launch offer', 1, 'active'),
(13, 8, 1, 1, 'RTD-ICE-TROPEZ', 'Ice Tropez Original 275ml', 'ice-tropez-original-275ml', 'Imported sparkling ready-to-drink bottle.', 'A premium Ice Tropez bottle for launch events, rooftop service, and curated drink boxes.', 'Ice Tropez', '275ml bottle', 19900, 21900, 18, 4, 'https://icetropez.com/23-large_default/ice-tropez-original-12-pack.jpg', NULL, 0, 1, 0, 1, 'Premium launch', 1, 'active');

INSERT IGNORE INTO shop_product_images (id, product_id, image_url, alt_text, sort_order, is_primary) VALUES
(1, 1, 'https://castelmalawi.com/storage/2025/02/Packshots_0004_Malawi-gin-1.png', 'Malawi Gin bottle', 1, 1),
(2, 1, 'https://castelmalawi.com/storage/2025/02/Spirits-of-Malawi-Babies_0002_Gin-3.0-1-1.png', 'Malawi Gin bottle alternative view', 2, 0),
(3, 2, 'https://castelmalawi.com/storage/2025/02/Spirits-of-Malawi-Babies_0002_Gin-3.0-1-1.png', 'Malawi Gin 330ml', 1, 1),
(4, 3, 'https://castelmalawi.com/storage/2025/02/bigbrandy.png', 'Premier Brandy bottle', 1, 1),
(5, 3, 'https://castelmalawi.com/storage/2025/02/Spirits-of-Malawi-Babies_0000_Brandy-3.0-1.png', 'Premier Brandy alternative view', 2, 0),
(6, 4, 'https://castelmalawi.com/storage/2025/02/Spirits-of-Malawi-Babies_0000_Brandy-3.0-1.png', 'Premier Brandy 330ml', 1, 1),
(7, 5, 'https://castelmalawi.com/storage/2025/02/Packshots_0003_Malawi-vodka-1.png', 'Malawi Vodka bottle', 1, 1),
(8, 5, 'https://castelmalawi.com/storage/2025/02/Spirits-of-Malawi-Babies_0001_Vodka-3.0-1.png', 'Malawi Vodka bottle alternative view', 2, 0),
(9, 6, 'https://castelmalawi.com/storage/2025/02/Packshot-KK-1.png', 'Kuche Kuche bottle', 1, 1),
(10, 6, 'https://castelmalawi.com/storage/2025/02/KK-6-PACK-1.png', 'Kuche Kuche 6 pack', 2, 0),
(11, 7, 'https://castelmalawi.com/storage/2025/02/biggreen.png', 'Carlsberg Green bottle', 1, 1),
(12, 7, 'https://castelmalawi.com/storage/2025/02/Carlsberg-Green-1-1.png', 'Carlsberg Green bottle alternative view', 2, 0),
(13, 8, 'assets/images/shop/soft-drinks.png', 'Coca-Cola 2L', 1, 1),
(14, 9, 'assets/images/shop/soft-drinks.png', 'Sprite 300ml', 1, 1),
(15, 10, 'assets/images/shop/soft-drinks.png', 'Fanta Orange 300ml', 1, 1),
(16, 11, 'https://malawiplus.com/wp-content/uploads/2021/07/Linga4.jpg', 'Linga Fruit Wine 750ml', 1, 1),
(19, 11, 'https://malawiplus.com/wp-content/uploads/2021/07/Linga5.jpg', 'Linga Fruit Wine 750ml', 2, 0),
(17, 12, 'https://www.brutalfruit.co.za/sites/g/files/seuoyk1971/files/styles/product_preview/public/2023-07/packs_Ruby%20Apple.png?itok=EcTg5v1q', 'Brutal Fruit Ruby Apple 275ml', 1, 1),
(18, 13, 'https://icetropez.com/23-large_default/ice-tropez-original-12-pack.jpg', 'Ice Tropez Original 275ml', 1, 1);

SET FOREIGN_KEY_CHECKS = 1;
