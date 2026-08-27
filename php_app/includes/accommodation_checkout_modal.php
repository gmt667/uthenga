<?php
/**
 * Real accommodation booking widget — dates -> real room availability/pricing
 * (api/room-availability.php) -> real time-bound hold + Pending booking
 * (api/accommodation/checkout.php) -> Uthenga Checkout (UthengaPay.initiate()).
 * Included only by hotels.php for now.
 */
?>
<div class="modal-overlay" id="accom-checkout-modal" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="modal">
    <div class="modal-header">
      <h3 id="accom-checkout-title">Book Your Stay</h3>
      <button class="modal-close" onclick="closeModal('accom-checkout-modal')">Close</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="accom-checkout-listing-id" value="">

      <div class="form-group">
        <label class="form-label" for="accom-checkin">Check-in Date</label>
        <input type="date" id="accom-checkin" class="form-control" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="accom-checkout">Check-out Date</label>
        <input type="date" id="accom-checkout" class="form-control" required>
      </div>

      <button type="button" class="btn btn-primary" style="width:100%;margin-bottom:1rem;" onclick="AccommodationCheckout.checkAvailability()">
        Check Availability
      </button>

      <div id="accom-checkout-msg" class="text-sm text-muted" style="margin-bottom:0.75rem;"></div>
      <div id="accom-checkout-rooms"></div>
    </div>
  </div>
</div>
