-- =============================================================================
-- Uthenga Marketplace — Additional Seed Data (All 28 Districts of Malawi)
-- Run with INSERT IGNORE to safely add on top of existing data
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ─── Extra Vendor Users ──────────────────────────────────────────────────────
INSERT IGNORE INTO users (id, name, email, password_hash, role, avatar, is_approved, balance, must_change_pw, joined_date) VALUES

('v-5', 'Malawi Cultural Arts Trust', 'events@malawiarts.com',
 '$2y$12$zuC8o8M.cs/xu/6qGaoai.DjVb2h90fo7zHcSDCvE.xzuwwkamaNi',
 'Event Organizer',
 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=150&fit=crop&q=80',
 1, 2800000.00, 0, '2026-03-05'),

('v-6', 'Liwonde Safari Camps', 'reservations@liwondesafari.com',
 '$2y$12$zuC8o8M.cs/xu/6qGaoai.DjVb2h90fo7zHcSDCvE.xzuwwkamaNi',
 'Hotel/Lodge Manager',
 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?w=150&fit=crop&q=80',
 1, 9100000.00, 0, '2026-02-28'),

('v-7', 'Peak Adventures Malawi', 'book@peakadventuresmalawi.com',
 '$2y$12$zuC8o8M.cs/xu/6qGaoai.DjVb2h90fo7zHcSDCvE.xzuwwkamaNi',
 'Tour Operator',
 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=150&fit=crop&q=80',
 1, 3500000.00, 0, '2026-04-01'),

('v-8', 'Trans-Malawi Coaches', 'info@transmalawi.mw',
 '$2y$12$zuC8o8M.cs/xu/6qGaoai.DjVb2h90fo7zHcSDCvE.xzuwwkamaNi',
 'Transport Provider',
 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=150&fit=crop&q=80',
 1, 4700000.00, 0, '2026-04-10'),

('c-3', 'Thandizo Mvula', 'thandizo@gmail.com',
 '$2y$12$zuC8o8M.cs/xu/6qGaoai.DjVb2h90fo7zHcSDCvE.xzuwwkamaNi',
 'Customer',
 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150&fit=crop&q=80',
 1, 90000.00, 0, '2026-05-01'),

('c-4', 'Moses Nkhoma', 'moses.nkhoma@hotmail.com',
 '$2y$12$zuC8o8M.cs/xu/6qGaoai.DjVb2h90fo7zHcSDCvE.xzuwwkamaNi',
 'Customer',
 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&fit=crop&q=80',
 1, 210000.00, 0, '2026-05-15');

-- =============================================================================
-- MORE EVENT LISTINGS  (All major cities & districts represented)
-- =============================================================================
INSERT IGNORE INTO listings (id, listing_type, title, description, location, image, gallery, vendor_id, vendor_name, rating, featured, is_active, meta) VALUES

('evt-6', 'event', 'Malawi Gospel Music Night',
 'A spectacular evening of soul-stirring gospel music featuring top choir groups and solo artists across Malawi and the region. Experience powerful voices from Blantyre Music & Arts Choir, Zodiak Gospel Stars, and visiting artists from Zambia and Zimbabwe.',
 'Blantyre Civic Centre, Blantyre',
 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=800&fit=crop&q=80"]',
 'v-5', 'Malawi Cultural Arts Trust', 4.7, 1, 1,
 '{"date":"2026-08-02","time":"06:00 PM - 11:00 PM","category":"Music Festivals","vipTicketPrice":20000,"standardTicketPrice":8000,"vipAvailable":100,"standardAvailable":500,"vipTotal":100,"standardTotal":600,"venueCapacity":700}'),

('evt-7', 'event', 'Lilongwe International Film Festival',
 'Malawi''s premier film festival showcasing African cinema, international documentaries, and short films from emerging Malawian filmmakers. Three days of screenings, director Q&As, master classes, and networking at Lilongwe''s iconic venues.',
 'Alliance Française, Lilongwe',
 'https://images.unsplash.com/photo-1478720568477-152d9b164e26?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1524985069026-dd778a71c7b4?w=800&fit=crop&q=80"]',
 'v-5', 'Malawi Cultural Arts Trust', 4.5, 0, 1,
 '{"date":"2026-10-03","time":"10:00 AM - 10:00 PM","category":"Entertainment Shows","vipTicketPrice":15000,"standardTicketPrice":5000,"vipAvailable":50,"standardAvailable":200,"vipTotal":60,"standardTotal":250,"venueCapacity":310}'),

('evt-8', 'event', 'Liwonde River Kayaking Challenge',
 'An exhilarating adventure race along the scenic Shire River through Liwonde National Park. Navigate past hippos, crocodiles, and spectacular bird life in this timed kayaking competition. Open to amateur and competitive paddlers.',
 'Mvuu Camp, Liwonde National Park, Machinga District',
 'https://images.unsplash.com/photo-1530053969600-caed2596d242?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=800&fit=crop&q=80"]',
 'v-5', 'Malawi Cultural Arts Trust', 4.6, 0, 1,
 '{"date":"2026-09-06","time":"07:00 AM - 04:00 PM","category":"Sports Matches","vipTicketPrice":0,"standardTicketPrice":12000,"vipAvailable":0,"standardAvailable":80,"vipTotal":0,"standardTotal":80,"venueCapacity":80}'),

('evt-9', 'event', 'Dedza Pottery & Arts Festival',
 'Celebrate Malawi''s rich artistic heritage at the Dedza Pottery Festival — one of the oldest arts events in the country. Browse handcrafted ceramics, watch live wheel-throwing demonstrations, sample local cuisine, and take home unique Malawian art pieces.',
 'Dedza Pottery, Dedza District',
 'https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1505873242700-f289a29e1724?w=800&fit=crop&q=80"]',
 'v-5', 'Malawi Cultural Arts Trust', 4.4, 0, 1,
 '{"date":"2026-08-23","time":"09:00 AM - 06:00 PM","category":"Cultural Festivals","vipTicketPrice":0,"standardTicketPrice":3000,"vipAvailable":0,"standardAvailable":300,"vipTotal":0,"standardTotal":300,"venueCapacity":300}'),

('evt-10', 'event', 'Malawi International Trade & Investment Expo',
 'The nation''s largest annual business and investment exposition, bringing together 200+ exhibitors from Malawi, SADC region, and international partners. Key sectors: agriculture, mining, manufacturing, fintech, and hospitality.',
 'Bingu International Convention Centre (BICC), Lilongwe',
 'https://images.unsplash.com/photo-1587825140708-dfaf72ae4b04?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1515187029135-18ee286d815b?w=800&fit=crop&q=80"]',
 'v-1', 'Lake Malawi Festivals Ltd', 4.8, 1, 1,
 '{"date":"2026-11-14","time":"08:00 AM - 06:00 PM","category":"Conferences","vipTicketPrice":75000,"standardTicketPrice":20000,"vipAvailable":120,"standardAvailable":600,"vipTotal":150,"standardTotal":800,"venueCapacity":950}'),

