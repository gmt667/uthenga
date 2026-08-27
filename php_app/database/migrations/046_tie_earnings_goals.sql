-- Quick Taxi Operations: Earnings. Every figure the Earnings workspace shows
-- (gross fare, trip counts, payment-method split) is derived directly from
-- tie_trips — there is no platform-commission, bonus, or payout-gateway model
-- in this codebase, so none of that is fabricated here. The one thing that
-- needs its own storage is a driver-set weekly earnings goal, which is a
-- real, opt-in target rather than a computed figure.
CREATE TABLE IF NOT EXISTS tie_trip_earnings_goals (
  driver_user_id VARCHAR(30) NOT NULL PRIMARY KEY,
  weekly_goal DECIMAL(12,2) NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
