<?php
/**
 * Uthenga - Admin Marketing Dashboard
 */
$pageTitle = 'Marketing Dashboard';
$activeNav = 'admin-promotions';

require_once __DIR__ . '/includes/admin_header.php';

$userId = $_SESSION['user_id'] ?? null;
$success = '';
$error = '';

$hasNewsletterSubscribers = uthenga_table_exists('newsletter_subscribers');
$hasNewsletterCampaigns = uthenga_table_exists('newsletter_campaigns');
$hasReferralCodes = uthenga_table_exists('referral_codes');
$hasReferralUses = uthenga_table_exists('referral_uses');
$hasGiftVouchers = uthenga_table_exists('gift_vouchers');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_campaign'])) {
    if (!validateCsrf()) {
        $error = 'Security check failed. Please refresh.';
    } elseif (!$hasNewsletterSubscribers || !$hasNewsletterCampaigns) {
        $error = 'Marketing tables are missing. Please run the database migrations first.';
    } else {
        $subject = trim($_POST['subject'] ?? '');
        $audience = $_POST['audience'] ?? 'all';
        $bodyText = trim($_POST['body_text'] ?? '');
        $bodyHtml = trim($_POST['body_html'] ?? '');

        if ($subject === '' || $bodyText === '') {
            $error = 'Subject and plain text body are required.';
        } else {
            $where = "status = 'subscribed'";
            $params = [];
            if ($audience !== 'all') {
                $where .= " AND JSON_CONTAINS(preferences, ?)";
                $params[] = json_encode($audience);
            }

            $subCount = dbCount("SELECT COUNT(*) FROM newsletter_subscribers WHERE $where", $params);

            dbExecute(
                "INSERT INTO newsletter_campaigns
                 (subject, body_html, body_text, audience, status, sent_count, sent_at, created_by, created_at)
                 VALUES (?, ?, ?, ?, 'sent', ?, NOW(), ?, NOW())",
                [$subject, $bodyHtml, $bodyText, $audience, $subCount, $userId]
            );

            logAction('Newsletter Campaign Sent', "Admin sent campaign: '$subject' to $subCount subscribers.");
            $success = "Campaign '$subject' sent successfully to $subCount subscribers!";
        }
    }
}

$subscribersCount = $hasNewsletterSubscribers ? dbCount("SELECT COUNT(*) FROM newsletter_subscribers WHERE status = 'subscribed'") : 0;
$referralCodesCount = $hasReferralCodes ? dbCount("SELECT COUNT(*) FROM referral_codes WHERE is_active = 1") : 0;
$referralUsesCount = $hasReferralUses ? dbCount("SELECT COUNT(*) FROM referral_uses") : 0;
$activeVouchersCount = $hasGiftVouchers ? dbCount("SELECT COUNT(*) FROM gift_vouchers WHERE status = 'active'") : 0;

$campaigns = $hasNewsletterCampaigns ? dbQuery(
    "SELECT c.*, u.name AS creator_name
     FROM newsletter_campaigns c
     LEFT JOIN users u ON u.id = c.created_by
     ORDER BY c.created_at DESC
     LIMIT 10"
) : [];

$vouchers = $hasGiftVouchers ? dbQuery(
    "SELECT * FROM gift_vouchers
     ORDER BY created_at DESC
     LIMIT 10"
) : [];

$referralLeaders = $hasReferralCodes ? dbQuery(
    "SELECT u.name, u.email, rc.code, rc.uses_count
     FROM referral_codes rc
     JOIN users u ON u.id = rc.user_id
     ORDER BY rc.uses_count DESC
     LIMIT 5"
) : [];

// ── Ads section ──────────────────────────────────────────────────────────────
$hasAds = uthenga_table_exists('advertisements');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ad'])) {
    if (!validateCsrf()) {
        $error = 'Security check failed. Please refresh.';
    } elseif (!$hasAds) {
        $error = 'Advertisements table is not installed. Run migrations first.';
    } else {
        $adTitle     = trim($_POST['ad_title'] ?? '');
        $adCategory  = trim($_POST['ad_category'] ?? 'Restaurant');
        $adImageUrl  = trim($_POST['ad_image_url'] ?? '');
        $adLinkUrl   = trim($_POST['ad_link_url'] ?? '');
        $adStartDate = trim($_POST['ad_start_date'] ?? date('Y-m-d'));
        $adEndDate   = trim($_POST['ad_end_date'] ?? date('Y-m-d', strtotime('+30 days')));

        if ($adTitle === '') {
            $error = 'Ad title is required.';
        } else {
            dbExecute(
                "INSERT INTO advertisements (title, ad_type, image_url, link_url, status, start_date, end_date, created_at)
                 VALUES (?, ?, ?, ?, 'active', ?, ?, NOW())",
                [$adTitle, $adCategory, $adImageUrl ?: null, $adLinkUrl ?: null, $adStartDate, $adEndDate]
            );
            logAction('Ad Created', "Marketing ad '$adTitle' ($adCategory) created.");
            $success = "Ad \"$adTitle\" published successfully!";
        }
    }
}