('evt-11', 'event', 'Mzuzu Jazz & Blues Night',
 'Northern Malawi''s most celebrated live music event returns to Mzuzu for a night of smooth jazz and soulful blues. Featuring top Malawian jazz artists alongside guests from Tanzania and Zambia in an intimate, atmospheric setting.',
 'Mzuzu Hotel Lawn, Mzuzu City, Mzimba District',
 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=800&fit=crop&q=80"]',
 'v-5', 'Malawi Cultural Arts Trust', 4.6, 0, 1,
 '{"date":"2026-08-30","time":"07:00 PM - 11:30 PM","category":"Music Festivals","vipTicketPrice":18000,"standardTicketPrice":7000,"vipAvailable":80,"standardAvailable":300,"vipTotal":80,"standardTotal":350,"venueCapacity":430}'),

('evt-12', 'event', 'Zomba Plateau Wildflower & Heritage Walk',
 'Join expert botanists and cultural historians for a guided sunrise walk through the ancient forests and wildflower meadows of the Zomba Plateau. Discover rare orchids, colonial history, and breathtaking panoramic views of southern Malawi.',
 'Zomba Plateau, Zomba District',
 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1501555088652-021faa106b9b?w=800&fit=crop&q=80"]',
 'v-7', 'Peak Adventures Malawi', 4.7, 0, 1,
 '{"date":"2026-09-13","time":"05:30 AM - 12:00 PM","category":"Tourism Events","vipTicketPrice":0,"standardTicketPrice":8000,"vipAvailable":0,"standardAvailable":40,"vipTotal":0,"standardTotal":40,"venueCapacity":40}'),

('evt-13', 'event', 'Nkhata Bay Reggae & Beach Party',
 'Lake Malawi''s most vibrant beach festival returns to beautiful Nkhata Bay with three days of reggae, afrobeats, and lakeside celebrations. International DJs, local bands, swimming, kayaking, and the ultimate lake lifestyle experience.',
 'Nkhata Bay Beach, Nkhata Bay District',
 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=800&fit=crop&q=80"]',
 'v-5', 'Malawi Cultural Arts Trust', 4.8, 1, 1,
 '{"date":"2026-09-19","time":"12:00 PM - 02:00 AM","category":"Music Festivals","vipTicketPrice":35000,"standardTicketPrice":15000,"vipAvailable":100,"standardAvailable":400,"vipTotal":100,"standardTotal":500,"venueCapacity":600}'),

('evt-14', 'event', 'Mangochi Gosheni City Cultural Festival',
 'Celebrating the unique heritage and vibrant culture of Mangochi — Gosheni City, the gateway to Lake Malawi''s southern shores. Traditional dance, Yao & Lomwe cultural performances, local food fair, and live music by the lake.',
 'Mangochi Beach, Mangochi District (Gosheni City)',
 'https://images.unsplash.com/photo-1504701954957-2010ec3bcec1?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=800&fit=crop&q=80"]',
 'v-5', 'Malawi Cultural Arts Trust', 4.6, 1, 1,
 '{"date":"2026-10-10","time":"10:00 AM - 08:00 PM","category":"Cultural Festivals","vipTicketPrice":12000,"standardTicketPrice":4000,"vipAvailable":150,"standardAvailable":600,"vipTotal":150,"standardTotal":700,"venueCapacity":850}'),

('evt-15', 'event', 'Karonga Cultural Diversity Fair',
 'Northern Malawi''s most inclusive cultural celebration, bringing together the diverse ethnic communities of the Karonga lakeshore district. Traditional songs, dance, crafts market, and authentic northern cuisine from the Ngonde, Tumbuka, and Sukwa peoples.',
 'Karonga Town Ground, Karonga District',
 'https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1505873242700-f289a29e1724?w=800&fit=crop&q=80"]',
 'v-5', 'Malawi Cultural Arts Trust', 4.3, 0, 1,
 '{"date":"2026-08-16","time":"09:00 AM - 06:00 PM","category":"Cultural Festivals","vipTicketPrice":0,"standardTicketPrice":2000,"vipAvailable":0,"standardAvailable":500,"vipTotal":0,"standardTotal":500,"venueCapacity":500}');

-- =============================================================================
-- MORE ACCOMMODATION LISTINGS (Covering more districts)
-- =============================================================================
INSERT IGNORE INTO listings (id, listing_type, title, description, location, image, gallery, vendor_id, vendor_name, rating, featured, is_active, meta) VALUES

('acc-3', 'accommodation', 'Mvuu Wilderness Lodge',
 'Nestled in Liwonde National Park along the Shire River, Mvuu Lodge offers an authentic African bush experience. Wake up to elephants at the waterhole, enjoy bush walks with armed rangers, and take sunset river cruises spotting hippos, crocs, and rare water birds.',
 'Liwonde National Park, Machinga District',
 'https://images.unsplash.com/photo-1523805009345-7448845a9e53?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1547471080-7cc2caa01a7e?w=800&fit=crop&q=80"]',
 'v-6', 'Liwonde Safari Camps', 4.9, 1, 1,
 '{"category":"Safari Lodge","amenities":["River View","Game Drives","Boat Safari","Bush Walks","Restaurant","Bar","WiFi"],"rooms":[{"id":"room-3a","name":"Luxury Chalet","pricePerNight":195000,"capacity":2,"availableRooms":4},{"id":"room-3b","name":"Standard Banda","pricePerNight":110000,"capacity":2,"availableRooms":6}]}'),

('acc-4', 'accommodation', 'Makuzi Beach Lodge',
 'A stunning boutique beach lodge on the shores of Lake Malawi near Nkhata Bay. Surrounded by indigenous forest, Makuzi offers traditional African-style chalets with direct beach access, superb snorkeling, kayaking, and legendary sundowner cocktails on the lake.',
 'Chintheche, Nkhata Bay District',
 'https://images.unsplash.com/photo-1439066615861-d1af74d74000?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=800&fit=crop&q=80"]',
 'v-6', 'Liwonde Safari Camps', 4.7, 1, 1,
 '{"category":"Beach Lodge","amenities":["Private Beach","Snorkeling Gear","Kayaks","Restaurant","Bar","Solar Power","WiFi"],"rooms":[{"id":"room-4a","name":"Lakeview Chalet","pricePerNight":135000,"capacity":2,"availableRooms":6},{"id":"room-4b","name":"Garden Banda","pricePerNight":78000,"capacity":2,"availableRooms":4}]}'),

('acc-5', 'accommodation', 'Crossroads Hotel Lilongwe',
 'A modern 4-star hotel in Lilongwe''s Commercial District. Perfect for business travelers with excellent conference facilities, fast WiFi, and the popular Carlitto''s restaurant. Walking distance from key government and banking institutions.',
 'Area 2, Lilongwe',
 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1582719508461-905c673771fd?w=800&fit=crop&q=80"]',
 'v-2', 'Kaya Mawa Lodge Management', 4.3, 0, 1,
 '{"category":"Hotel","amenities":["Swimming Pool","Restaurant","Bar","Conference Rooms","WiFi","Parking","Gym"],"rooms":[{"id":"room-5a","name":"Standard Double","pricePerNight":65000,"capacity":2,"availableRooms":20},{"id":"room-5b","name":"Business Suite","pricePerNight":120000,"capacity":2,"availableRooms":8}]}'),

