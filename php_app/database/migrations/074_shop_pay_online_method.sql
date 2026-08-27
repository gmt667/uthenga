-- Adds the unified 'pay_online' payment method (routes through UthengaPaymentEngine's
-- shared Uthenga Checkout — Bank/Airtel Money/Mpamba) alongside the existing
-- shop payment method values. Additive ENUM widen only.

ALTER TABLE shop_orders
  MODIFY COLUMN payment_method ENUM('cash_on_delivery','bank_transfer','tnm_mpamba','airtel_money','paychangu','pay_online') NOT NULL DEFAULT 'cash_on_delivery';

ALTER TABLE shop_payments
  MODIFY COLUMN payment_method ENUM('cash_on_delivery','bank_transfer','tnm_mpamba','airtel_money','paychangu','pay_online') NOT NULL;