$marketingAds = $hasAds ? dbQuery(
    "SELECT * FROM advertisements
     WHERE ad_type NOT IN ('banner','popup','system')
     ORDER BY created_at DESC LIMIT 20"
) : [];
$marketingAdsCount = $hasAds ? dbCount(
    "SELECT COUNT(*) FROM advertisements WHERE ad_type NOT IN ('banner','popup','system') AND status = 'active'"
) : 0;
?>
<div class="page-header">
  <div>
    <h1 class="page-title">Marketing Command Center</h1>
    <p class="text-muted">Manage newsletter campaigns, track referral codes, and audit active gift vouchers.</p>
  </div>
</div>

<?php if ($success): ?><div class="alert alert-success" style="margin-bottom:1.5rem;">✓ <?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error" style="margin-bottom:1.5rem;">✗ <?= e($error) ?></div><?php endif; ?>

<div class="grid grid-cols-4 gap-2" style="margin-bottom:1.5rem;">
  <div class="stat-card">
    <div class="stat-icon stat-icon-blue"><?= admin_icon_svg('notification') ?></div>
    <div><div class="stat-value"><?= number_format($subscribersCount) ?></div><div class="stat-label">Newsletter Subscribers</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-icon-cyan"><?= admin_icon_svg('users') ?></div>
    <div><div class="stat-value"><?= number_format($referralUsesCount) ?></div><div class="stat-label">Successful Referrals</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-icon-purple"><?= admin_icon_svg('credit-card') ?></div>
    <div><div class="stat-value"><?= number_format($activeVouchersCount) ?></div><div class="stat-label">Active Vouchers</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-icon-green"><?= admin_icon_svg('shield') ?></div>
    <div><div class="stat-value"><?= number_format($referralCodesCount) ?></div><div class="stat-label">Registered Referrers</div></div>
  </div>
</div>

