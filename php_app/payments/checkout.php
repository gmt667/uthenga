<?php
/**
 * Uthenga — Unified Checkout Page
 * Supports: PayChangu, Airtel Money, TNM Mpamba, Visa/Mastercard
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle  = 'Checkout';
$userId     = $_SESSION['user_id'];
$userEmail  = $_SESSION['user_email'] ?? '';
$userName   = $_SESSION['user_name'] ?? '';

// ── Resolve the booking/order ─────────────────────────────────────────────────
$bookingId   = $_GET['booking_id'] ?? '';
$bookingType = $_GET['type'] ?? '';   // event | stay | tour | transport | other
$amount      = 0;
$description = '';
$booking     = null;
$bookingCode = '';

if ($bookingId) {
    // Resolve the booking against the current booking schema.
    $booking = dbQueryOne(
        "SELECT b.*, u.name AS customer_name, u.email AS customer_email
         FROM bookings b
         INNER JOIN users u ON u.id = b.customer_id
         WHERE b.id = ? AND b.customer_id = ?",
        [$bookingId, $userId]
    );
    if ($booking) {
        $amount      = (float)($booking['grand_total'] ?? $booking['total_amount'] ?? 0);
        $bookingCode = $booking['booking_code'] ?? (string)$bookingId;
        $description = $booking['reference_name'] ?? $booking['booking_code'] ?? ('Booking #' . $bookingId);
        if ($bookingType === '') {
            $bookingType = $booking['booking_channel'] ?? 'other';
        }
    }
}

// Allow direct amount (e.g., from trip-planner or ad-hoc flow)
if (!$booking && isset($_GET['amount'])) {
    $amount      = max(0, (float)$_GET['amount']);
    $description = urldecode($_GET['desc'] ?? 'Uthenga Payment');
}

// ── Gateway config (from .env) ────────────────────────────────────────────────
$paychanguKey  = uthenga_env('PAYCHANGU_SECRET_KEY', '');
$airtelKey     = uthenga_env('AIRTEL_API_KEY', '');
$tnmKey        = uthenga_env('TNM_API_KEY', '');
$cardEnabled   = (bool)uthenga_env('CARD_PAYMENTS_ENABLED', false);

$gateways = [
    'paychangu' => [
        'label'   => 'PayChangu',
        'short'   => 'PC',
        'desc'    => 'Pay securely via PayChangu (Visa, Mastercard, Mobile Money)',
        'enabled' => (bool)$paychanguKey,
    ],
    'airtel' => [
        'label'   => 'Airtel Money',
        'short'   => 'AM',
        'desc'    => 'Pay with Airtel Money — fast USSD push',
        'enabled' => (bool)$airtelKey,
    ],
    'tnm' => [
        'label'   => 'TNM Mpamba',
        'short'   => 'TM',
        'desc'    => 'Pay with TNM Mpamba mobile money',
        'enabled' => (bool)$tnmKey,
    ],
    'card' => [
        'label'   => 'Visa / Mastercard',
        'short'   => 'CC',
        'desc'    => 'Pay by card — 3D Secure enabled',
        'enabled' => $cardEnabled,
    ],
];

$anyGatewayEnabled = array_filter($gateways, fn($g) => $g['enabled']);

function uthenga_checkout_split_name(string $fullName): array {
    $parts = preg_split('/\s+/', trim($fullName)) ?: [];
    $first = $parts[0] ?? 'Uthenga';
    $last = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'Customer';
    return [$first, $last];
}

function uthenga_checkout_update_transaction(string $txnRef, array $data): void {
    if ($txnRef === '' || !$data) {
        return;
    }

    $set = [];
    $params = [];
    foreach ($data as $column => $value) {
        $set[] = $column . ' = ?';
        $params[] = $value;
    }
    $params[] = $txnRef;
    dbExecute('UPDATE transactions SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE transaction_reference = ?', $params);
}

function uthenga_paychangu_initialize(array $txn, array $booking, string $userEmail, string $userName, string $phone): array {
    $apiKey = PAYCHANGU_PUBLIC_KEY !== '' ? PAYCHANGU_PUBLIC_KEY : PAYCHANGU_SECRET_KEY;
    if ($apiKey === '') {
        return ['success' => false, 'message' => 'PayChangu credentials are not configured.'];
    }
    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'cURL is not available on this server.'];
    }

    [$firstName, $lastName] = uthenga_checkout_split_name($userName);
    $payload = [
        'amount' => (float)($txn['amount'] ?? 0),
        'currency' => $txn['currency'] ?? APP_CURRENCY,
        'email' => $userEmail,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'tx_ref' => $txn['transaction_reference'],
        'callback_url' => PAYCHANGU_CALLBACK_URL,
        'return_url' => PAYCHANGU_RETURN_URL . '?txn=' . urlencode($txn['transaction_reference']),
        'description' => $booking['reference_name'] ?? ($txn['metadata']['booking_code'] ?? 'Uthenga booking'),
        'customization' => [
            'title' => APP_NAME,
            'description' => 'Secure checkout for Uthenga bookings',
        ],
        'metadata' => [
            'booking_id' => $txn['booking_id'] ?? null,
            'booking_code' => $booking['booking_code'] ?? null,
            'booking_type' => $txn['metadata']['booking_type'] ?? null,
            'phone' => $phone ?: null,
        ],
    ];

    $ch = curl_init(rtrim(PAYCHANGU_API_BASE_URL, '/') . PAYCHANGU_INIT_PATH);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 30,
    ]);

    $responseBody = curl_exec($ch);
    $curlErr = curl_error($ch);
    $curlCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($responseBody === false || $responseBody === null) {
        return ['success' => false, 'message' => $curlErr ?: 'Unable to initialize PayChangu payment.'];
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        return ['success' => false, 'message' => 'PayChangu returned an invalid response.'];
    }

    $gatewayRef = trim((string)($decoded['reference'] ?? $decoded['data']['reference'] ?? $decoded['data']['tx_ref'] ?? $txn['transaction_reference']));
    $checkoutUrl = trim((string)($decoded['checkout_url'] ?? $decoded['data']['checkout_url'] ?? $decoded['data']['link'] ?? $decoded['payment_url'] ?? $decoded['url'] ?? ''));
    $success = $curlCode >= 200 && $curlCode < 300 && ($checkoutUrl !== '' || !empty($decoded['success']));
    $message = (string)($decoded['message'] ?? $decoded['data']['message'] ?? ($success ? 'PayChangu checkout ready.' : 'PayChangu could not start the payment.'));

    return [
        'success' => $success,
        'checkout_url' => $checkoutUrl,
        'reference' => $gatewayRef,
        'message' => $message,
        'response' => $decoded,
    ];
}

// ── Handle payment initiation ─────────────────────────────────────────────────
$errors  = [];
$success = '';
$redirectUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $errors[] = 'Security token mismatch. Please refresh and try again.';
    } else {
        $gateway = $_POST['gateway'] ?? '';
        $phone   = preg_replace('/[^0-9+]/', '', trim($_POST['phone'] ?? ''));
        $terms   = $_POST['terms'] ?? '';

        if (empty($gateway) || !array_key_exists($gateway, $gateways)) $errors[] = 'Please select a payment method.';
        if (empty($terms)) $errors[] = 'You must accept the terms to proceed.';
        if ($amount <= 0)  $errors[] = 'Invalid payment amount.';

        if (in_array($gateway, ['airtel', 'tnm']) && empty($phone)) {
            $errors[] = 'Phone number is required for mobile money.';
        }

        if (empty($errors)) {
            // Create a transaction record using the production schema.
            $txnRef = generateId('TXN');
            $txnMeta = [
                'source' => 'checkout',
                'booking_id' => $bookingId ?: null,
                'booking_code' => $bookingCode ?: null,
                'booking_type' => $bookingType ?: null,
                'gateway' => $gateway,
                'phone' => $phone ?: null,
            ];
            dbExecute(
                "INSERT INTO transactions (transaction_reference, booking_id, user_id, vendor_id, amount, currency, gateway_name, transaction_type, status, metadata, created_at, updated_at)
                 VALUES (?, ?, ?, NULL, ?, ?, ?, 'booking_payment', 'pending', ?, NOW(), NOW())",
                [
                    $txnRef,
                    $bookingId ?: null,
                    $userId,
                    $amount,
                    APP_CURRENCY,
                    $gateways[$gateway]['label'] ?? $gateway,
                    json_encode($txnMeta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]
            );
            uthenga_record_transaction_analytics([
                'transaction_reference' => $txnRef,
                'booking_id' => $bookingId ?: null,
                'user_id' => $userId,
                'gateway_name' => $gateways[$gateway]['label'] ?? $gateway,
                'status' => 'pending',
                'amount' => $amount,
                'created_at' => date('Y-m-d H:i:s'),
            ], 'created');

            // Route to gateway
            switch ($gateway) {
                case 'paychangu':
                    $txnRow = [
                        'transaction_reference' => $txnRef,
                        'booking_id' => $bookingId ?: null,
                        'user_id' => $userId,
                        'amount' => $amount,
                        'currency' => APP_CURRENCY,
                        'metadata' => $txnMeta,
                    ];
                    $initResult = uthenga_paychangu_initialize($txnRow, $booking ?: [], $userEmail, $userName, $phone);
                    if ($initResult['success']) {
                        $txnMeta['gateway_reference'] = $initResult['reference'] ?? $txnRef;
                        $txnMeta['gateway_response'] = $initResult['response'] ?? [];
                        uthenga_checkout_update_transaction($txnRef, [
                            'metadata' => json_encode($txnMeta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        ]);
                        $redirectUrl = $initResult['checkout_url'] ?: (PAYCHANGU_RETURN_URL . '?txn=' . urlencode($txnRef));
                    } else {
                        uthenga_checkout_update_transaction($txnRef, [
                            'status' => 'failed',
                            'metadata' => json_encode(array_merge($txnMeta, [
                                'gateway_error' => $initResult['message'] ?? 'PayChangu initialization failed.',
                            ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        ]);
                        uthenga_record_transaction_analytics([
                            'transaction_reference' => $txnRef,
                            'booking_id' => $bookingId ?: null,
                            'user_id' => $userId,
                            'gateway_name' => $gateways[$gateway]['label'] ?? $gateway,
                            'status' => 'failed',
                            'amount' => $amount,
                            'created_at' => date('Y-m-d H:i:s'),
                        ], 'failed');
                        $errors[] = $initResult['message'] ?? 'PayChangu payment could not be started.';
                    }
                    break;
                case 'airtel':
                    $redirectUrl = BASE_URL . 'payments/airtel-callback.php?demo=1&txn=' . urlencode($txnRef) . '&phone=' . urlencode($phone);
                    break;
                case 'tnm':
                    $redirectUrl = BASE_URL . 'payments/tnm-callback.php?demo=1&txn=' . urlencode($txnRef) . '&phone=' . urlencode($phone);
                    break;
                case 'card':
                    $redirectUrl = BASE_URL . 'payments/card-callback.php?demo=1&txn=' . urlencode($txnRef);
                    break;
            }

            if ($redirectUrl && empty($errors)) {
                redirect($redirectUrl);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <meta name="description" content="Secure checkout for your Uthenga booking — Airtel Money, TNM Mpamba, Visa, Mastercard.">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= APP_VERSION ?>">
  <style>
    .checkout-grid { display: grid; grid-template-columns: 1fr 340px; gap: 2rem; align-items: start; }
    @media(max-width:880px){ .checkout-grid { grid-template-columns: 1fr; } }
    .checkout-card { background: var(--clr-surface); border: 1px solid var(--clr-border); border-radius: var(--radius-lg); padding: 2rem; }
    .gw-option { display: flex; gap: 1rem; align-items: flex-start; padding: 1rem; border: 2px solid var(--clr-border);
                  border-radius: var(--radius-md); cursor: pointer; transition: all .2s; margin-bottom: .75rem; }
    .gw-option:hover { border-color: var(--clr-cyan, #38bdf8); }
    .gw-option.selected { border-color: var(--clr-cyan, #38bdf8); background: rgba(56,189,248,.07); }
    .gw-option.disabled { opacity: .45; cursor: not-allowed; }
    .gw-icon {
      width: 2.2rem;
      height: 2.2rem;
      min-width: 2.2rem;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      font-size: 0.72rem;
      font-weight: 800;
      letter-spacing: 0.08em;
      color: var(--clr-text);
      background: var(--clr-surface2);
      border: 1px solid var(--clr-border);
    }
    .gw-label { font-weight: 700; font-size: .95rem; }
    .gw-desc  { font-size: .78rem; color: var(--clr-text-soft); margin-top: .15rem; }
    .gw-coming{ font-size: .68rem; font-weight: 700; padding: .15rem .5rem; border-radius: 100px;
                background: rgba(245,158,11,.15); color: #f59e0b; margin-left: .5rem; }
    .order-row { display: flex; justify-content: space-between; font-size: .88rem; margin-bottom: .6rem; }
    .order-total { display: flex; justify-content: space-between; font-size: 1.1rem; font-weight: 800;
                    margin-top: 1rem; padding-top: 1rem; border-top: 2px solid var(--clr-border); }
    .secure-badges { display: flex; gap: .75rem; flex-wrap: wrap; margin-top: 1.25rem; }
    .secure-badge { font-size: .72rem; color: var(--clr-text-soft); display: flex; align-items: center; gap: .3rem; }
    .phone-field { display: none; margin-top: .75rem; }
    .phone-field.visible { display: block; }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<main class="container" style="padding-top:2rem; padding-bottom:5rem;">
  <div style="margin-bottom:2rem;">
    <h1 style="font-size:1.8rem; font-weight:800; margin-bottom:.25rem;">Secure Checkout</h1>
    <p class="text-muted">Complete your booking payment safely.</p>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger" style="margin-bottom:1.5rem;">
      <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!$anyGatewayEnabled): ?>
    <div class="alert" style="background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);color:#f59e0b;padding:1.25rem;border-radius:var(--radius-md);margin-bottom:1.5rem;">
      <strong>Payment gateways not yet configured.</strong>
      To enable payments, add your gateway API keys to the <code>.env</code> file:<br>
      <code>PAYCHANGU_SECRET_KEY=...</code>, <code>AIRTEL_API_KEY=...</code>, <code>TNM_API_KEY=...</code>
    </div>
  <?php endif; ?>

  <form method="POST" id="checkout-form">
    <?= csrfField() ?>
    <input type="hidden" name="booking_id" value="<?= e($bookingId) ?>">
    <input type="hidden" name="amount" value="<?= e($amount) ?>">

    <div class="checkout-grid">
      <!-- Payment method selection -->
      <div class="checkout-card">
        <h2 style="font-size:1.1rem; font-weight:700; margin-bottom:1.5rem;">Select Payment Method</h2>

        <?php foreach ($gateways as $key => $gw): ?>
        <label class="gw-option <?= !$gw['enabled'] ? 'disabled' : '' ?>"
               id="gw-label-<?= $key ?>" for="gw-<?= $key ?>">
          <input type="radio" name="gateway" id="gw-<?= $key ?>" value="<?= $key ?>"
                 <?= !$gw['enabled'] ? 'disabled' : '' ?>
                 onchange="handleGatewayChange('<?= $key ?>')"
                 style="margin-top:.2rem;">
          <span class="gw-icon" aria-hidden="true"><?= e($gw['short']) ?></span>
          <div>
            <div class="gw-label">
              <?= e($gw['label']) ?>
              <?php if (!$gw['enabled']): ?>
                <span class="gw-coming">Coming Soon</span>
              <?php endif; ?>
            </div>
            <div class="gw-desc"><?= e($gw['desc']) ?></div>
          </div>
        </label>
        <?php endforeach; ?>

        <!-- Mobile money phone number -->
        <div class="phone-field" id="mobile-phone-field">
          <label class="form-label">Mobile Money Phone Number</label>
          <input type="tel" name="phone" id="phone-input" class="form-control"
                 placeholder="+265 99 999 9999" autocomplete="tel">
          <div style="font-size:.75rem;color:var(--clr-text-soft);margin-top:.3rem;">
            You will receive a USSD push to approve the payment.
          </div>
        </div>

        <div style="margin-top:1.5rem; border-top:1px solid var(--clr-border); padding-top:1.25rem;">
          <label style="display:flex; gap:.65rem; align-items:flex-start; cursor:pointer; font-size:.84rem;">
            <input type="checkbox" name="terms" value="1" style="margin-top:.2rem;" required>
            <span>I agree to the <a href="<?= BASE_URL ?>terms.php" target="_blank" style="color:var(--clr-cyan);">Terms of Service</a>
            and <a href="<?= BASE_URL ?>privacy.php" target="_blank" style="color:var(--clr-cyan);">Privacy Policy</a>. Payments are processed securely.</span>
          </label>
        </div>

        <button type="submit" id="pay-btn" class="btn btn-cyan btn-lg" style="width:100%;margin-top:1.25rem;font-size:1.05rem;">
          Pay MK <?= number_format($amount) ?>
        </button>

        <div class="secure-badges">
          <span class="secure-badge">SSL Encrypted</span>
          <span class="secure-badge">PCI Compliant</span>
          <span class="secure-badge">Refunds Available</span>
        </div>
      </div>

      <!-- Order summary -->
      <div>
        <div class="checkout-card">
          <h3 style="font-size:1rem;font-weight:700;margin-bottom:1rem;">Order Summary</h3>
          <div class="order-row">
            <span class="text-muted">Item</span>
            <strong style="text-align:right;max-width:180px;"><?= e($description ?: 'Booking') ?></strong>
          </div>
          <?php if ($booking): ?>
          <div class="order-row">
            <span class="text-muted">Booking Ref</span>
            <span style="font-family:monospace;font-size:.8rem;"><?= e($bookingId) ?></span>
          </div>
          <div class="order-row">
            <span class="text-muted">Type</span>
            <span style="text-transform:capitalize;"><?= e($bookingType) ?></span>
          </div>
          <?php endif; ?>
          <div class="order-row">
            <span class="text-muted">Customer</span>
            <span><?= e($userName) ?></span>
          </div>
          <div class="order-total">
            <span>Total Due</span>
            <strong style="color:var(--clr-cyan,#38bdf8);">MK <?= number_format($amount) ?></strong>
          </div>
        </div>

        <div class="checkout-card" style="margin-top:1.25rem;">
          <h3 style="font-size:.9rem;font-weight:700;margin-bottom:.75rem;">📞 Need Help?</h3>
          <p class="text-muted" style="font-size:.8rem;margin-bottom:.75rem;">
            Having trouble with payment? Contact our support team.
          </p>
          <a href="<?= BASE_URL ?>support.php" class="btn btn-secondary" style="width:100%;text-align:center;display:block;font-size:.85rem;">
            Open Support Ticket
          </a>
        </div>
      </div>
    </div>
  </form>
</main>

<script>
function handleGatewayChange(key) {
  // Highlight selected card
  document.querySelectorAll('.gw-option').forEach(el => el.classList.remove('selected'));
  const label = document.getElementById('gw-label-' + key);
  if (label) label.classList.add('selected');

  // Show phone field for mobile money
  const phoneField = document.getElementById('mobile-phone-field');
  const phoneInput = document.getElementById('phone-input');
  if (['airtel', 'tnm'].includes(key)) {
    phoneField.classList.add('visible');
    phoneInput.required = true;
  } else {
    phoneField.classList.remove('visible');
    phoneInput.required = false;
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