('acc-6', 'accommodation', 'Butterfly Space Hostel',
 'The ultimate backpacker''s paradise on the shores of Lake Malawi at Nkhata Bay. A vibrant social hostel with stunning lake views, communal kitchen, hammock lounges, and legendary sunset parties. Best place to meet fellow travellers from around the world.',
 'Nkhata Bay Town, Nkhata Bay District',
 'https://images.unsplash.com/photo-1555854877-bab0e564b8d5?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1513694203232-719a280e022f?w=800&fit=crop&q=80"]',
 'v-6', 'Liwonde Safari Camps', 4.6, 0, 1,
 '{"category":"Hostel","amenities":["Lake View","Communal Kitchen","Hammock Lounge","Bar","Snorkeling","WiFi","Common Room"],"rooms":[{"id":"room-6a","name":"Dorm Bed","pricePerNight":18000,"capacity":1,"availableRooms":20},{"id":"room-6b","name":"Private Room","pricePerNight":55000,"capacity":2,"availableRooms":6}]}'),

('acc-7', 'accommodation', 'Zomba Forest Lodge',
 'A beautifully restored colonial forest lodge perched atop the magnificent Zomba Plateau. Surrounded by ancient pine forests, trout streams, and sweeping views of the southern plains. Ideal for hikers, cyclists, and anyone seeking tranquil mountain retreat.',
 'Zomba Plateau, Zomba District',
 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1501555088652-021faa106b9b?w=800&fit=crop&q=80"]',
 'v-2', 'Kaya Mawa Lodge Management', 4.8, 0, 1,
 '{"category":"Forest Lodge","amenities":["Mountain Views","Hiking Trails","Trout Fishing","Fireplace","Restaurant","Garden","WiFi"],"rooms":[{"id":"room-7a","name":"Colonial Suite","pricePerNight":115000,"capacity":2,"availableRooms":4},{"id":"room-7b","name":"Forest Cottage","pricePerNight":82000,"capacity":2,"availableRooms":6}]}'),

('acc-8', 'accommodation', 'Mzuzu Coffee House & Lodge',
 'A charming mid-range lodge in the heart of Mzuzu City — Northern Malawi''s vibrant capital. Cozy rooms, exceptional locally-sourced coffee from Mzuzu Coffee cooperative, rooftop terrace, and easy walking access to Mzuzu market and transport hub.',
 'Mzuzu City Centre, Mzimba District',
 'https://images.unsplash.com/photo-1555854877-bab0e564b8d5?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&fit=crop&q=80"]',
 'v-6', 'Liwonde Safari Camps', 4.4, 0, 1,
 '{"category":"Lodge","amenities":["Rooftop Terrace","Coffee Bar","WiFi","Restaurant","Airport Transfers","Parking"],"rooms":[{"id":"room-8a","name":"Standard Room","pricePerNight":45000,"capacity":2,"availableRooms":12},{"id":"room-8b","name":"Superior Room","pricePerNight":72000,"capacity":2,"availableRooms":6}]}'),

('acc-9', 'accommodation', 'Gosheni Lakeshore Resort Mangochi',
 'A beautiful lakeshore resort on the southern shores of Lake Malawi in Mangochi — the heart of Gosheni City. Enjoy pristine beach, water sports, fresh chambo fish, and spectacular sunsets over the lake. Perfect for families and couples.',
 'Mangochi Beach, Mangochi District (Gosheni City)',
 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1504701954957-2010ec3bcec1?w=800&fit=crop&q=80"]',
 'v-6', 'Liwonde Safari Camps', 4.5, 1, 1,
 '{"category":"Beach Resort","amenities":["Private Beach","Water Sports","Restaurant","Bar","Swimming Pool","WiFi","Kids Area"],"rooms":[{"id":"room-9a","name":"Lakeview Suite","pricePerNight":145000,"capacity":2,"availableRooms":8},{"id":"room-9b","name":"Standard Beach Chalet","pricePerNight":88000,"capacity":3,"availableRooms":12}]}'),

('acc-10', 'accommodation', 'Karonga Lakeview Guest House',
 'A comfortable and friendly guest house on the northern lakeshore in Karonga District. The closest accommodation to the famous Malawi Dinosaur Museum, with stunning views of Lake Malawi and easy access to the Songwe River crossing into Tanzania.',
 'Karonga Town, Karonga District',
 'https://images.unsplash.com/photo-1513694203232-719a280e022f?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1555854877-bab0e564b8d5?w=800&fit=crop&q=80"]',
 'v-6', 'Liwonde Safari Camps', 4.1, 0, 1,
 '{"category":"Guest House","amenities":["Lake View","Restaurant","WiFi","Parking","24hr Security","Laundry"],"rooms":[{"id":"room-10a","name":"Standard Room","pricePerNight":35000,"capacity":2,"availableRooms":10},{"id":"room-10b","name":"Family Room","pricePerNight":55000,"capacity":4,"availableRooms":4}]}');

-- =============================================================================
-- MORE TOUR LISTINGS (All key districts/regions)
-- =============================================================================
INSERT IGNORE INTO listings (id, listing_type, title, description, location, image, gallery, vendor_id, vendor_name, rating, featured, is_active, meta) VALUES

('tour-3', 'tour', 'Liwonde National Park Safari (Boat & Game Drive)',
 'Experience Africa''s most accessible big-game reserve on both land and water. Morning game drives through Liwonde''s mopane woodland for elephant, sable antelope, and buffalo sightings, followed by afternoon Shire River boat safari among hippos and hundreds of crocodile.',
 'Liwonde National Park, Machinga District',
 'https://images.unsplash.com/photo-1547471080-7cc2caa01a7e?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1504701954957-2010ec3bcec1?w=800&fit=crop&q=80"]',
 'v-3', 'Malawi Wildlife Safaris', 4.9, 1, 1,
 '{"durationDays":2,"maxGroupSize":10,"pricePerPerson":165000,"datesAvailable":["2026-07-18","2026-07-25","2026-08-01","2026-08-15","2026-09-05"],"itinerary":[{"day":1,"title":"Game Drive Morning","description":"Early morning land cruiser safari, birding walk, lunch at Mvuu Camp"},{"day":2,"title":"Shire River Boat Safari","description":"Full river cruise, hippo pods, crocodile banks, afternoon departure"}]}'),

