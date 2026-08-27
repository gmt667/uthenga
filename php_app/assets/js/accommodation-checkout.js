/**
 * Real accommodation booking widget — see includes/accommodation_checkout_modal.php.
 */
(function (window) {
  'use strict';

  var AccommodationCheckout = {
    listingId: null,
    listingTitle: null,

    open: function (listingId, listingTitle) {
      this.listingId = listingId;
      this.listingTitle = listingTitle;
      document.getElementById('accom-checkout-listing-id').value = listingId;
      document.getElementById('accom-checkout-title').textContent = 'Book: ' + listingTitle;
      document.getElementById('accom-checkout-msg').textContent = '';
      document.getElementById('accom-checkout-rooms').innerHTML = '';
      openModal('accom-checkout-modal');
    },

    csrfToken: function () {
      var meta = document.querySelector('meta[name="csrf-token"]');
      return meta ? meta.content : '';
    },

    checkAvailability: function () {
      var checkIn = document.getElementById('accom-checkin').value;
      var checkOut = document.getElementById('accom-checkout').value;
      var msg = document.getElementById('accom-checkout-msg');
      var roomsEl = document.getElementById('accom-checkout-rooms');

      if (!checkIn || !checkOut) {
        msg.textContent = 'Please select both a check-in and check-out date.';
        return;
      }

      msg.textContent = 'Checking availability…';
      roomsEl.innerHTML = '';

      var self = this;
      fetch('/uthenga/api/room-availability.php?property_id=' + encodeURIComponent(this.listingId) +
            '&check_in=' + encodeURIComponent(checkIn) + '&check_out=' + encodeURIComponent(checkOut))
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success) {
            msg.textContent = data.message || 'Unable to check availability right now.';
            return;
          }
          var rooms = (data.rooms || []).filter(function (r) { return r.available; });
          if (rooms.length === 0) {
            msg.textContent = 'No rooms are available for those dates.';
            return;
          }
          msg.textContent = '';
          roomsEl.innerHTML = rooms.map(function (room) {
            var total = room.stay_total != null ? room.stay_total : room.price_per_night;
            return (
              '<div class="accom-room-row" style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;padding:0.75rem 0;border-bottom:1px solid rgba(0,0,0,0.08);">' +
                '<div>' +
                  '<strong>' + room.room_name + '</strong><br>' +
                  '<span class="text-sm text-muted">MK ' + Number(room.price_per_night).toLocaleString() + ' / night &middot; Total MK ' + Number(total).toLocaleString() + '</span>' +
                '</div>' +
                '<button type="button" class="btn btn-sm btn-primary" onclick="AccommodationCheckout.reserve(' + room.room_id + ')">Reserve &amp; Pay</button>' +
              '</div>'
            );
          }).join('');
        })
        .catch(function () {
          msg.textContent = 'Unable to check availability right now. Please try again.';
        });
    },

    reserve: function (roomTypeId) {
      var checkIn = document.getElementById('accom-checkin').value;
      var checkOut = document.getElementById('accom-checkout').value;
      var msg = document.getElementById('accom-checkout-msg');
      msg.textContent = 'Holding your room…';

      var self = this;
      fetch('/uthenga/api/accommodation/checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'hold',
          listing_id: this.listingId,
          room_type_id: roomTypeId,
          quantity: 1,
          check_in_date: checkIn,
          check_out_date: checkOut,
          csrf_token: this.csrfToken()
        })
      })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success) {
          msg.textContent = data.error || 'That room could not be held. It may have just been booked.';
          return;
        }
        closeModal('accom-checkout-modal');
        UthengaPay.initiate({
          serviceType: 'accommodation',
          serviceId: data.listing_id,
          bookingId: data.booking_id,
          amount: data.total_price,
          title: data.listing_title,
          sub: data.nights + ' night' + (data.nights === 1 ? '' : 's')
        }, function () {
          // Success screen (receipt) is shown by UthengaPay itself; its own
          // "Close & View Reservation" button reloads the page from there.
        });
      })
      .catch(function () {
        msg.textContent = 'That room could not be held right now. Please try again.';
      });
    }
  };

  window.AccommodationCheckout = AccommodationCheckout;
})(window);
