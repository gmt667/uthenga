SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

START TRANSACTION;

-- =========================================================
-- 1) CORE USERS
-- =========================================================

INSERT IGNORE INTO users (
  id, user_code, full_name, email, phone, password_hash,
  avatar_url, account_status, email_verified_at, phone_verified_at,
  last_login_at, must_change_password, created_at, updated_at, deleted_at
) VALUES
(1, 'USR-ADMIN-001', 'Uthenga Admin', 'admin@uthenga.test', '+265100000001', NULL, NULL, 'active', NOW(), NULL, NULL, 0, NOW(), NOW(), NULL),
(2, 'USR-VENDOR-001', 'Demo Vendor User', 'vendor@uthenga.test', '+265100000002', NULL, NULL, 'active', NOW(), NULL, NULL, 0, NOW(), NOW(), NULL),
(3, 'USR-CUSTOMER-001', 'Demo Customer', 'customer@uthenga.test', '+265100000003', NULL, NULL, 'active', NOW(), NULL, NULL, 0, NOW(), NOW(), NULL);

-- =========================================================
-- 2) LOCATION STACK
-- =========================================================

INSERT IGNORE INTO countries (
  id, name, iso2_code, iso3_code, phone_code, created_at, updated_at, deleted_at
) VALUES
(1, 'Malawi', 'MW', 'MWI', '+265', NOW(), NOW(), NULL);

INSERT IGNORE INTO cities (
  id, country_id, name, latitude, longitude, created_at, updated_at, deleted_at
) VALUES
(1, 1, 'Lilongwe', -13.9626, 33.7741, NOW(), NOW(), NULL),
(2, 1, 'Blantyre', -15.7861, 35.0058, NOW(), NOW(), NULL);

INSERT IGNORE INTO locations (
  id, country_id, city_id, label, address_line1, address_line2, postal_code,
  latitude, longitude, google_place_id, osm_place_id, map_provider,
  created_at, updated_at, deleted_at
) VALUES
(1, 1, 1, 'Lilongwe City Centre', 'Area 2, Lilongwe', NULL, NULL, -13.9626, 33.7741, NULL, NULL, 'manual', NOW(), NOW(), NULL),
(2, 1, 2, 'Blantyre City Centre', 'Victoria Avenue, Blantyre', NULL, NULL, -15.7861, 35.0058, NULL, NULL, 'manual', NOW(), NOW(), NULL),
(3, 1, 1, 'Lake Shore Venue', 'Senga Bay Road, Salima', NULL, NULL, -13.7500, 34.5667, NULL, NULL, 'manual', NOW(), NOW(), NULL);

-- =========================================================
-- 3) VENDOR + CATEGORIES
-- =========================================================

INSERT IGNORE INTO vendors (
  id, vendor_code, user_id, business_name, display_name, description,
  business_email, business_phone, website_url, payout_email, status,
  approved_at, rejected_at, suspended_at, created_at, updated_at, deleted_at
) VALUES
(1, 'VND-001', 2, 'Demo Vendor Ltd', 'Demo Vendor', 'Seed vendor for marketplace testing', 'vendor@uthenga.test', '+265100000002', 'https://example.com', 'payout@uthenga.test', 'approved', NOW(), NULL, NULL, NOW(), NOW(), NULL);

INSERT IGNORE INTO event_categories (
  id, name, description, created_at, updated_at, deleted_at
) VALUES
(1, 'Music', 'Live music and entertainment events', NOW(), NOW(), NULL),
(2, 'Business', 'Workshops, conferences, and business events', NOW(), NOW(), NULL),
(3, 'Technology', 'Tech meetups, product launches, and demos', NOW(), NOW(), NULL);

INSERT IGNORE INTO property_categories (
  id, name, description, created_at, updated_at, deleted_at
) VALUES
(1, 'Hotel', 'Hotels and full-service accommodation', NOW(), NOW(), NULL),
(2, 'Lodge', 'Lodges and boutique stays', NOW(), NOW(), NULL);

-- =========================================================
-- 4) EVENTS
-- =========================================================

