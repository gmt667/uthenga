USE uthenga_app;

INSERT INTO ride_sharing_trips
  (id, driver_id, driver_name, driver_phone, pickup_location, destination, departure_datetime,
   available_seats, booked_seats, price_per_seat, vehicle_make, vehicle_model, vehicle_color, vehicle_reg, description, status)
VALUES
  ('RST-001', 'v-4', 'Chikondi Mwale', '+265 888 123 456',
   'Lilongwe (Area 47)', 'Blantyre (Limbe)',
   DATE_ADD(NOW(), INTERVAL 2 DAY), 4, 1, 2500.00,
   'Toyota', 'Hiace', 'White', 'MWK 7823', 'Direct Lilongwe–Blantyre run. Comfortable minivan, AC, music.', 'open'),

  ('RST-002', 'c-1', 'Grace Banda', '+265 995 654 321',
   'Blantyre (CBD)', 'Zomba Town',
   DATE_ADD(NOW(), INTERVAL 1 DAY), 3, 0, 1800.00,
   'Toyota', 'Corolla', 'Silver', 'MWK 4491', 'Morning run to Zomba. Leaves 6:30 AM sharp. Quiet ride.', 'open'),

  ('RST-003', 'v-3', 'Kondwani Nyirenda', '+265 882 777 888',
   'Mzuzu City', 'Nkhata Bay',
   DATE_ADD(NOW(), INTERVAL 3 DAY), 5, 2, 3200.00,
   'Nissan', 'Patrol', 'Black', 'MWK 2019', 'SUV 4x4 to Nkhata Bay. Great for luggage. Scenic route.', 'open'),

  ('RST-004', 'v-4', 'AXA Coach Services', '+265 993 100 200',
   'Lilongwe Bus Depot', 'Mzuzu City',
   DATE_ADD(NOW(), INTERVAL 4 DAY), 12, 5, 4000.00,
   'Scania', 'Coach', 'Red/White', 'ZA 3344 MW', 'Executive coach Lilongwe to Mzuzu. Reclining seats, USB charging.', 'open'),

  ('RST-005', 'c-2', 'Limbani Chimwaza', '+265 881 222 333',
   'Zomba (UNIMA)', 'Blantyre (Wenela)',
   DATE_ADD(NOW(), INTERVAL 1 DAY), 2, 0, 1500.00,
   'Honda', 'Fit', 'Blue', 'MWK 9912', 'Heading to Blantyre after lectures. Split fuel costs.', 'open'),

  ('RST-006', 'v-3', 'Safari Mike Travels', '+265 888 500 600',
   'Blantyre (Nkolokoti)', 'Liwonde National Park',
   DATE_ADD(NOW(), INTERVAL 5 DAY), 6, 3, 5500.00,
   'Toyota', 'Land Cruiser', 'Khaki', 'ZB 0071 MW', 'Safari vehicle to Liwonde. Perfect for park entry. Luggage rack available.', 'open'),

  ('RST-007', 'u-2', 'Chisomo Phiri', '+265 993 900 100',
   'Lilongwe (City Centre)', 'Dedza Town',
   DATE_ADD(NOW(), INTERVAL 2 DAY), 3, 1, 1200.00,
   'Mazda', 'CX-5', 'Grey', 'MW 2244', 'Weekend trip to Dedza. Pottery market stop possible.', 'open'),

  ('RST-008', 'v-4', 'AXA Express', '+265 993 100 201',
   'Mzuzu City', 'Lilongwe Bus Depot',
   DATE_ADD(NOW(), INTERVAL 3 DAY), 14, 6, 3800.00,
   'Scania', 'Bus', 'Blue/White', 'ZA 3345 MW', 'Return express bus from Mzuzu to Lilongwe. Departs 5:00 AM.', 'open')

ON DUPLICATE KEY UPDATE id=id;
