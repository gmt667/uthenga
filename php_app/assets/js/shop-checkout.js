/**
 * Real shop cart + checkout widget — thin JSON wrappers (api/shop/cart.php,
 * api/shop/checkout.php) around the existing real cart/order logic in
 * includes/shop_helpers.php -> Uthenga Checkout (UthengaPay.initiate()) for
 * pay_online orders, matching shop-order.php's own real call exactly.
 */
(function (window) {
  'use strict';

  var ShopCheckout = {
    csrfToken: function () {
      var meta = document.querySelector('meta[name="csrf-token"]');
      return meta ? meta.content : '';
    },

    addToCart: function (productId, btn) {
      var original = btn ? btn.textContent : '';
      if (btn) { btn.disabled = true; btn.textContent = 'Adding…'; }

      fetch('/uthenga/api/shop/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'add', product_id: productId, quantity: 1, csrf_token: this.csrfToken() })
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success) {
            alert(data.error || 'Could not add that to your cart.');
            return;
          }
          ShopCheckout.renderCartBadge(data.items);
        })
        .catch(function () { alert('Could not add that to your cart. Please try again.'); })
        .finally(function () { if (btn) { btn.disabled = false; btn.textContent = original; } });
    },

    renderCartBadge: function (items) {
      var badge = document.getElementById('shop-cart-count');
      if (!badge) return;
      var count = (items || []).reduce(function (sum, item) { return sum + (parseInt(item.quantity, 10) || 0); }, 0);
      badge.textContent = count;
      badge.style.display = count > 0 ? 'inline-flex' : 'none';
    },

    openCheckout: function () {
      var msg = document.getElementById('shop-checkout-msg');
      msg.textContent = 'Loading your cart…';
      openModal('shop-checkout-modal');

      fetch('/uthenga/api/shop/cart.php?action=list')
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success || !data.items || data.items.length === 0) {
            msg.textContent = 'Your cart is empty.';
            document.getElementById('shop-checkout-summary').innerHTML = '';
            document.getElementById('shop-checkout-form-fields').style.display = 'none';
            return;
          }
          msg.textContent = '';
          document.getElementById('shop-checkout-form-fields').style.display = 'block';
          var lines = data.items.map(function (item) {
            return '<div style="display:flex;justify-content:space-between;font-size:.85rem;margin-bottom:.3rem;"><span>' + item.name + ' × ' + item.quantity + '</span><span>MK ' + Number(item.line_total).toLocaleString() + '</span></div>';
          }).join('');
          var t = data.totals;
          lines += '<div style="height:.5px;background:rgba(0,0,0,.1);margin:.6rem 0;"></div>';
          lines += '<div style="display:flex;justify-content:space-between;font-weight:800;"><span>Total</span><span>MK ' + Number(t.total).toLocaleString() + '</span></div>';
          document.getElementById('shop-checkout-summary').innerHTML = lines;
        })
        .catch(function () { msg.textContent = 'Could not load your cart. Please try again.'; });
    },

    submit: function () {
      var msg = document.getElementById('shop-checkout-msg');
      var payload = {
        customer_name: document.getElementById('shop-checkout-name').value.trim(),
        customer_email: document.getElementById('shop-checkout-email').value.trim(),
        customer_phone: document.getElementById('shop-checkout-phone').value.trim(),
        delivery_address: document.getElementById('shop-checkout-address').value.trim(),
        payment_method: document.getElementById('shop-checkout-method').value,
        csrf_token: this.csrfToken()
      };

      if (typeof UthengaPhone !== 'undefined') {
        var phoneCheck = UthengaPhone.validate(payload.customer_phone);
        if (!phoneCheck.valid) {
          msg.textContent = phoneCheck.message;
          return;
        }
        payload.customer_phone = phoneCheck.normalized;
      }
      if (!payload.customer_name || !payload.customer_email || !payload.delivery_address) {
        msg.textContent = 'Please fill in your name, email and delivery address.';
        return;
      }

      msg.textContent = 'Placing your order…';

      fetch('/uthenga/api/shop/checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success) {
            msg.textContent = data.error || 'Could not place your order. Please try again.';
            return;
          }
          if (!data.requires_payment) {
            closeModal('shop-checkout-modal');
            ShopCheckout.renderCartBadge([]);
            alert('Order ' + data.order_number + ' placed! Pay on delivery.');
            location.reload();
            return;
          }
          // requires_payment is true — must always go through real payment
          // collection. If UthengaPay isn't loaded yet, say so honestly
          // rather than silently treating an unpaid order as placed.
          if (typeof UthengaPay === 'undefined') {
            msg.textContent = 'Payment could not start (still loading). Please wait a moment and try again — your order has been saved but not charged.';
            return;
          }
          closeModal('shop-checkout-modal');
          ShopCheckout.renderCartBadge([]);
          UthengaPay.initiate({
            serviceType: 'shop',
            serviceId: 'uthenga-retail-org',
            bookingId: data.order_number,
            amount: data.total,
            title: 'Order ' + data.order_number,
            sub: 'Uthenga Shop'
          }, function () {
            // Success screen (receipt) is shown by UthengaPay itself.
          });
        })
        .catch(function () { msg.textContent = 'Could not place your order right now. Please try again.'; });
    }
  };

  window.ShopCheckout = ShopCheckout;
})(window);