('tour-4', 'tour', 'Chongoni Rock Art UNESCO Heritage Walk',
 'Guided walk through one of Africa''s most important rock art sites — a UNESCO World Heritage Site. Explore ancient Chewa and San paintings on granite outcrops with expert cultural historians. Includes traditional storytelling session and local lunch.',
 'Chongoni, Dedza District',
 'https://images.unsplash.com/photo-1469474968028-56623f02e42e?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1501555088652-021faa106b9b?w=800&fit=crop&q=80"]',
 'v-7', 'Peak Adventures Malawi', 4.6, 0, 1,
 '{"durationDays":1,"maxGroupSize":15,"pricePerPerson":45000,"datesAvailable":["2026-07-19","2026-07-26","2026-08-02","2026-08-09","2026-09-06"],"itinerary":[{"day":1,"title":"Heritage Walk & Cultural Experience","description":"Morning rock art tour, guided storytelling, traditional lunch, afternoon return"}]}'),

('tour-5', 'tour', 'Mulanje Mountain Multi-Day Trekking Adventure',
 'Conquer the Roof of Malawi — Mount Mulanje at 3,002m. This guided multi-day trek takes you through dramatic cedar forests, stunning waterfalls, and open moorlands. Spectacular views across Mozambique and Malawi''s tea estates below.',
 'Mulanje Massif, Mulanje District',
 'https://images.unsplash.com/photo-1501555088652-021faa106b9b?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=800&fit=crop&q=80"]',
 'v-7', 'Peak Adventures Malawi', 4.8, 1, 1,
 '{"durationDays":3,"maxGroupSize":8,"pricePerPerson":240000,"datesAvailable":["2026-07-21","2026-08-04","2026-08-18","2026-09-01","2026-09-15"],"itinerary":[{"day":1,"title":"Likhubula Gate to Chambe Hut","description":"Forest trail, cedar trees, waterfall, arrive at mountain hut"},{"day":2,"title":"Chambe to Sapitwa Peak","description":"Summit Sapitwa peak (3,002m), panoramic views, descent to Thuchila"},{"day":3,"title":"Descent & Departure","description":"Morning descent through Ruo Gorge, transfer back to Blantyre"}]}'),

('tour-6', 'tour', 'Thyolo Tea Estate Cycling & Tasting Tour',
 'Cycle through the rolling green hills of Thyolo''s famous tea estates with a knowledgeable local guide. Learn how Malawi''s premium tea is cultivated, processed and exported worldwide. Includes a full tea tasting session, estate tour, and traditional Malawian lunch.',
 'Thyolo Tea Estates, Thyolo District',
 'https://images.unsplash.com/photo-1523464862212-d6631d073194?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1501555088652-021faa106b9b?w=800&fit=crop&q=80"]',
 'v-7', 'Peak Adventures Malawi', 4.5, 0, 1,
 '{"durationDays":1,"maxGroupSize":12,"pricePerPerson":65000,"datesAvailable":["2026-07-22","2026-07-29","2026-08-05","2026-08-12","2026-09-09"],"itinerary":[{"day":1,"title":"Cycling Tea Estate Experience","description":"Morning cycle tour, estate walk, tea processing factory visit, tasting, lunch"}]}'),

('tour-7', 'tour', 'Viphya Plateau Pine Forest Trek',
 'A refreshing trek through the vast Viphya Plateau — one of the largest man-made forests in the world. Pine-scented trails, pristine Luwawa Dam fishing, mountain biking, and starlit nights under Malawi''s spectacular skies. Perfect escape from city heat.',
 'Luwawa Forest Lodge, Nkhotakota District',
 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1501555088652-021faa106b9b?w=800&fit=crop&q=80"]',
 'v-7', 'Peak Adventures Malawi', 4.7, 0, 1,
 '{"durationDays":2,"maxGroupSize":10,"pricePerPerson":125000,"datesAvailable":["2026-07-25","2026-08-08","2026-08-22","2026-09-12"],"itinerary":[{"day":1,"title":"Arrival & Forest Walk","description":"Scenic drive, pine forest trail, Luwawa Dam fishing, overnight"},{"day":2,"title":"Mountain Biking & Departure","description":"Morning mountain bike trail, bird spotting, afternoon return to Mzuzu or Lilongwe"}]}'),

('tour-8', 'tour', 'Majete Wildlife Reserve Full Day Safari',
 'Visit the only Big Five game reserve in Malawi! Majete Wildlife Reserve in Chikwawa District was successfully restocked with lion, leopard, elephant, buffalo, and rhino through an African Parks conservation partnership. An incredible conservation success story.',
 'Majete Wildlife Reserve, Chikwawa District',
 'https://images.unsplash.com/photo-1523805009345-7448845a9e53?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1547471080-7cc2caa01a7e?w=800&fit=crop&q=80"]',
 'v-3', 'Malawi Wildlife Safaris', 4.8, 1, 1,
 '{"durationDays":1,"maxGroupSize":8,"pricePerPerson":180000,"datesAvailable":["2026-07-20","2026-07-27","2026-08-03","2026-08-10","2026-08-17","2026-09-07"],"itinerary":[{"day":1,"title":"Big Five Full Day Safari","description":"Morning and afternoon game drives, lunch at Thawale Lodge, sunset views over Shire River"}]}'),

('tour-9', 'tour', 'Cape Maclear Snorkeling & Kayak Day Trip',
 'A day trip to Cape Maclear — the jewel of Lake Malawi National Park (UNESCO World Heritage). Snorkel with hundreds of cichlid species, kayak around Otter Point, visit a local fishing village, and enjoy fresh nsima and chambo at a lakeside restaurant.',
 'Cape Maclear, Mangochi District (Gosheni City)',
 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1530053969600-caed2596d242?w=800&fit=crop&q=80"]',
 'v-3', 'Malawi Wildlife Safaris', 4.9, 1, 1,
 '{"durationDays":1,"maxGroupSize":12,"pricePerPerson":95000,"datesAvailable":["2026-07-19","2026-07-26","2026-08-02","2026-08-09","2026-08-16","2026-09-06","2026-09-13"],"itinerary":[{"day":1,"title":"Cape Maclear Full Day Experience","description":"Morning snorkel, kayak to Otter Point, village visit, lunch, afternoon departure"}]}'),

('tour-10', 'tour', 'Kasungu National Park Game Drive',
 'Explore the vast and undervisited Kasungu National Park in Central Malawi. The park''s remote wilderness setting and recovering elephant population offer a raw, authentic safari experience away from the tourist crowds. Excellent birdwatching with over 200 species.',
 'Kasungu National Park, Kasungu District',
 'https://images.unsplash.com/photo-1523805009345-7448845a9e53?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1504701954957-2010ec3bcec1?w=800&fit=crop&q=80"]',
 'v-3', 'Malawi Wildlife Safaris', 4.4, 0, 1,
 '{"durationDays":2,"maxGroupSize":8,"pricePerPerson":145000,"datesAvailable":["2026-07-23","2026-08-06","2026-08-20","2026-09-03","2026-09-17"],"itinerary":[{"day":1,"title":"Arrival & Afternoon Game Drive","description":"Transfer from Lilongwe, afternoon game drive, overnight at Lifupa Camp"},{"day":2,"title":"Morning Game Drive & Departure","description":"Early morning drive, elephant tracking, birding, afternoon return to Lilongwe"}]}');