INSERT IGNORE INTO events (
  id, event_code, vendor_id, category_id, title, slug, description,
  short_description, event_mode, timezone, status, featured,
  total_capacity, age_limit, poster_image_url, created_at, updated_at, deleted_at
) VALUES
(1, 'EVT001', 1, 1, 'Lake Shore Music Festival', 'lake-shore-music-festival', 'Demo event for testing the modular feed and event detail pages.', 'A demo music festival by the lake.', 'physical', 'Africa/Blantyre', 'published', 1, 500, 16, 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=1200&fit=crop&q=80', NOW(), NOW(), NULL);

INSERT IGNORE INTO event_locations (
  id, event_id, location_id, venue_name, venue_notes, is_primary, created_at, updated_at
) VALUES
(1, 1, 3, 'Lake Shore Events Grounds', 'Main stage near the lake shore', 1, NOW(), NOW());

INSERT IGNORE INTO event_images (
  id, event_id, image_url, alt_text, sort_order, created_at
) VALUES
(1, 1, 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=1200&fit=crop&q=80', 'Crowd at music festival', 1, NOW());

INSERT IGNORE INTO ticket_types (
  id, event_id, name, description, total_quantity, sold_quantity, min_per_order, max_per_order, is_active, created_at, updated_at
) VALUES
(1, 1, 'General Admission', 'Standard entry ticket', 400, 120, 1, 6, 1, NOW(), NOW()),
(2, 1, 'VIP', 'VIP access with premium seating', 100, 20, 1, 4, 1, NOW(), NOW());

INSERT IGNORE INTO ticket_pricing (
  id, ticket_type_id, price, currency, valid_from, valid_to, is_active, created_at, updated_at
) VALUES
(1, 1, 15000.00, 'MWK', NULL, NULL, 1, NOW(), NOW()),
(2, 2, 40000.00, 'MWK', NULL, NULL, 1, NOW(), NOW());

INSERT IGNORE INTO featured_events (
  id, event_id, placement, starts_at, ends_at, display_order, created_at
) VALUES
(1, 1, 'homepage', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 1, NOW());

-- =========================================================
-- 5) STAYS
-- =========================================================

INSERT IGNORE INTO properties (
  id, property_code, vendor_id, category_id, title, slug, description,
  check_in_time, check_out_time, total_rooms, base_price, currency,
  rating_average, review_count, status, featured, created_at, updated_at, deleted_at
) VALUES
(1, 'PRO-001', 1, 1, 'Sunset Lake Lodge', 'sunset-lake-lodge', 'Demo stay listing for testing the properties feed and detail page.', '14:00:00', '10:00:00', 12, 65000.00, 'MWK', 4.70, 18, 'published', 1, NOW(), NOW(), NULL);

INSERT IGNORE INTO property_locations (
  id, property_id, location_id, is_primary, directions_note, created_at, updated_at
) VALUES
(1, 1, 2, 1, 'Near the city center for easy access', NOW(), NOW());

INSERT IGNORE INTO property_images (
  id, property_id, image_url, alt_text, sort_order, created_at
) VALUES
(1, 1, 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=1200&fit=crop&q=80', 'Lake lodge exterior', 1, NOW());

INSERT IGNORE INTO property_rooms (
  id, property_id, room_name, room_type, description, price_per_night, currency,
  max_occupancy, total_units, amenities, status, created_at, updated_at
) VALUES
(1, 1, 'Standard Room', 'double', 'Comfortable room for two guests', 65000.00, 'MWK', 2, 8, JSON_ARRAY('wifi','breakfast','aircon'), 'active', NOW(), NOW()),
(2, 1, 'Family Suite', 'suite', 'Spacious suite for families', 110000.00, 'MWK', 4, 4, JSON_ARRAY('wifi','breakfast','aircon','balcony'), 'active', NOW(), NOW());

-- =========================================================
-- 6) TRANSPORT
-- =========================================================

INSERT IGNORE INTO transport_providers (
  id, vendor_id, provider_code, name, provider_type, status, created_at, updated_at, deleted_at
) VALUES
(1, 1, 'TRP-001', 'Demo Transit', 'bus', 'active', NOW(), NOW(), NULL);

INSERT IGNORE INTO routes (
  id, provider_id, route_code, origin_location_id, destination_location_id, route_name,
  distance_km, estimated_duration_min, base_fare, currency, status, created_at, updated_at, deleted_at
) VALUES
(1, 1, 'RT-001', 1, 2, 'Lilongwe to Blantyre Express', 310.00, 240, 12000.00, 'MWK', 'active', NOW(), NOW(), NULL);

INSERT IGNORE INTO vehicles (
  id, provider_id, route_id, vehicle_code, vehicle_type, plate_number, model, seat_capacity,
  status, created_at, updated_at, deleted_at
) VALUES
(1, 1, 1, 'VEH-001', 'bus', 'MW-BUS-001', 'Scania Touring', 45, 'active', NOW(), NOW(), NULL);

-- =========================================================
-- 7) TOURS
-- =========================================================

INSERT IGNORE INTO tour_packages (
  id, package_code, vendor_id, location_id, title, slug, description,
  duration_days, max_group_size, base_price, currency, status,
  created_at, updated_at, deleted_at
) VALUES
(1, 'TOUR-001', 1, 3, 'Lake Shore Weekend Escape', 'lake-shore-weekend-escape', 'Demo tour package built for the modular catalog feed.', 2, 12, 85000.00, 'MWK', 'published', NOW(), NOW(), NULL);

-- =========================================================
-- 8) SMALL QUALITY-OF-LIFE RELATIONS
-- =========================================================

INSERT IGNORE INTO favorites (
  id, user_id, favorite_type, reference_id, notes, created_at
) VALUES
(1, 3, 'event', '1', 'Demo favorite event', NOW()),
(2, 3, 'property', '1', 'Demo favorite stay', NOW());

COMMIT;

SET FOREIGN_KEY_CHECKS = 1;
