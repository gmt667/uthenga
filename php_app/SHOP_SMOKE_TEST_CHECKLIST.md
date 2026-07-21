# Shop Smoke Test Checklist

Use this checklist after applying the Shop migration or deploying a new build.

## Public Shop

- Open the public navbar and confirm `Shop` appears next to the existing site sections.
- Load the Shop homepage and verify featured products, new arrivals, best sellers, and promotions render.
- Search for a product by name and by SKU.
- Filter by category and by stock status.
- Open a product detail page and confirm the image gallery renders.
- Add a product to the cart from the listing and from the detail page.

## Cart

- Increase and decrease cart quantities.
- Remove a single item from the cart.
- Clear the cart.
- Confirm subtotal, delivery fee, tax, discount, and total are visible.
- Confirm the cart persists after login/session refresh.

## Checkout

- Open checkout with a non-empty cart.
- Fill in name, email, phone, delivery address, and delivery instructions.
- Select each payment method:
  - Cash on Delivery
  - Bank Transfer
  - TNM Mpamba
  - Airtel Money
- Place an order and confirm the receipt page opens.

## Customer Orders

- Open `My Orders` from the customer dashboard.
- Confirm order history displays the new order.
- Open the receipt/invoice view.
- Confirm the delivery timeline and order items render.
- Cancel an eligible order before delivery.

## Admin Shop

- Open Shop Management from the admin sidebar.
- Create a category.
- Create a product.
- Update stock quantity.
- Open the product detail screen and edit the product.
- Add and remove gallery images.
- Open the order detail screen and update order status.
- Assign a rider to an order.
- Confirm the order moves through:
  - Pending
  - Confirmed
  - Preparing
  - Assigned to Rider
  - Out for Delivery
  - Delivered

## Super Admin

- Open Global Shop Management from the super-admin sidebar.
- Confirm shop settings are visible.
- Toggle payment methods.
- Update delivery fee and free-delivery threshold.
- Confirm Shop summary metrics appear on the super-admin dashboard.

## Notifications

- Confirm the customer receives a notification when an order is placed.
- Confirm the customer receives a notification when an order is confirmed.
- Confirm the customer receives a notification when an order is dispatched.
- Confirm the customer receives a notification when an order is delivered.
- Confirm admins receive a notification for new shop orders.

## Responsive Checks

- Test the Shop homepage on desktop, tablet, and mobile widths.
- Test the cart and checkout layout on mobile widths.
- Confirm the admin tables remain horizontally scrollable on smaller screens.
