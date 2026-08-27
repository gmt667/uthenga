/**
 * Real bus ticket purchase widget — reuses the existing, proven TIE bus
 * operations endpoints verbatim (api/tie/transport/search.php,
 * payment-methods.php, purchase.php, purchase-status.php); no new backend
 * logic. Mirrors bus-tickets.php's own real search -> seat-class selection ->
 * saved-payment-method -> purchase -> poll flow, just triggered from a
 * dashboard-native modal instead of a separate page.
 */
(function (window) {
  'use strict';

  var BusCheckout = {
    departures: [],
    selectedDeparture: null,
    selectedSeat: null,
    quantity: 1,
    paymentMethodId: null,
    pollTimer: null,

    csrfToken: function () {
      var meta = document.querySelector('meta[name="csrf-token"]');
      return meta ? meta.content : '';
    },

    open: function () {
      this.showStep('search');
      openModal('real-bus-checkout-modal');
      var dateInput = document.getElementById('bus-search-date');
      if (dateInput && !dateInput.value) dateInput.value = new Date().toISOString().slice(0, 10);
    },

    showStep: function (step) {
      ['search', 'results', 'seat', 'payment', 'waiting', 'ticket'].forEach(function (s) {
        var el = document.getElementById('bus-step-' + s);
        if (el) el.style.display = (s === step) ? 'block' : 'none';
      });
    },

    search: function () {
      var origin = document.getElementById('bus-search-from').value.trim();
      var destination = document.getElementById('bus-search-to').value.trim();
      var date = document.getElementById('bus-search-date').value;
      var msg = document.getElementById('bus-results-msg');
      var list = document.getElementById('bus-results-list');
      msg.textContent = 'Searching…';
      list.innerHTML = '';
      this.showStep('results');

      var qs = 'origin=' + encodeURIComponent(origin) + '&destination=' + encodeURIComponent(destination) + '&date=' + encodeURIComponent(date);
      fetch('/uthenga/api/tie/transport/search.php?' + qs, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success) {
            msg.textContent = (data.error && data.error.message) || 'Unable to search right now.';
            return;
          }
          var departures = data.result.departures || [];
          BusCheckout.departures = departures;
          if (departures.length === 0) {
            msg.textContent = 'No scheduled departures found for those details.';
            return;
          }
          msg.textContent = '';
          list.innerHTML = departures.map(function (dep, idx) {
            var when = new Date(dep.departure_at);
            var seatRows = (dep.seat_classes || []).filter(function (c) { return c.remaining_seats > 0; }).map(function (c) {
              return '<div style="display:flex;justify-content:space-between;align-items:center;padding:.4rem 0;">' +
                '<span>' + c.class_name + ' — MK ' + Number(c.price).toLocaleString() + ' (' + c.remaining_seats + ' left)</span>' +
                '<button type="button" class="btn btn-sm btn-primary" onclick="BusCheckout.selectSeat(' + idx + ',' + c.departure_seat_id + ')">Select</button>' +
              '</div>';
            }).join('') || '<div class="text-muted text-sm">Sold out.</div>';
            return '<div style="border:1px solid rgba(0,0,0,.1);border-radius:10px;padding:.85rem;margin-bottom:.75rem;">' +
              '<strong>' + dep.title + '</strong> — ' + dep.operator + '<br>' +
              '<span class="text-sm text-muted">' + dep.origin + ' → ' + dep.destination + ' · ' + when.toLocaleString() + '</span>' +
              (dep.vehicle ? '<br><span class="text-xs text-muted">' + dep.vehicle.make_model + ' (' + dep.vehicle.reg_number + ')</span>' : '<br><span class="text-xs" style="color:#f59e0b;">Vehicle not yet assigned</span>') +
              '<div style="margin-top:.5rem;">' + seatRows + '</div>' +
            '</div>';
          }).join('');
        })
        .catch(function () { msg.textContent = 'Unable to search right now. Please try again.'; });
    },

    selectSeat: function (departureIdx, departureSeatId) {
      var dep = this.departures[departureIdx];
      var seat = (dep.seat_classes || []).find(function (c) { return c.departure_seat_id === departureSeatId; });
      if (!seat) return;
      this.selectedDeparture = dep;
      this.selectedSeat = seat;
      this.quantity = 1;

      document.getElementById('bus-seat-summary').innerHTML =
        '<strong>' + dep.title + '</strong> · ' + dep.origin + ' → ' + dep.destination + '<br>' +
        seat.class_name + ' — MK ' + Number(seat.price).toLocaleString() + ' per seat';
      document.getElementById('bus-passenger-qty').max = Math.min(seat.remaining_seats, 10);
      document.getElementById('bus-passenger-qty').value = 1;
      document.getElementById('bus-passenger-name').value = '';
      document.getElementById('bus-passenger-phone').value = '';
      document.getElementById('bus-seat-msg').textContent = '';
      this.showStep('seat');
    },

    proceedToPayment: function () {
      var qty = Math.max(1, Math.min(parseInt(document.getElementById('bus-passenger-qty').value, 10) || 1, this.selectedSeat.remaining_seats, 10));
      var name = document.getElementById('bus-passenger-name').value.trim();
      var phone = document.getElementById('bus-passenger-phone').value.trim();
      var msg = document.getElementById('bus-seat-msg');

      if (!name) { msg.textContent = 'Please enter the lead passenger name.'; return; }
      if (phone && typeof UthengaPhone !== 'undefined') {
        var check = UthengaPhone.validate(phone);
        if (!check.valid) { msg.textContent = check.message; return; }
        phone = check.normalized;
      }

      this.quantity = qty;
      this.passengers = [];
      for (var i = 0; i < qty; i++) {
        this.passengers.push({ name: qty > 1 ? name + ' (Passenger ' + (i + 1) + ')' : name, phone: phone || null });
      }

      document.getElementById('bus-payment-msg').textContent = 'Loading your payment methods…';
      document.getElementById('bus-payment-methods').innerHTML = '';
      document.getElementById('bus-add-method-form').style.display = 'none';
      this.showStep('payment');
      this.loadPaymentMethods();
    },

    loadPaymentMethods: function () {
      var msg = document.getElementById('bus-payment-msg');
      var self = this;
      fetch('/uthenga/api/tie/transport/payment-methods.php?action=list', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success) { msg.textContent = 'Could not load payment methods.'; return; }
          var methods = data.result.methods || [];
          if (methods.length === 0) {
            msg.textContent = 'Add a mobile money number to pay for this ticket.';
            self.loadOperators();
            return;
          }
          msg.textContent = '';
          document.getElementById('bus-payment-methods').innerHTML = methods.map(function (m) {
            var label = m.channel === 'mobile_money' ? (m.operator_name + ' — ' + m.mobile_number_masked) : ('Bank Transfer — ' + (m.account_number || ''));
            return '<div class="uth-pay-method-card" style="cursor:pointer;padding:.6rem;border:1px solid rgba(0,0,0,.1);border-radius:8px;margin-bottom:.4rem;" onclick="BusCheckout.choosePaymentMethod(\'' + m.id + '\', this)">' + label + '</div>';
          }).join('') + '<button type="button" class="btn btn-sm btn-secondary" style="margin-top:.5rem;" onclick="BusCheckout.loadOperators()">+ Add another mobile money number</button>';
        })
        .catch(function () { msg.textContent = 'Could not load payment methods. Please try again.'; });
    },

    choosePaymentMethod: function (id, el) {
      this.paymentMethodId = id;
      document.querySelectorAll('#bus-payment-methods .uth-pay-method-card').forEach(function (c) { c.style.borderColor = 'rgba(0,0,0,.1)'; });
      if (el) el.style.borderColor = '#2563eb';
      document.getElementById('bus-purchase-btn').style.display = 'block';
    },

    loadOperators: function () {
      var wrap = document.getElementById('bus-add-method-form');
      wrap.style.display = 'block';
      fetch('/uthenga/api/tie/transport/payment-methods.php?action=operators', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          var operators = (data.success && data.result.operators) || [];
          var select = document.getElementById('bus-operator-select');
          select.innerHTML = operators.map(function (op) {
            return '<option value="' + op.ref_id + '">' + op.name + '</option>';
          }).join('');
        });
    },

    addPaymentMethod: function () {
      var msg = document.getElementById('bus-payment-msg');
      var operatorRefId = document.getElementById('bus-operator-select').value;
      var mobileRaw = document.getElementById('bus-new-method-phone').value.trim();
      var check = (typeof UthengaPhone !== 'undefined') ? UthengaPhone.validate(mobileRaw) : { valid: !!mobileRaw, normalized: mobileRaw };
      if (!check.valid) { msg.textContent = check.message; return; }

      msg.textContent = 'Saving…';
      fetch('/uthenga/api/tie/transport/payment-methods.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrfToken() },
        body: JSON.stringify({ action: 'add_mobile_money', mobile: check.normalized, operator_ref_id: operatorRefId })
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success) {
            msg.textContent = (data.error && data.error.message) || 'Could not save that number.';
            return;
          }
          document.getElementById('bus-add-method-form').style.display = 'none';
          BusCheckout.loadPaymentMethods();
        })
        .catch(function () { msg.textContent = 'Could not save that number. Please try again.'; });
    },

    purchase: function () {
      var msg = document.getElementById('bus-payment-msg');
      if (!this.paymentMethodId) { msg.textContent = 'Choose a payment method first.'; return; }
      msg.textContent = 'Charging…';

      fetch('/uthenga/api/tie/transport/purchase.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrfToken() },
        body: JSON.stringify({
          departure_seat_id: this.selectedSeat.departure_seat_id,
          quantity: this.quantity,
          passengers: this.passengers,
          payment_method_id: this.paymentMethodId
        })
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success) {
            msg.textContent = (data.error && data.error.message) || 'That could not be purchased. Please try again.';
            return;
          }
          BusCheckout.bookingId = data.result.booking_id;
          document.getElementById('bus-waiting-msg').textContent = data.result.instructions || 'Confirming your payment…';
          BusCheckout.showStep('waiting');
          BusCheckout.pollStatus();
        })
        .catch(function () { msg.textContent = 'That could not be purchased right now. Please try again.'; });
    },

    pollStatus: function () {
      if (this.pollTimer) clearInterval(this.pollTimer);
      var bookingId = this.bookingId;
      this.pollTimer = setInterval(function () {
        fetch('/uthenga/api/tie/transport/purchase-status.php?booking_id=' + encodeURIComponent(bookingId), { credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (!data.success) return;
            if (data.result.payment_status === 'Paid') {
              clearInterval(BusCheckout.pollTimer);
              BusCheckout.showTicket(data.result.tickets || []);
            } else if (data.result.payment_status === 'Failed') {
              clearInterval(BusCheckout.pollTimer);
              document.getElementById('bus-waiting-msg').textContent = 'Payment did not go through. Your seat has been released — please try again.';
            }
          });
      }, 4000);
    },

    showTicket: function (tickets) {
      var el = document.getElementById('bus-ticket-details');
      if (tickets.length === 0) {
        el.innerHTML = '<p>Payment received! Your ticket is confirmed.</p>';
      } else {
        el.innerHTML = tickets.map(function (t) {
          return '<div style="border:1px solid rgba(0,0,0,.1);border-radius:10px;padding:.75rem;margin-bottom:.5rem;">' +
            '<strong>' + t.title + '</strong><br>' +
            new Date(t.departure_at).toLocaleString() + '<br>' +
            'Seat class fare: MK ' + Number(t.fare).toLocaleString() +
          '</div>';
        }).join('');
      }
      this.showStep('ticket');
    }
  };

  window.BusCheckout = BusCheckout;
})(window);
