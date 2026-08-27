/**
 * Real event ticket purchase widget — real ticket types (api/event-ticket-types.php)
 * -> the existing, proven request_api.php create_booking path (real inventory
 * decrement + stale-hold sweep + Pending booking) -> Uthenga Checkout
 * (UthengaPay.initiate()), the exact same real call assets/js/main.js already
 * makes from event-details.php, just without that page's legacy
 * gateway-button/"Simulation Mode" UI in between.
 */
(function (window) {
  'use strict';

  var EventCheckout = {
    listingId: null,
    listingTitle: null,

    open: function (listingId, listingTitle) {
      this.listingId = listingId;
      this.listingTitle = listingTitle;
      document.getElementById('evt-checkout-title').textContent = 'Get Tickets: ' + listingTitle;
      document.getElementById('evt-checkout-msg').textContent = 'Loading ticket types…';
      document.getElementById('evt-checkout-types').innerHTML = '';
      openModal('evt-checkout-modal');
      this.loadTicketTypes();
    },

    csrfToken: function () {
      var meta = document.querySelector('meta[name="csrf-token"]');
      return meta ? meta.content : '';
    },

    loadTicketTypes: function () {
      var msg = document.getElementById('evt-checkout-msg');
      var typesEl = document.getElementById('evt-checkout-types');
      var self = this;

      fetch('/uthenga/api/event-ticket-types.php?event_id=' + encodeURIComponent(this.listingId))
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success) {
            msg.textContent = data.message || 'Unable to load ticket types right now.';
            return;
          }
          var types = (data.ticket_types || []).filter(function (t) { return t.available; });
          if (types.length === 0) {
            msg.textContent = 'No tickets are currently available for this event.';
            return;
          }
          msg.textContent = '';
          typesEl.innerHTML = types.map(function (t, idx) {
            return (
              '<div class="evt-ticket-row" style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;padding:0.75rem 0;border-bottom:1px solid rgba(0,0,0,0.08);">' +
                '<div>' +
                  '<strong>' + t.name + '</strong><br>' +
                  '<span class="text-sm text-muted">MK ' + Number(t.price).toLocaleString() + ' &middot; ' + t.remaining + ' left</span>' +
                '</div>' +
                '<div style="display:flex;align-items:center;gap:0.5rem;">' +
                  '<input type="number" min="1" max="' + Math.max(1, Math.min(t.remaining, 10)) + '" value="1" id="evt-qty-' + idx + '" style="width:56px;height:36px;text-align:center;border-radius:8px;border:1px solid rgba(0,0,0,0.15);">' +
                  '<button type="button" class="btn btn-sm btn-primary" onclick="EventCheckout.reserve(' + (t.ticket_type_id || 0) + ',\'' + t.name.replace(/'/g, "\\'") + '\',' + idx + ')">Buy</button>' +
                '</div>' +
              '</div>'
            );
          }).join('');
        })
        .catch(function () {
          msg.textContent = 'Unable to load ticket types right now. Please try again.';
        });
    },

    reserve: function (ticketTypeId, ticketTypeName, qtyInputIdx) {
      var msg = document.getElementById('evt-checkout-msg');
      var qtyInput = document.getElementById('evt-qty-' + qtyInputIdx);
      var quantity = Math.max(1, parseInt(qtyInput ? qtyInput.value : '1', 10) || 1);
      msg.textContent = 'Reserving your tickets…';

      var formData = new FormData();
      formData.append('action', 'create_booking');
      formData.append('listing_id', this.listingId);
      formData.append('listing_type', 'event');
      formData.append('ticket_type_id', ticketTypeId || 0);
      formData.append('ticket_type', ticketTypeName);
      formData.append('quantity', quantity);
      formData.append('gateway', 'uthenga pay');
      formData.append('csrf_token', this.csrfToken());

      fetch('/uthenga/request_api.php', { method: 'POST', body: formData })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success) {
            msg.textContent = data.message || 'That could not be booked. It may have just sold out.';
            return;
          }
          var booking = data.booking;
          if (!booking.requires_payment) {
            closeModal('evt-checkout-modal');
            alert(data.message || 'Booking confirmed!');
            location.reload();
            return;
          }
          // requires_payment is true — this must always go through real
          // payment collection. If UthengaPay somehow isn't loaded yet (a
          // script-loading race on a large page), say so honestly rather
          // than silently treating an uncharged booking as confirmed — the
          // booking stays real and Pending either way; nothing is lost.
          if (typeof UthengaPay === 'undefined') {
            msg.textContent = 'Payment could not start (still loading). Please wait a moment and try again — you have not been charged.';
            return;
          }
          closeModal('evt-checkout-modal');
          UthengaPay.initiate({
            serviceType: 'event',
            serviceId: booking.listing_id,
            bookingId: booking.id,
            amount: booking.total_price,
            title: booking.listing_title,
            sub: 'Event Ticket' + (booking.quantity > 1 ? ' × ' + booking.quantity : '')
          }, function () {
            // Success screen (receipt) is shown by UthengaPay itself.
          });
        })
        .catch(function () {
          msg.textContent = 'That could not be booked right now. Please try again.';
        });
    }
  };

  window.EventCheckout = EventCheckout;
})(window);