-- =============================================================================
-- MORE TRANSPORT LISTINGS (All key routes between 28 districts)
-- =============================================================================
INSERT IGNORE INTO listings (id, listing_type, title, description, location, image, gallery, vendor_id, vendor_name, rating, featured, is_active, meta) VALUES

('trans-3', 'transport', 'Blantyre → Zomba Express Minibus',
 'A direct, frequent minibus service connecting Blantyre to Zomba city. Comfortable 18-seater vehicles with AC, professional drivers, and fixed departure times from Blantyre Wenela Bus Terminal. Ideal for day trips to Zomba Plateau.',
 'Wenela Bus Terminal, Blantyre',
 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=800&fit=crop&q=80"]',
 'v-8', 'Trans-Malawi Coaches', 4.2, 0, 1,
 '{"vehicleType":"Minibus","routeFrom":"Blantyre","routeTo":"Zomba","departureTime":"06:00 AM","arrivalTime":"07:30 AM","pricePerSeat":5500,"totalSeats":18,"availableSeats":12,"scheduleDays":["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]}'),

('trans-4', 'transport', 'Mzuzu → Nkhata Bay Shuttle Service',
 'The only reliable scheduled shuttle connecting Mzuzu city to the vibrant lakeside town of Nkhata Bay. Door-to-door pickup in Mzuzu, scenic mountain road journey, and drop-off at the main Nkhata Bay boat landing.',
 'Mzuzu City Centre, Mzimba District',
 'https://images.unsplash.com/photo-1449965408869-eaa3f722e40d?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1570125909232-eb263c188f7e?w=800&fit=crop&q=80"]',
 'v-8', 'Trans-Malawi Coaches', 4.5, 0, 1,
 '{"vehicleType":"Shuttle","routeFrom":"Mzuzu","routeTo":"Nkhata Bay","departureTime":"08:00 AM","arrivalTime":"10:00 AM","pricePerSeat":9000,"totalSeats":14,"availableSeats":7,"scheduleDays":["Monday","Wednesday","Friday","Saturday","Sunday"]}'),

('trans-5', 'transport', 'Lilongwe → Dedza → Zomba Highway Express',
 'Comfortable long-distance coach connecting Lilongwe to Zomba via Dedza. AXA Coaches premium service with AC, reclining seats, and USB charging. The most scenic route in Malawi passing the Dedza highlands and Chongoni forest.',
 'Kamuzu Highway, Lilongwe',
 'https://images.unsplash.com/photo-1570125909232-eb263c188f7e?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=800&fit=crop&q=80"]',
 'v-4', 'AXA Coach Services', 4.4, 0, 1,
 '{"vehicleType":"Coach Bus","routeFrom":"Lilongwe","routeTo":"Zomba","departureTime":"07:00 AM","arrivalTime":"01:00 PM","pricePerSeat":14000,"totalSeats":50,"availableSeats":28,"scheduleDays":["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"]}'),

('trans-6', 'transport', 'Lilongwe → Mangochi (Gosheni City) Express',
 'Daily express coach service from Lilongwe to Mangochi — Gosheni City and the gateway to Lake Malawi''s southern shores. Comfortable air-conditioned coaches, professional staff, and drop-off at Mangochi town centre and beachside resorts.',
 'Lilongwe Intercity Bus Terminal',
 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1570125909232-eb263c188f7e?w=800&fit=crop&q=80"]',
 'v-4', 'AXA Coach Services', 4.3, 0, 1,
 '{"vehicleType":"Coach Bus","routeFrom":"Lilongwe","routeTo":"Mangochi","departureTime":"06:00 AM","arrivalTime":"10:00 AM","pricePerSeat":16000,"totalSeats":50,"availableSeats":35,"scheduleDays":["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]}'),

('trans-7', 'transport', 'Blantyre → Lilongwe → Mzuzu Intercity Coach',
 'The flagship long-haul route spanning all three of Malawi''s major cities in one journey. AXA''s premier overnight coach from Blantyre to Mzuzu via Lilongwe. Reclining seats, onboard refreshments, and a professional crew ensure a comfortable overnight journey.',
 'Wenela Bus Terminal, Blantyre',
 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1449965408869-eaa3f722e40d?w=800&fit=crop&q=80"]',
 'v-4', 'AXA Coach Services', 4.7, 1, 1,
 '{"vehicleType":"Coach Bus","routeFrom":"Blantyre","routeTo":"Mzuzu","departureTime":"06:00 PM","arrivalTime":"04:00 AM","pricePerSeat":28000,"totalSeats":50,"availableSeats":22,"scheduleDays":["Monday","Wednesday","Friday","Sunday"]}'),

('trans-8', 'transport', 'Karonga → Mzuzu Express Bus',
 'Regular bus service linking the far north of Malawi — Karonga District on the Tanzanian border — to Mzuzu city. Passes through the beautiful Rumphi and Viphya highlands with breathtaking scenery and cool mountain air.',
 'Karonga Town, Karonga District',
 'https://images.unsplash.com/photo-1449965408869-eaa3f722e40d?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1570125909232-eb263c188f7e?w=800&fit=crop&q=80"]',
 'v-8', 'Trans-Malawi Coaches', 4.1, 0, 1,
 '{"vehicleType":"Bus","routeFrom":"Karonga","routeTo":"Mzuzu","departureTime":"05:00 AM","arrivalTime":"11:00 AM","pricePerSeat":12000,"totalSeats":60,"availableSeats":42,"scheduleDays":["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]}'),

('trans-9', 'transport', 'Blantyre → Mulanje → Phalombe Shuttle',
 'A comfortable shuttle connecting Blantyre to Mulanje and Phalombe in the Mulanje massif foothills. Perfect for trekkers heading to Mount Mulanje and travelers exploring the scenic southern highlands tea country.',
 'Limbe Bus Depot, Blantyre',
 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=800&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1449965408869-eaa3f722e40d?w=800&fit=crop&q=80"]',
 'v-8', 'Trans-Malawi Coaches', 4.3, 0, 1,
 '{"vehicleType":"Shuttle","routeFrom":"Blantyre","routeTo":"Mulanje","departureTime":"06:30 AM","arrivalTime":"08:30 AM","pricePerSeat":8000,"totalSeats":14,"availableSeats":9,"scheduleDays":["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]}');