<div class="grid grid-cols-3 gap-3" style="margin-bottom:1.5rem;">
  <section class="glass-panel" style="grid-column: span 2;">
    <div class="section-head">
      <div>
        <h3>Create and Send Newsletter Campaign</h3>
        <p class="text-xs text-muted">Send customized email campaigns to targeted subscriber segments.</p>
      </div>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="send_campaign" value="1">

      <div class="form-group">
        <label class="form-label" for="subject">Campaign Subject Line *</label>
        <input type="text" id="subject" name="subject" class="form-control" placeholder="e.g. Special Holiday Offers in Lake Malawi!" required>
      </div>

      <div class="form-group">
        <label class="form-label" for="audience">Audience Preference Target</label>
        <select name="audience" id="audience" class="form-control">
          <option value="all">All Subscribers (General News)</option>
          <option value="events">Events Enthusiasts (events)</option>
          <option value="travel">Travelers and Stays (travel)</option>
          <option value="transport">Transport Users (transport)</option>
          <option value="deals">Bargain Hunters (deals)</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label" for="body_text">Plain Text Email Body *</label>
        <textarea id="body_text" name="body_text" class="form-control" rows="4" placeholder="Plain text version for compatibility..." required></textarea>
      </div>

      <div class="form-group">
        <label class="form-label" for="body_html">Rich HTML Email Body (Optional)</label>
        <textarea id="body_html" name="body_html" class="form-control" rows="4" placeholder="<p>HTML version here...</p>"></textarea>
      </div>

      <button type="submit" class="btn btn-primary" <?= (!$hasNewsletterSubscribers || !$hasNewsletterCampaigns) ? 'disabled' : '' ?>>Send Email Dispatch</button>
    </form>
    <?php if (!$hasNewsletterSubscribers || !$hasNewsletterCampaigns): ?>
      <p class="text-xs text-muted" style="margin-top:0.75rem;">Newsletter tables are unavailable, so campaign sending is disabled until migrations are applied.</p>
    <?php endif; ?>
  </section>

  <section class="glass-panel">
    <div class="section-head">
      <div>
        <h3>Top Referrers</h3>
        <p class="text-xs text-muted">Users driving the most registrations.</p>
      </div>
    </div>
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th>User</th>
            <th>Referrals</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($referralLeaders)): ?>
            <tr><td colspan="2" class="text-muted text-xs">No referrals logged.</td></tr>
          <?php else: ?>
            <?php foreach ($referralLeaders as $leader): ?>
              <tr>
                <td>
                  <strong><?= e($leader['name']) ?></strong>
                  <div class="text-xs text-muted">Code: <?= e($leader['code']) ?></div>
                </td>
                <td><strong><?= number_format((int) $leader['uses_count']) ?></strong></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<div class="grid grid-cols-2 gap-3">
  <section class="glass-panel">
    <div class="section-head">
      <div>
        <h3>Campaign History</h3>
        <p class="text-xs text-muted">Recently dispatched newsletters.</p>
      </div>
    </div>
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Subject</th>
            <th>Audience</th>
            <th>Dispatched</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($campaigns)): ?>
            <tr><td colspan="4" class="text-muted text-xs">No campaigns dispatched yet.</td></tr>
          <?php else: ?>
            <?php foreach ($campaigns as $c): ?>
              <tr>
                <td class="text-xs text-muted"><?= e(date('M j, Y', strtotime($c['sent_at'] ?? $c['created_at']))) ?></td>
                <td><strong><?= e($c['subject']) ?></strong></td>
                <td><span class="badge badge-pending"><?= e(ucfirst($c['audience'])) ?></span></td>
                <td><strong><?= number_format((int) $c['sent_count']) ?></strong></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="glass-panel">
    <div class="section-head">
      <div>
        <h3>Active Gift Cards and Vouchers</h3>
        <p class="text-xs text-muted">Auditing issued vouchers and their balances.</p>
      </div>
    </div>
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Voucher Code</th>
            <th>Recipient</th>
            <th>Balance</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($vouchers)): ?>
            <tr><td colspan="4" class="text-muted text-xs">No vouchers issued yet.</td></tr>
          <?php else: ?>
            <?php foreach ($vouchers as $v): ?>
              <tr>
                <td class="font-mono text-xs"><strong><?= e($v['voucher_code']) ?></strong></td>
                <td class="text-xs text-muted"><?= e($v['recipient_email'] ?? '') ?></td>
                <td><strong><?= formatMWK((float) ($v['balance_mwk'] ?? 0)) ?></strong></td>
                <td><span class="badge badge-<?= ($v['status'] ?? '') === 'active' ? 'approved' : 'rejected' ?>"><?= e($v['status'] ?? '') ?></span></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<!-- ── Advertise Your Business Section ───────────────────────────────────── -->
