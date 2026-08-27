<?php
/**
 * Uthenga Payment Engine — Reusable Payment Experience Modal
 * Embedded into any customer page (Accommodation, Events, Tours, Transport, Shop, Quick Travel).
 *
 * Self-links its own stylesheet rather than assuming the calling page already
 * loaded it via includes/footer.php — pages like dashboard.php never include
 * footer.php, and without this the modal previously rendered with no
 * position/z-index at all (position: static), landing wherever it happened
 * to fall in normal document flow instead of as a full-screen overlay. Safe
 * to include twice on pages that DO already link it via footer.php —
 * browsers dedupe an identical stylesheet URL.
 */
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/uthenga-payment.css?v=<?= rawurlencode(APP_VERSION) ?>">
<!-- UTHENGA PAYMENT MODAL CONTAINER -->
<div class="uth-pay-overlay" id="uth-pay-overlay">
  <div class="uth-pay-modal" id="uth-pay-modal">
    <div class="uth-pay-header">
      <div class="uth-pay-brand">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
        <span>UTHENGA PAYMENT</span>
      </div>
      <button type="button" class="uth-pay-close" onclick="UthengaPay.closeModal()" aria-label="Close modal">✕</button>
    </div>

    <div class="uth-pay-body">

      <!-- STEP 1: SUMMARY & METHOD SELECTION -->
      <div id="uth-pay-step-1">
        <div class="uth-pay-summary">
          <h3 class="uth-pay-title" id="uth-pay-item-title">Mango Lodge Accommodation</h3>
          <p class="uth-pay-subtitle" id="uth-pay-item-sub">2 Nights • Lilongwe</p>

          <div class="uth-pay-row uth-pay-total">
            <span>Amount to Pay</span>
            <strong id="uth-pay-amount-total" style="color:#e63946;">MK 80,000</strong>
          </div>
        </div>

        <div style="font-size:0.8rem;font-weight:700;color:#94a3b8;margin-bottom:0.6rem;text-transform:uppercase;letter-spacing:0.05em;">How would you like to pay?</div>

        <div class="uth-pay-methods">
          <div class="uth-pay-method-card selected" data-method="airtel" onclick="UthengaPay.selectMethodTile(this)">
            <div class="uth-pay-icon" style="background:rgba(239,68,68,0.15);color:#ef4444;">📱</div>
            <div style="flex:1;">
              <div style="font-weight:700;font-size:0.9rem;">Airtel Money</div>
              <div style="font-size:0.75rem;color:#94a3b8;">Pay using your Airtel wallet</div>
            </div>
          </div>

          <div class="uth-pay-method-card" data-method="mpamba" onclick="UthengaPay.selectMethodTile(this)">
            <div class="uth-pay-icon" style="background:rgba(59,130,246,0.15);color:#3b82f6;">📱</div>
            <div style="flex:1;">
              <div style="font-weight:700;font-size:0.9rem;">TNM Mpamba</div>
              <div style="font-size:0.75rem;color:#94a3b8;">Pay using your Mpamba wallet</div>
            </div>
          </div>

          <div class="uth-pay-method-card" data-method="bank" onclick="UthengaPay.selectMethodTile(this)">
            <div class="uth-pay-icon" style="background:rgba(16,185,129,0.15);color:#10b981;">🏦</div>
            <div style="flex:1;">
              <div style="font-weight:700;font-size:0.9rem;">Bank Transfer</div>
              <div style="font-size:0.75rem;color:#94a3b8;">Pay from your bank account</div>
            </div>
          </div>
        </div>

        <button type="button" class="uth-pay-btn-primary" onclick="UthengaPay.proceedToDetails()">
          Continue to Payment →
        </button>

        <div style="text-align:center;font-size:0.7rem;color:#64748b;margin-top:0.85rem;">
          🔒 Secure payment powered by Uthenga Engine
        </div>
      </div>

      <!-- STEP 2A: MOBILE MONEY PHONE INPUT -->
      <div id="uth-pay-step-phone" style="display:none;">
        <h3 class="uth-pay-title" id="uth-pay-phone-title">Airtel Money Payment</h3>
        <p class="uth-pay-subtitle">Enter your mobile number to receive a payment prompt on your phone.</p>

        <div style="margin-bottom:1.25rem;">
          <label style="font-size:0.72rem;color:#94a3b8;font-weight:700;display:block;margin-bottom:0.35rem;text-transform:uppercase;">Mobile Phone Number</label>
          <input type="tel" id="uth-pay-phone-input" style="width:100%;height:44px;background:#090d16;border:1px solid rgba(255,255,255,0.12);border-radius:10px;color:#fff;padding:0 1rem;font-size:0.95rem;outline:none;" placeholder="099X XXX XXX or +265 99X XXX XXX" oninput="UthengaPay.onPhoneInput()">
          <div id="uth-pay-phone-feedback" style="font-size:0.75rem;margin-top:0.4rem;min-height:1em;"></div>
        </div>

        <button type="button" class="uth-pay-btn-primary" id="uth-pay-phone-submit" onclick="UthengaPay.submitPhonePayment()">
          Send Payment Request
        </button>

        <button type="button" style="width:100%;background:none;border:none;color:#94a3b8;font-size:0.8rem;margin-top:0.75rem;cursor:pointer;" onclick="UthengaPay.showStep(1)">
          ← Choose another method
        </button>
      </div>

      <!-- STEP 2B: BANK TRANSFER DETAILS -->
      <div id="uth-pay-step-bank" style="display:none;">
        <h3 class="uth-pay-title">Bank Transfer Instructions</h3>
        <p class="uth-pay-subtitle">Transfer the exact amount to our National Bank account below.</p>

        <div class="uth-pay-bank-box">
          <div style="font-size:0.72rem;color:#94a3b8;text-transform:uppercase;">Bank Name</div>
          <strong style="font-size:1rem;color:#fff;">National Bank of Malawi</strong>

          <div style="margin-top:0.75rem;font-size:0.72rem;color:#94a3b8;text-transform:uppercase;">Account Number</div>
          <strong style="font-size:1.2rem;color:#10b981;" id="uth-pay-bank-account">1004829103</strong>

          <div style="margin-top:0.75rem;font-size:0.72rem;color:#94a3b8;text-transform:uppercase;">Transaction Reference</div>
          <strong style="font-size:1.1rem;color:#e63946;" id="uth-pay-bank-ref">UTH-8F42K9</strong>
        </div>

        <div style="font-size:0.75rem;color:#94a3b8;text-align:center;margin-bottom:1rem;">
          Session expires in: <span id="uth-pay-timer" style="color:#f59e0b;font-weight:700;">59:42</span>
        </div>

        <button type="button" class="uth-pay-btn-primary" onclick="UthengaPay.checkStatusNow()">
          I Have Made the Transfer
        </button>
      </div>

      <!-- STEP 3: WAITING FOR AUTHORIZATION -->
      <div id="uth-pay-step-waiting" style="display:none;text-align:center;">
        <div class="uth-pay-spinner"></div>
        <h3 class="uth-pay-title" style="margin-top:0.5rem;">Waiting for Payment</h3>
        <p class="uth-pay-subtitle">A payment request has been sent to your phone.<br>Please authorize the transaction on your mobile device.</p>

        <div style="background:#090d16;border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:0.75rem;font-size:0.8rem;margin:1rem 0;color:#cbd5e1;">
          Reference: <strong id="uth-pay-waiting-ref" style="color:#e63946;">UTH-8F42K9</strong>
        </div>

        <?php if (APP_ENV === 'development'): ?>
        <button type="button" class="uth-pay-btn-primary" style="background:#10b981;" onclick="UthengaPay.simulateSandboxSuccess()">
          ⚡ Simulate Phone Authorization (Sandbox)
        </button>
        <?php endif; ?>
      </div>

      <!-- STEP 3B: PAYMENT FAILED / EXPIRED / CANCELLED -->
      <div id="uth-pay-step-failed" style="display:none;text-align:center;">
        <div style="width:54px;height:54px;border-radius:50%;background:rgba(230,57,70,0.15);color:#e63946;font-size:1.8rem;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
          ✕
        </div>
        <h3 class="uth-pay-title" style="font-size:1.15rem;">Payment could not be confirmed</h3>
        <p class="uth-pay-subtitle" id="uth-pay-failed-reason">Your booking has not been charged.</p>

        <button type="button" class="uth-pay-btn-primary" onclick="UthengaPay.retryPayment()">
          Try Again
        </button>

        <button type="button" style="width:100%;background:none;border:none;color:#94a3b8;font-size:0.82rem;margin-top:0.75rem;cursor:pointer;" onclick="UthengaPay.retryPayment()">
          Choose Another Method
        </button>
      </div>

      <!-- STEP 4: SUCCESS & RECEIPT -->
      <div id="uth-pay-step-success" style="display:none;text-align:center;">
        <div style="width:54px;height:54px;border-radius:50%;background:rgba(16,185,129,0.15);color:#10b981;font-size:1.8rem;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
          ✓
        </div>
        <h3 class="uth-pay-title" style="font-size:1.25rem;">Payment Confirmed!</h3>
        <p class="uth-pay-subtitle">Your transaction was verified and your booking is active.</p>

        <div class="uth-pay-summary" style="text-align:left;">
          <div class="uth-pay-row"><span>Receipt Number</span><strong id="uth-pay-receipt-no">UTH-RCP-20260810-8F42</strong></div>
          <div class="uth-pay-row"><span>Payment Status</span><strong style="color:#10b981;">VERIFIED</strong></div>
          <div class="uth-pay-row"><span>Amount Paid</span><strong id="uth-pay-paid-amount">MK 82,000</strong></div>
        </div>

        <button type="button" class="uth-pay-btn-primary" onclick="UthengaPay.downloadReceipt()">
          Download Receipt PDF
        </button>

        <button type="button" style="width:100%;background:none;border:none;color:#94a3b8;font-size:0.82rem;margin-top:0.75rem;cursor:pointer;" onclick="UthengaPay.closeModal(true)">
          Close &amp; View Reservation
        </button>
      </div>

    </div>
  </div>
</div>

<script src="<?= BASE_URL ?>assets/js/phone-validate.js?v=<?= rawurlencode(APP_VERSION) ?>"></script>
<script src="<?= BASE_URL ?>assets/js/uthenga-payment.js?v=<?= rawurlencode(APP_VERSION) ?>"></script>