-- =============================================================================
-- MORE REVIEWS
-- =============================================================================
INSERT IGNORE INTO reviews (id, listing_id, user_name, rating, comment, review_date) VALUES
('rev-e6', 'evt-6', 'Thandizo Mvula', 5, 'The gospel choirs were heavenly! Best spiritual event I have attended.', '2026-05-15'),
('rev-e7', 'evt-7', 'Moses Nkhoma', 4, 'Great film selection! The Q&A with directors was particularly insightful.', '2026-06-01'),
('rev-e11', 'evt-11', 'Grace Banda', 5, 'Mzuzu Jazz Night was phenomenal! Incredible musicians and perfect atmosphere.', '2026-06-10'),
('rev-e13', 'evt-13', 'Desire Mwalwanda', 5, 'Nkhata Bay Reggae Fest is pure magic. Three days of vibes on the lake!', '2026-06-14'),
('rev-e14', 'evt-14', 'Chisomo Phiri', 4, 'Gosheni City Cultural Festival beautifully represents Yao heritage. Very proud.', '2026-06-20'),
('rev-a3', 'acc-3', 'Grace Banda', 5, 'Waking up to elephants at breakfast is a life-changing experience. Truly magical!', '2026-05-28'),
('rev-a4', 'acc-4', 'Limbani Chimwaza', 5, 'Makuzi is paradise. Cleanest water, warmest staff, best sundowners in Africa.', '2026-06-10'),
('rev-a5', 'acc-5', 'Desire Mwalwanda', 4, 'Great for business travel. Fast WiFi and the conference rooms are excellent.', '2026-06-20'),
('rev-a6', 'acc-6', 'Chisomo Phiri', 5, 'Perfect backpacker spot. Made friends from 8 countries in one evening!', '2026-06-05'),
('rev-a8', 'acc-8', 'Moses Nkhoma', 4, 'Mzuzu Coffee House brews the best coffee in all of Malawi. Rooftop view is stunning.', '2026-06-22'),
('rev-a9', 'acc-9', 'Thandizo Mvula', 5, 'Gosheni Lakeshore Resort is the hidden gem of Mangochi. Kids loved it!', '2026-06-28'),
('rev-t3', 'tour-3', 'Thandizo Mvula', 5, 'The hippos came right up to the boat. Our guide was fantastic and very knowledgeable.', '2026-06-15'),
('rev-t4', 'tour-4', 'Moses Nkhoma', 5, 'The rock art at Chongoni is stunning. Learning about Chewa culture was priceless.', '2026-06-22'),
('rev-t5', 'tour-5', 'Grace Banda', 5, 'Summit day was tough but the view from Sapitwa peak is absolutely breathtaking.', '2026-06-18'),
('rev-t8', 'tour-8', 'Limbani Chimwaza', 5, 'Majete''s Big Five is unbelievable! Saw lions on our first drive. Life goal achieved!', '2026-06-25'),
('rev-t9', 'tour-9', 'Chisomo Phiri', 5, 'Cape Maclear is breathtaking. The cichlid fish are so colourful you think you are in the ocean!', '2026-07-01'),
('rev-tr3', 'trans-3', 'Limbani Chimwaza', 4, 'Clean, reliable and on time. Perfect for the Zomba day trip.', '2026-05-30'),
('rev-tr5', 'trans-7', 'Moses Nkhoma', 4, 'Overnight coach was comfortable. Arrived refreshed in Mzuzu right on schedule.', '2026-06-08');

-- =============================================================================
-- MORE BLOG POSTS
-- =============================================================================
INSERT IGNORE INTO blog_posts (id, title, excerpt, content, image, author, category, post_date) VALUES

('blog-4', 'Top 5 Things to Do at Lake Malawi This Summer',
 'Crystal waters, incredible fish, beach parties, kayaking, and world-class lodges — Lake Malawi is Malawi''s crown jewel and your ultimate summer playground.',
 '<p>Lake Malawi — known as the "Calendar Lake" due to its dimensions of 365 miles long and 52 miles wide — is undeniably the heart and soul of Malawi. The UNESCO World Heritage Site is home to more species of tropical freshwater fish than any other lake on Earth.</p><h3>1. Snorkeling at Cape Maclear</h3><p>The crystal-clear waters around Cape Maclear (Mangochi District) teem with hundreds of species of colourful cichlid fish. Most lodges provide snorkeling gear, and guided tours can take you to the best spots.</p><h3>2. Island Hopping to Mumbo & Domwe</h3><p>These uninhabited wilderness islands offer a back-to-nature experience. Stay in tented camps right on the beach with no electricity or distractions.</p><h3>3. Kayaking Along the Shore</h3><p>Rent a kayak and explore hidden coves, fishing villages, and spectacular sunsets along the lakeshore.</p><h3>4. Attend the Lake of Stars Festival</h3><p>Held annually at Mangochi Beach, this three-day music and arts festival brings together African and international artists for an unforgettable beachside celebration.</p><h3>5. Stay at a Luxury Island Lodge</h3><p>Kaya Mawa on Likoma Island is one of Africa''s most exclusive and romantic lodges, perched on its own private island in the lake.</p>',
 'https://images.unsplash.com/photo-1504701954957-2010ec3bcec1?w=800&fit=crop&q=80',
 'Desire Mwalwanda', 'Travel Guide', '2026-06-20'),

('blog-5', 'A Complete Guide to Liwonde National Park',
 'Malawi''s most accessible national park packs extraordinary wildlife into a compact area. Here''s everything you need to know for an unforgettable safari.',
 '<p>Liwonde National Park may be compact by African standards, but it punches far above its weight. Straddling the Shire River in southern Malawi''s Machinga District, this 548 sq km reserve offers some of the continent''s best waterborne wildlife viewing.</p><h3>Wildlife You Can See</h3><p>The park is home to elephant herds (over 700 individuals), hippo pods, crocodile, sable antelope, waterbuck, and over 400 species of birds including the African fish eagle.</p><h3>Activities</h3><p>Game drives in open Land Cruisers, boat safaris on the Shire River, guided bush walks, and night drives are all available.</p><h3>Best Time to Visit</h3><p>June to October (dry season) is ideal when animals congregate around the river and vegetation is thin.</p><h3>How to Get There</h3><p>Liwonde town is 2.5 hours from Blantyre and 4 hours from Lilongwe. Regular buses serve the route, or you can book the Trans-Malawi shuttle service through Uthenga.</p>',
 'https://images.unsplash.com/photo-1547471080-7cc2caa01a7e?w=800&fit=crop&q=80',
 'Chisomo Phiri', 'Travel Guide', '2026-06-28'),

('blog-6', 'Malawi''s Festival Calendar: Not-to-Miss Events in 2026',
 'From gospel nights to film festivals, kayak races to trade expos — Malawi''s 2026 events calendar is packed with incredible experiences across all 28 districts.',
 '<p>Malawi may be a small country, but its events calendar is anything but small. 2026 promises to be one of the most exciting years for events and festivals across all 28 districts.</p><h3>Lake of Stars Festival — Mangochi (September)</h3><p>The headline event of Malawi''s cultural calendar at Mangochi Beach Resort for three days of world-class music and celebration on the shores of Lake Malawi.</p><h3>Malawi Tech Innovation Summit — Lilongwe (July)</h3><p>The premier national digital gathering at BICC. Essential for founders, investors, and tech enthusiasts.</p><h3>Blantyre Football Derby — Blantyre (July)</h3><p>Nyasa Big Bullets vs Mighty Mukuru Wanderers — the most passionate rivalry in Malawi.</p><h3>Dedza Pottery Festival — Dedza (August)</h3><p>A wonderful celebration of traditional Malawian crafts at the famous Dedza Pottery.</p><h3>Nkhata Bay Reggae & Beach Festival — Nkhata Bay (September)</h3><p>Three days of reggae, afrobeats, and lakeside celebrations in beautiful Nkhata Bay.</p><h3>Mzuzu Jazz Night — Mzimba (August)</h3><p>Northern Malawi''s best live music event at Mzuzu Hotel Lawn.</p><h3>Gosheni City Cultural Festival — Mangochi (October)</h3><p>Celebrating the rich Yao and Lomwe heritage of Mangochi District.</p>',
 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=800&fit=crop&q=80',
 'Malawi Cultural Arts Trust', 'Local Events', '2026-07-01'),