<div style="margin-top:2rem;">
  <div class="section-head" style="margin-bottom:1.25rem;">
    <div>
      <h2 class="page-title" style="font-size:1.25rem;">📢 Advertise Your Business</h2>
      <p class="text-muted text-sm">Promote local businesses — restaurants, cafés, hotels, pharmacies, and more — on the Uthenga platform.</p>
    </div>
    <span class="badge badge-approved" style="align-self:flex-start;">
      <?= number_format($marketingAdsCount) ?> Live Ads
    </span>
  </div>

  <div class="grid grid-cols-3 gap-3">
    <!-- Create Ad Form -->
    <section class="glass-panel" style="grid-column:span 1;">
      <div class="section-head">
        <div><h3>Create New Ad</h3><p class="text-xs text-muted">Fill in the details to publish a business ad.</p></div>
      </div>
      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
        <input type="hidden" name="create_ad" value="1">

        <div class="form-group">
          <label class="form-label" for="ad_title">Business / Ad Title *</label>
          <input type="text" id="ad_title" name="ad_title" class="form-control"
                 placeholder="e.g. The Lotus Garden Restaurant" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="ad_category">Business Category</label>
          <select name="ad_category" id="ad_category" class="form-control">
            <option value="Restaurant">🍽️ Restaurant</option>
            <option value="Cafe">☕ Café &amp; Coffee Shop</option>
            <option value="Hotel">🏨 Hotel &amp; Lodging</option>
            <option value="Pharmacy">💊 Pharmacy</option>
            <option value="Supermarket">🛒 Supermarket &amp; Grocery</option>
            <option value="Salon">✂️ Salon &amp; Beauty</option>
            <option value="Gym">🏋️ Gym &amp; Fitness</option>
            <option value="Curio">🎁 Curio &amp; Gift Shop</option>
            <option value="Photography">📷 Photography</option>
            <option value="LocalBusiness">🏪 Other Local Business</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="ad_image_url">Banner Image URL</label>
          <input type="url" id="ad_image_url" name="ad_image_url" class="form-control"
                 placeholder="https://example.com/banner.jpg">
        </div>

        <div class="form-group">
          <label class="form-label" for="ad_link_url">Destination Link</label>
          <input type="url" id="ad_link_url" name="ad_link_url" class="form-control"
                 placeholder="https://yourbusiness.com or a listing URL">
        </div>

        <div class="grid grid-cols-2 gap-2">
          <div class="form-group">
            <label class="form-label" for="ad_start_date">Start Date</label>
            <input type="date" id="ad_start_date" name="ad_start_date" class="form-control"
                   value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="ad_end_date">End Date</label>
            <input type="date" id="ad_end_date" name="ad_end_date" class="form-control"
                   value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
          </div>
        </div>

        <button type="submit" class="btn btn-primary" <?= !$hasAds ? 'disabled' : '' ?>>Publish Ad</button>
        <?php if (!$hasAds): ?>
          <p class="text-xs text-muted" style="margin-top:.5rem;">Advertisements table unavailable — run migrations first.</p>
        <?php endif; ?>
      </form>
    </section>

    <!-- Live Ads Grid -->
    <section class="glass-panel" style="grid-column:span 2;">
      <div class="section-head">
        <div><h3>Live Business Ads</h3><p class="text-xs text-muted">Currently published marketing advertisements.</p></div>
      </div>
      <?php if (empty($marketingAds)): ?>
        <div style="padding:2.5rem;text-align:center;color:var(--clr-text-muted);">
          <div style="font-size:2.5rem;margin-bottom:.75rem;">📢</div>
          <p>No business ads published yet.<br>Create your first ad using the form on the left.</p>
        </div>
      <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;">
          <?php foreach ($marketingAds as $ad): ?>
            <?php
              $isActive = ($ad['status'] ?? '') === 'active';
              $isExpired = !empty($ad['end_date']) && strtotime($ad['end_date']) < time();
              $categoryIcons = [
                'Restaurant'=>'🍽️','Cafe'=>'☕','Hotel'=>'🏨','Pharmacy'=>'💊',
                'Supermarket'=>'🛒','Salon'=>'✂️','Gym'=>'🏋️','Curio'=>'🎁',
                'Photography'=>'📷','LocalBusiness'=>'🏪',
              ];
              $icon = $categoryIcons[$ad['ad_type']] ?? '📣';
            ?>
            <div style="
              border:1px solid var(--clr-border);border-radius:var(--radius-md);
              overflow:hidden;background:var(--clr-surface2);
              <?= ($isExpired || !$isActive) ? 'opacity:.6;' : '' ?>
            ">
              <?php if (!empty($ad['image_url'])): ?>
                <div style="height:110px;overflow:hidden;">
                  <img src="<?= e($ad['image_url']) ?>" alt="<?= e($ad['title']) ?>"
                       style="width:100%;height:100%;object-fit:cover;display:block;">
                </div>
              <?php else: ?>
                <div style="height:110px;display:flex;align-items:center;justify-content:center;
                            background:linear-gradient(135deg,var(--clr-surface),var(--clr-border));
                            font-size:2.5rem;"><?= $icon ?></div>
              <?php endif; ?>
              <div style="padding:.75rem;">
                <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;
                            letter-spacing:.06em;color:var(--clr-text-soft);margin-bottom:.3rem;">
                  <?= $icon ?> <?= e($ad['ad_type']) ?>
                </div>
                <div style="font-weight:700;font-size:.9rem;margin-bottom:.4rem;line-height:1.25;
                            white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= e($ad['title']) ?>">
                  <?= e($ad['title']) ?>
                </div>
                <div class="text-xs text-muted" style="margin-bottom:.5rem;">
                  <?= date('d M', strtotime($ad['start_date'])) ?> – <?= date('d M Y', strtotime($ad['end_date'])) ?>
                </div>
                <div style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap;">
                  <?php if ($isExpired): ?>
                    <span class="badge badge-cancelled">Expired</span>
                  <?php elseif ($isActive): ?>
                    <span class="badge badge-approved">Active</span>
                  <?php else: ?>
                    <span class="badge badge-pending">Inactive</span>
                  <?php endif; ?>
                  <?php if (!empty($ad['link_url'])): ?>
                    <a href="<?= e($ad['link_url']) ?>" target="_blank" rel="noopener"
                       style="font-size:.72rem;font-weight:600;color:var(--clr-primary);">Visit ↗</a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
