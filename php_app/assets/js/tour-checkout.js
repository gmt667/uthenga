/**
 * Real tour booking widget — the existing, proven request_api.php
 * create_booking path (now fixed to actually collect payment for tours,
 * matching Accommodation/Events) -> Uthenga Checkout (UthengaPay.initiate()).
 */
(function (window) {
  'use strict';

  var TourCheckout = {
    listingId: null,
    listingTitle: null,
    pricePerPerson: 0,

    open: function (listingId, listingTitle, pricePerPerson) {
      this.listingId = listingId;
      this.listingTitle = listingTitle;
      this.pricePerPerson = parseFloat(pricePerPerson || 0);
      document.getElementById('tour-checkout-title').textContent = 'Book: ' + listingTitle;
      document.getElementById('tour-checkout-msg').textContent = '';
      document.getElementById('tour-checkout-qty').value = 1;
      var todayPlus7 = new Date();
      todayPlus7.setDate(todayPlus7.getDate() + 7);
      document.getElementById('tour-checkout-date').value = todayPlus7.toISOString().slice(0, 10);
      document.getElementById('tour-checkout-date').min = new Date().toISOString().slice(0, 10);
      this.updateTotal();
      openModal('tour-checkout-modal');
    },

    updateTotal: function () {
      var qty = Math.max(1, parseInt(document.getElementById('tour-checkout-qty').value, 10) || 1);
      var total = this.pricePerPerson * qty;
      document.getElementById('tour-checkout-total').textContent = 'MK ' + total.toLocaleString();
    },

    csrfToken: function () {
      var meta = document.querySelector('meta[name="csrf-token"]');
      return meta ? meta.content : '';
    },

    reserve: function () {
      var msg = document.getElementById('tour-checkout-msg');
      var qty = Math.max(1, parseInt(document.getElementById('tour-checkout-qty').value, 10) || 1);
      var tourDate = document.getElementById('tour-checkout-date').value;
      if (!tourDate) {
        msg.textContent = 'Please choose a tour date.';
        return;
      }
      msg.textContent = 'Booking your tour…';

      var formData = new FormData();
      formData.append('action', 'create_booking');
      formData.append('listing_id', this.listingId);
      formData.append('listing_type', 'tour');
      formData.append('quantity', qty);
      formData.append('tour_date', tourDate);
      formData.append('gateway', 'uthenga pay');
      formData.append('csrf_token', this.csrfToken());

      fetch('/uthenga/request_api.php', { method: 'POST', body: formData })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success) {
            msg.textContent = data.message || 'That could not be booked. Please try again.';
            return;
          }
          var booking = data.booking;
          if (!booking.requires_payment) {
            closeModal('tour-checkout-modal');
            alert(data.message || 'Booking confirmed!');
            location.reload();
            return;
          }
          // requires_payment is true — must always go through real payment
          // collection. If UthengaPay isn't loaded yet, say so honestly
          // rather than silently treating an uncharged booking as confirmed.
          if (typeof UthengaPay === 'undefined') {
            msg.textContent = 'Payment could not start (still loading). Please wait a moment and try again — you have not been charged.';
            return;
          }
          closeModal('tour-checkout-modal');
          UthengaPay.initiate({
            serviceType: 'tour',
            serviceId: booking.listing_id,
            bookingId: booking.id,
            amount: booking.total_price,
            title: booking.listing_title,
            sub: 'Tour' + (booking.quantity > 1 ? ' × ' + booking.quantity + ' people' : '')
          }, function () {
            // Success screen (receipt) is shown by UthengaPay itself.
          });
        })
        .catch(function () {
          msg.textContent = 'That could not be booked right now. Please try again.';
        });
    }
  };

  window.TourCheckout = TourCheckout;
})(window);
