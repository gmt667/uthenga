/**
 * Uthenga Payment Engine — Client Subsystem & Modal Manager
 */

(function(window) {
  'use strict';

  var UthengaPay = {
    currentIntent: null,
    selectedMethod: 'airtel',
    pollInterval: null,
    onSuccessCallback: null,

    /**
     * Start a payment session for any service (Accommodation, Events, Tours, Transport, Shop)
     */
    initiate: function(params, onSuccess) {
      this.onSuccessCallback = onSuccess || null;
      var title = params.title || 'Uthenga Service Booking';
      var sub   = params.sub   || 'Lilongwe, Malawi';
      var gross = parseFloat(params.amount || 0);

      if (gross <= 0) {
        alert('Invalid payment amount.');
        return;
      }

      // Customer pays exactly the service's own price — Uthenga's commission is
      // deducted from this amount on the backend, never added on top of it.
      document.getElementById('uth-pay-item-title').textContent = title;
      document.getElementById('uth-pay-item-sub').textContent   = sub;
      document.getElementById('uth-pay-amount-total').textContent = 'MK ' + gross.toLocaleString();

      this.showStep(1);
      document.getElementById('uth-pay-overlay').classList.add('active');

      var csrf = document.querySelector('meta[name="csrf-token"]');
      csrf = csrf ? csrf.content : '';

      // Create Intent via API
      var self = this;
      fetch('/uthenga/api/payment/process.php?action=create_intent', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          service_type: params.serviceType || 'accommodation',
          service_id: params.serviceId || 's1',
          booking_id: params.bookingId || '',
          amount: gross,
          currency: 'MWK',
          idempotency_key: 'idemp_' + Date.now(),
          csrf_token: csrf
        })
      })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (data.success) {
          self.currentIntent = data.intent;
        } else {
          self.closeModal(false);
          alert(data.error || 'Unable to start payment. Please try again.');
        }
      })
      .catch(function(err) {
        self.closeModal(false);
        alert('Unable to start payment. Please check your connection and try again.');
      });
    },

    selectMethodTile: function(el) {
      var cards = document.querySelectorAll('.uth-pay-method-card');
      cards.forEach(function(c) { c.classList.remove('selected'); });
      el.classList.add('selected');
      this.selectedMethod = el.getAttribute('data-method') || 'airtel';
    },

    proceedToDetails: function() {
      if (this.selectedMethod === 'bank') {
        var ref = this.currentIntent ? this.currentIntent.intent_ref : 'UTH-8F42K9';
        document.getElementById('uth-pay-bank-ref').textContent = ref;
        this.showStep('bank');
      } else {
        var label = (this.selectedMethod === 'mpamba' ? 'TNM Mpamba' : 'Airtel Money') + ' Payment';
        document.getElementById('uth-pay-phone-title').textContent = label;
        var input = document.getElementById('uth-pay-phone-input');
        input.value = '';
        document.getElementById('uth-pay-phone-feedback').textContent = '';
        this.showStep('phone');
        input.focus();
      }
    },

    // Real-time feedback as the customer types — never blocks typing, just
    // tells them whether what they've entered so far is a valid Airtel
    // (099/098) or TNM Mpamba (088/089) number, with or without +265.
    onPhoneInput: function() {
      var input = document.getElementById('uth-pay-phone-input');
      var feedback = document.getElementById('uth-pay-phone-feedback');
      var raw = input.value.trim();

      if (!raw || typeof UthengaPhone === 'undefined') {
        feedback.textContent = '';
        return;
      }

      var result = UthengaPhone.validate(raw);
      if (result.valid) {
        feedback.style.color = '#10b981';
        feedback.textContent = '✓ ' + result.message;
        if (result.network !== this.selectedMethod && (result.network === 'airtel' || result.network === 'tnm')) {
          var expected = this.selectedMethod === 'mpamba' ? 'tnm' : this.selectedMethod;
          if (result.network !== expected) {
            feedback.style.color = '#f59e0b';
            feedback.textContent = '⚠ This looks like a ' + (result.network === 'airtel' ? 'Airtel Money' : 'TNM Mpamba') + ' number, not ' + (this.selectedMethod === 'mpamba' ? 'TNM Mpamba' : 'Airtel Money') + '.';
          }
        }
      } else {
        feedback.style.color = '#e63946';
        feedback.textContent = raw.length >= 7 ? result.message : '';
      }
    },

    submitPhonePayment: function() {
      var input = document.getElementById('uth-pay-phone-input');
      var raw = input.value.trim();
      var result = (typeof UthengaPhone !== 'undefined') ? UthengaPhone.validate(raw) : { valid: !!raw, normalized: raw };

      if (!result.valid) {
        var feedback = document.getElementById('uth-pay-phone-feedback');
        feedback.style.color = '#e63946';
        feedback.textContent = result.message || 'Please enter a valid mobile number.';
        input.focus();
        return;
      }
      var phone = result.normalized;

      var ref = this.currentIntent ? this.currentIntent.intent_ref : 'UTH-8F42K9';
      document.getElementById('uth-pay-waiting-ref').textContent = ref;
      this.showStep('waiting');

      var csrf = document.querySelector('meta[name="csrf-token"]');
      csrf = csrf ? csrf.content : '';

      // Call API select method
      var self = this;
      fetch('/uthenga/api/payment/process.php?action=select_method', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          intent_ref: ref,
          method: self.selectedMethod,
          phone: phone,
          csrf_token: csrf
        })
      });

      // Start Polling for status
      this.startPolling(ref);
    },

    startPolling: function(intentRef) {
      var self = this;
      if (this.pollInterval) clearInterval(this.pollInterval);

      this.pollInterval = setInterval(function() {
        fetch('/uthenga/api/payment/process.php?action=check_status&intent_ref=' + encodeURIComponent(intentRef))
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data.success && (data.intent.status === 'CONFIRMED' || data.intent.status === 'SETTLED')) {
            clearInterval(self.pollInterval);
            self.showSuccessState(data.receipt_number || 'UTH-RCP-' + intentRef, data.intent.gross_amount);
          } else if (data.success === false && !data.pending) {
            // A genuine terminal outcome (FAILED/EXPIRED/CANCELLED) — stop
            // polling and tell the customer, rather than spinning forever.
            // "pending" (still waiting on the customer's phone) keeps polling.
            clearInterval(self.pollInterval);
            self.showFailedState(data);
          }
        })
        .catch(function(e){});
      }, 3000);
    },

    simulateSandboxSuccess: function() {
      if (this.pollInterval) clearInterval(this.pollInterval);
      var ref = this.currentIntent ? this.currentIntent.intent_ref : 'UTH-8F42K9';
      var csrf = document.querySelector('meta[name="csrf-token"]');
      csrf = csrf ? csrf.content : '';

      var self = this;
      fetch('/uthenga/api/payment/process.php?action=demo_simulate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ intent_ref: ref, csrf_token: csrf })
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        var amount = (data.intent && data.intent.gross_amount) || (self.currentIntent ? self.currentIntent.gross_amount : 0);
        self.showSuccessState(data.receipt_number || ('UTH-RCP-' + ref), amount);
      })
      .catch(function() {
        self.showSuccessState('UTH-RCP-20260810-' + ref, 82000);
      });
    },

    // "I Have Made the Transfer" — this can only ever tell Uthenga the customer
    // BELIEVES they paid. It must never confirm the booking itself; confirmation
    // comes only from a real verified backend state (check_status / the webhook).
    checkStatusNow: function() {
      var ref = this.currentIntent ? this.currentIntent.intent_ref : '';
      if (!ref) return;

      var self = this;
      fetch('/uthenga/api/payment/process.php?action=check_status&intent_ref=' + encodeURIComponent(ref))
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data.success && (data.intent.status === 'CONFIRMED' || data.intent.status === 'SETTLED')) {
            self.showSuccessState(data.receipt_number || 'UTH-RCP-' + ref, data.intent.gross_amount);
          } else if (data.success === false && !data.pending) {
            self.showFailedState(data);
          } else {
            document.getElementById('uth-pay-waiting-ref').textContent = ref;
            self.showStep('waiting');
            self.startPolling(ref);
          }
        })
        .catch(function() {
          document.getElementById('uth-pay-waiting-ref').textContent = ref;
          self.showStep('waiting');
          self.startPolling(ref);
        });
    },

    showFailedState: function(data) {
      var reason = 'Your booking has not been charged.';
      if (data && data.expired) {
        reason = 'This payment session expired before it was completed. Your booking has not been charged.';
      } else if (data && data.intent && data.intent.status === 'CANCELLED') {
        reason = 'This payment was cancelled. Your booking has not been charged.';
      }
      document.getElementById('uth-pay-failed-reason').textContent = reason;
      this.showStep('failed');
    },

    // Both "Try Again" and "Choose Another Method" restart from a clean
    // hold — the underlying intent is already terminal (FAILED/EXPIRED/
    // CANCELLED) and its hold/inventory has already been released, so
    // resuming it in place would risk selling something no longer held.
    // Reloading sends the customer back through the page's own booking
    // flow, which acquires a fresh hold and a fresh intent.
    retryPayment: function() {
      this.closeModal(true);
    },

    showSuccessState: function(receiptNo, amount) {
      document.getElementById('uth-pay-receipt-no').textContent = receiptNo;
      document.getElementById('uth-pay-paid-amount').textContent = 'MK ' + parseFloat(amount || 0).toLocaleString();
      this.showStep('success');

      if (typeof this.onSuccessCallback === 'function') {
        this.onSuccessCallback(receiptNo, this.currentIntent);
      }
    },

    downloadReceipt: function() {
      var receiptNo = document.getElementById('uth-pay-receipt-no').textContent;
      window.open('/uthenga/payments/receipt.php?receipt=' + encodeURIComponent(receiptNo), '_blank');
    },

    showStep: function(step) {
      ['1', 'phone', 'bank', 'waiting', 'failed', 'success'].forEach(function(s) {
        var el = document.getElementById('uth-pay-step-' + s);
        if (el) el.style.display = (String(s) === String(step)) ? 'block' : 'none';
      });
    },

    closeModal: function(reloadPage) {
      if (this.pollInterval) clearInterval(this.pollInterval);

      // Closing without a reload means the customer walked away before paying
      // (the success screen's own close button always passes reloadPage=true) —
      // tell the engine so the hold/inventory this intent reserved is released
      // now, instead of sitting orphaned until it lazily expires.
      if (!reloadPage && this.currentIntent && this.currentIntent.intent_ref) {
        var csrf = document.querySelector('meta[name="csrf-token"]');
        csrf = csrf ? csrf.content : '';
        fetch('/uthenga/api/payment/process.php?action=cancel_intent', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ intent_ref: this.currentIntent.intent_ref, csrf_token: csrf })
        }).catch(function(e){});
        this.currentIntent = null;
      }

      document.getElementById('uth-pay-overlay').classList.remove('active');
      if (reloadPage) {
        window.location.reload();
      }
    }
  };

  window.UthengaPay = UthengaPay;
})(window);