('blog-7', 'Hiking Mount Mulanje: The Roof of Malawi Guide',
 'The Roof of Malawi awaits. Our complete guide to trekking Africa''s second highest massif in Mulanje District, with tips on routes, huts, and what to pack.',
 '<p>Mount Mulanje — often called the "Island in the Sky" — is one of Malawi''s most dramatic and rewarding destinations in Mulanje District, Southern Malawi. Rising abruptly from the surrounding tea plantations to a height of 3,002 metres at Sapitwa Peak (the highest point in Central Africa), Mulanje is a world-class trekking destination.</p><h3>The Routes</h3><p>Over a dozen trails criss-cross the massif, ranging from day hikes to week-long circuits. The most popular is the traverse from Likhubula to Chambe Hut and on to Sapitwa.</p><h3>Mountain Huts</h3><p>The Malawi Mountain Club maintains a network of nine huts across the plateau, each sleeping 8-16 people. Book in advance during peak season (June-September).</p><h3>What to Pack</h3><p>Weather on Mulanje can change rapidly. Even in summer, night temperatures can drop to near freezing. Bring waterproof jacket, warm layers, sturdy hiking boots, and plenty of water.</p><h3>Booking Your Trek</h3><p>Guides and porters are available through Peak Adventures Malawi on the Uthenga marketplace.</p>',
 'https://images.unsplash.com/photo-1501555088652-021faa106b9b?w=800&fit=crop&q=80',
 'Peak Adventures Malawi', 'Travel Guide', '2026-07-05'),

('blog-8', 'Malawian Cuisine: A Foodie''s Guide to Local Flavors',
 'Nsima, chambo, beans, and mandazi — Malawi''s food culture is rich, hearty, and deeply satisfying. Here''s what to eat and where across all 28 districts.',
 '<p>Malawian cuisine is built around simple, wholesome ingredients — fresh fish from Lake Malawi, seasonal vegetables, legumes, and the staple starch nsima (a thick porridge made from maize flour).</p><h3>Must-Try Dishes</h3><p><strong>Nsima with Chambo:</strong> The ultimate Malawian meal. Nsima paired with chambo — the delicious cichlid fish found only in Lake Malawi — is the national dish. Best enjoyed at a lakeside restaurant in Mangochi, Nkhata Bay, or Cape Maclear.</p><p><strong>Ndiwo:</strong> A relish made from beans, vegetables, or groundnuts that accompanies nsima. Every family has their own secret recipe.</p><p><strong>Mandazi:</strong> Fried dough that makes the perfect breakfast, especially when served with sweet Malawian tea from the Thyolo estates.</p><p><strong>Kondowole:</strong> A cassava-based staple popular in the lakeshore districts of Salima, Nkhotakota, and Nkhata Bay.</p><h3>Where to Eat</h3><p>Lilongwe''s Area 4 restaurant strip, Blantyre''s Michiru Mountain area, any lakeside lodge, and Mzuzu''s city centre cafes all offer excellent Malawian food experiences.</p>',
 'https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?w=800&fit=crop&q=80',
 'Desire Mwalwanda', 'Culture', '2026-07-08'),

('blog-9', 'All 28 Districts of Malawi: A Traveler''s Overview',
 'From Chitipa in the far north to Nsanje at the southern tip, every district of Malawi has something unique to offer. Here''s your comprehensive district-by-district guide.',
 '<p>Malawi is divided into three regions and 28 districts, each with its own character, landscapes, culture, and attractions. Here''s a quick overview of all 28 districts to help you plan your Malawi adventure.</p><h3>Northern Region</h3><ul><li><strong>Chitipa:</strong> The most remote district, bordering Tanzania and Zambia, known for its Ngonde people and beautiful Misuku Hills.</li><li><strong>Karonga:</strong> A lakeshore district famous for its dinosaur fossils at the Malawi Dinosaur Museum and the gateway to Tanzania via Songwe border.</li><li><strong>Likoma:</strong> A tiny island district in the middle of Lake Malawi with the magnificent Likoma Cathedral and world-class Kaya Mawa lodge.</li><li><strong>Mzimba:</strong> Home to Mzuzu city, the commercial hub of the north, and the vast Viphya pine forests.</li><li><strong>Nkhata Bay:</strong> The backpacker capital of Malawi, with crystal-clear lake waters, vibrant beach culture, and the famous Butterfly Space hostel.</li><li><strong>Rumphi:</strong> Gateway to the magnificent Nyika Plateau National Park — Africa''s largest montane plateau, famous for wildflowers and rare antelopes.</li></ul><h3>Central Region</h3><ul><li><strong>Dedza:</strong> Known for the famous Dedza Pottery and the Chongoni Rock Art UNESCO World Heritage Site.</li><li><strong>Dowa:</strong> Agricultural heartland known for tobacco farming and the scenic Ntchisi Forest Reserve.</li><li><strong>Kasungu:</strong> Home to Kasungu National Park with recovering elephant populations and pristine wilderness.</li><li><strong>Lilongwe:</strong> The capital city — political, administrative, and increasingly vibrant cultural hub with Old Town markets and Area 10 entertainment.</li><li><strong>Mchinji:</strong> The western border district with Zambia, known for the Mchinji border crossing and game management areas.</li><li><strong>Nkhotakota:</strong> The oldest market town in Central Africa, and gateway to the Viphya Plateau via Luwawa Forest.</li><li><strong>Ntcheu:</strong> Southern central district known for tobacco and the Ntcheu Highlands bordering Mozambique.</li><li><strong>Ntchisi:</strong> Ntchisi Forest Reserve offers excellent birdwatching and hiking through indigenous forest.</li><li><strong>Salima:</strong> Gateway to Senga Bay — one of the most popular and accessible Lake Malawi beach destinations from Lilongwe.</li></ul><h3>Southern Region</h3><ul><li><strong>Balaka:</strong> An agricultural district at the junction of the M1 highway, known as a stopping point between Lilongwe and Blantyre.</li><li><strong>Blantyre:</strong> The commercial capital of Malawi, with Victorian architecture, vibrant markets, and proximity to Mount Mulanje and Zomba Plateau.</li><li><strong>Chikwawa:</strong> Home to Majete Wildlife Reserve — Malawi''s only Big Five park restored through African Parks partnership.</li><li><strong>Chiradzulu:</strong> A small highland district bordering Blantyre with the scenic Chiradzulu Mountain.</li><li><strong>Machinga:</strong> Home to Liwonde National Park — Malawi''s most visited game reserve along the Shire River.</li><li><strong>Mangochi (Gosheni City):</strong> The gateway to Lake Malawi''s most spectacular southern beaches, including Cape Maclear and Monkey Bay. Known as Gosheni City.</li><li><strong>Mulanje:</strong> Dominated by the spectacular Mulanje Massif — the highest peak in Central Africa at 3,002m. Tea estates surround the base.</li><li><strong>Mwanza:</strong> A small district bordering Mozambique, known for tobacco farming.</li><li><strong>Neno:</strong> One of Malawi''s newest and most remote districts, bordering Mozambique in the Shire Highlands.</li><li><strong>Nsanje:</strong> The southernmost district of Malawi, known as the "hottest district," at the confluence of the Shire and Ruo rivers.</li><li><strong>Thyolo:</strong> Famous for its stunning rolling tea estate landscapes, Malawi''s premium tea production, and excellent cycling and walking.</li><li><strong>Zomba:</strong> The former colonial capital, situated at the foot of the magnificent Zomba Plateau with its spectacular forests, trout fishing, and mountain walks.</li><li><strong>Phalombe:</strong> A remote district at the eastern foot of Mount Mulanje, known for its traditional crafts and border position with Mozambique.</li></ul>',
 'https://images.unsplash.com/photo-1612892483236-52d32a0e0ac1?w=800&fit=crop&q=80',
 'Desire Mwalwanda', 'Travel Guide', '2026-07-09');

