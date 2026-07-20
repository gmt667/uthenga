-- ====================================================================
-- Uthenga Marketplace — Migration 002: Car Rental and Platform Stats
-- ====================================================================

-- 1. Car Rental Listings Table
CREATE TABLE IF NOT EXISTS car_rental_listings (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vehicle_name   VARCHAR(180) NOT NULL,
  vehicle_type   VARCHAR(40)  NOT NULL COMMENT 'sedan, suv, 4x4, minivan, pickup, hatchback, luxury',
  location       VARCHAR(100) NOT NULL,
  seats          INT UNSIGNED NOT NULL DEFAULT 5,
  transmission   VARCHAR(30)  NOT NULL DEFAULT 'automatic' COMMENT 'manual, automatic',
  fuel_type      VARCHAR(30)  NOT NULL DEFAULT 'petrol' COMMENT 'petrol, diesel, electric, hybrid',
  price_per_day  DECIMAL(15,2) NOT NULL,
  image_url      VARCHAR(500) NULL,
  features       JSON         NULL COMMENT 'JSON array of features',
  with_driver    TINYINT(1)   NOT NULL DEFAULT 0,
  is_available   TINYINT(1)   NOT NULL DEFAULT 1,
  status         VARCHAR(20)  NOT NULL DEFAULT 'active' COMMENT 'active, maintenance, retired',
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Car Rental Bookings Table
CREATE TABLE IF NOT EXISTS car_rental_bookings (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id     VARCHAR(20)  NOT NULL,
  car_id         INT UNSIGNED NOT NULL,
  renter_id      VARCHAR(30)  NOT NULL,
  pickup_date    DATE         NOT NULL,
  return_date    DATE         NOT NULL,
  pickup_location VARCHAR(255) NOT NULL,
  total_days     INT UNSIGNED NOT NULL DEFAULT 1,
  total_fare     DECIMAL(15,2) NOT NULL,
  status         VARCHAR(30)  NOT NULL DEFAULT 'pending' COMMENT 'pending, confirmed, active, completed, cancelled',
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (car_id) REFERENCES car_rental_listings(id) ON DELETE CASCADE,
  FOREIGN KEY (renter_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Daily Transaction Stats Table
CREATE TABLE IF NOT EXISTS transaction_stats_daily (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  stat_date      DATE NOT NULL,
  total_count    INT NOT NULL DEFAULT 0,
  success_count  INT NOT NULL DEFAULT 0,
  failed_count   INT NOT NULL DEFAULT 0,
  pending_count  INT NOT NULL DEFAULT 0,
  total_revenue  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  payment_method VARCHAR(60) NOT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_stat_date_method (stat_date, payment_method)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Seed Seed Data for Car Rental (optional, if table is empty)
INSERT INTO car_rental_listings (vehicle_name, vehicle_type, location, seats, transmission, fuel_type, price_per_day, image_url, features, with_driver, is_available, status)
SELECT 'Toyota Land Cruiser Prado', '4x4', 'Lilongwe', 7, 'automatic', 'diesel', 85000.00, 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=600&fit=crop&q=80', '["4WD Off-Road", "Air Conditioning", "Leather Seats", "USB Port"]', 0, 1, 'active'
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM car_rental_listings WHERE vehicle_name = 'Toyota Land Cruiser Prado');

INSERT INTO car_rental_listings (vehicle_name, vehicle_type, location, seats, transmission, fuel_type, price_per_day, image_url, features, with_driver, is_available, status)
SELECT 'Toyota Rav4 SUV', 'suv', 'Blantyre', 5, 'automatic', 'petrol', 55000.00, 'https://images.unsplash.com/photo-1568605117036-5fe5e7bab0b7?w=600&fit=crop&q=80', '["SUV Height", "Air Conditioning", "Large Trunk", "Bluetooth"]', 0, 1, 'active'
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM car_rental_listings WHERE vehicle_name = 'Toyota Rav4 SUV');

INSERT INTO car_rental_listings (vehicle_name, vehicle_type, location, seats, transmission, fuel_type, price_per_day, image_url, features, with_driver, is_available, status)
SELECT 'Toyota Corolla Sedan', 'sedan', 'Lilongwe', 5, 'automatic', 'petrol', 35000.00, 'https://images.unsplash.com/photo-1549399542-7e3f8b79c341?w=600&fit=crop&q=80', '["Fuel Efficient", "Air Conditioning", "Comfortable Ride"]', 0, 1, 'active'
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM car_rental_listings WHERE vehicle_name = 'Toyota Corolla Sedan');

-- 5. Map Points Table (for interactive map on homepage / trip planner)
CREATE TABLE IF NOT EXISTS map_points (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(180)  NOT NULL,
  point_type  VARCHAR(40)   NOT NULL DEFAULT 'attraction'
                COMMENT 'attraction, transport, hospital, atm, hotel, restaurant, park',
  latitude    DECIMAL(10,7) NOT NULL,
  longitude   DECIMAL(10,7) NOT NULL,
  description TEXT          NULL,
  image_url   VARCHAR(500)  NULL,
  address     VARCHAR(255)  NULL,
  city        VARCHAR(100)  NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  is_featured TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_map_type (point_type),
  INDEX idx_map_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Airport Transfers Table (for airport-transfer.php bookings)
CREATE TABLE IF NOT EXISTS airport_transfers (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id         VARCHAR(30)  NOT NULL,
  passenger_id       VARCHAR(30)  NOT NULL,
  transfer_type      VARCHAR(30)  NOT NULL DEFAULT 'arrival' COMMENT 'arrival, departure, round_trip',
  airport            VARCHAR(100) NOT NULL,
  destination_address VARCHAR(255) NOT NULL,
  pickup_datetime    DATETIME     NOT NULL,
  passengers         INT UNSIGNED NOT NULL DEFAULT 1,
  luggage_count      INT UNSIGNED NOT NULL DEFAULT 1,
  vehicle_type       VARCHAR(40)  NOT NULL DEFAULT 'sedan',
  fare_mwk           DECIMAL(15,2) NOT NULL,
  status             VARCHAR(30)  NOT NULL DEFAULT 'pending',
  notes              TEXT         NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_at_booking (booking_id),
  INDEX idx_at_passenger (passenger_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Seed Map Points (Malawi major landmarks)
INSERT INTO map_points (name, point_type, latitude, longitude, description, city, is_active, is_featured)
SELECT 'Kamuzu International Airport', 'transport', -13.7880, 33.7740, 'Main international airport serving Lilongwe', 'Lilongwe', 1, 1
FROM dual WHERE NOT EXISTS (SELECT 1 FROM map_points WHERE name = 'Kamuzu International Airport');

INSERT INTO map_points (name, point_type, latitude, longitude, description, city, is_active, is_featured)
SELECT 'Lake Malawi', 'attraction', -13.5000, 34.5000, 'One of the largest lakes in Africa — crystal-clear waters and beautiful beaches', 'Salima', 1, 1
FROM dual WHERE NOT EXISTS (SELECT 1 FROM map_points WHERE name = 'Lake Malawi');

INSERT INTO map_points (name, point_type, latitude, longitude, description, city, is_active, is_featured)
SELECT 'Blantyre City Centre', 'attraction', -15.7861, 35.0058, 'Commercial and cultural hub of southern Malawi', 'Blantyre', 1, 1
FROM dual WHERE NOT EXISTS (SELECT 1 FROM map_points WHERE name = 'Blantyre City Centre');