-- =============================================================================
-- MORE SAMPLE BOOKINGS
-- =============================================================================
INSERT IGNORE INTO bookings (id, listing_id, listing_title, listing_image, listing_type, customer_id, customer_name, customer_email, booking_date, details, total_price, commission_paid, payment_status, booking_status, transaction_id, qr_code) VALUES

('BKG-10003', 'acc-1', 'Kaya Mawa Private Island Lodge',
 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?w=600&fit=crop&q=80',
 'accommodation', 'c-1', 'Grace Banda', 'grace.banda@gmail.com',
 '2026-06-25',
 '{"roomId":"room-1b","checkIn":"2026-07-10","checkOut":"2026-07-13","nights":3,"guests":2}',
 375000.00, 37500.00, 'Paid', 'confirmed',
 'TXN-G7H8I9', 'UTHENGA-AC-BKG-10003-GRACE'),

('BKG-10004', 'tour-3', 'Liwonde National Park Safari',
 'https://images.unsplash.com/photo-1547471080-7cc2caa01a7e?w=600&fit=crop&q=80',
 'tour', 'c-3', 'Thandizo Mvula', 'thandizo@gmail.com',
 '2026-06-28',
 '{"tourDate":"2026-07-25","quantity":2}',
 330000.00, 33000.00, 'Paid', 'confirmed',
 'TXN-J1K2L3', 'UTHENGA-TO-BKG-10004-THANDIZO'),

('BKG-10005', 'trans-1', 'AXA Executive Coach: Lilongwe → Blantyre',
 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=600&fit=crop&q=80',
 'transport', 'c-4', 'Moses Nkhoma', 'moses.nkhoma@hotmail.com',
 '2026-07-01',
 '{"departureDate":"2026-07-05","seats":1}',
 18000.00, 1800.00, 'Paid', 'confirmed',
 'TXN-M4N5O6', 'UTHENGA-TR-BKG-10005-MOSES'),

('BKG-10006', 'evt-4', 'Blantyre Football Derby',
 'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?w=600&fit=crop&q=80',
 'event', 'c-2', 'Limbani Chimwaza', 'limbani@outlook.com',
 '2026-07-02',
 '{"ticketType":"Standard","quantity":3}',
 24000.00, 2400.00, 'Paid', 'confirmed',
 'TXN-P7Q8R9', 'UTHENGA-EV-BKG-10006-LIMBANI'),

('BKG-10007', 'tour-8', 'Majete Wildlife Reserve Full Day Safari',
 'https://images.unsplash.com/photo-1523805009345-7448845a9e53?w=600&fit=crop&q=80',
 'tour', 'c-3', 'Thandizo Mvula', 'thandizo@gmail.com',
 '2026-07-05',
 '{"tourDate":"2026-07-20","quantity":2}',
 360000.00, 36000.00, 'Paid', 'confirmed',
 'TXN-Q8R9S1', 'UTHENGA-TO-BKG-10007-THANDIZO'),

('BKG-10008', 'acc-9', 'Gosheni Lakeshore Resort Mangochi',
 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?w=600&fit=crop&q=80',
 'accommodation', 'c-4', 'Moses Nkhoma', 'moses.nkhoma@hotmail.com',
 '2026-07-06',
 '{"roomId":"room-9b","checkIn":"2026-07-15","checkOut":"2026-07-18","nights":3,"guests":3}',
 264000.00, 26400.00, 'Pending', 'pending',
 NULL, NULL);

-- =============================================================================
-- TRANSACTIONS
-- =============================================================================
INSERT IGNORE INTO transactions (id, booking_id, customer_id, customer_name, amount, gateway, status, receipt_number) VALUES
('TXN-G7H8I9', 'BKG-10003', 'c-1', 'Grace Banda', 375000.00, 'Uthenga Pay', 'Success', 'REC-CT-3456789'),
('TXN-J1K2L3', 'BKG-10004', 'c-3', 'Thandizo Mvula', 330000.00, 'Airtel Money', 'Success', 'REC-CT-4567890'),
('TXN-M4N5O6', 'BKG-10005', 'c-4', 'Moses Nkhoma', 18000.00, 'TNM Mpamba', 'Success', 'REC-CT-5678901'),
('TXN-P7Q8R9', 'BKG-10006', 'c-2', 'Limbani Chimwaza', 24000.00, 'Airtel Money', 'Success', 'REC-CT-6789012'),
('TXN-Q8R9S1', 'BKG-10007', 'c-3', 'Thandizo Mvula', 360000.00, 'Uthenga Pay', 'Success', 'REC-CT-7890123');

-- =============================================================================
-- COUPONS
-- =============================================================================
INSERT IGNORE INTO coupons (code, discount_type, value, min_spend, expiry_date) VALUES
('SAFARI30', 'percentage', 30.00, 150000.00, '2026-09-30'),
('BLANTYRE15', 'percentage', 15.00, 50000.00, '2026-08-31'),
('NEWUSER20', 'fixed', 20000.00, 80000.00, '2026-12-31'),
('GOSHENI10', 'percentage', 10.00, 40000.00, '2026-12-31'),
('MZUZU5K', 'fixed', 5000.00, 25000.00, '2026-10-31');

SET FOREIGN_KEY_CHECKS = 1;
