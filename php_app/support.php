<?php
/**
 * Uthenga â€” Customer Support Ticket Center
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/auth_check.php';

requireCustomer();

$pageTitle = 'Support Tickets';
$activeNav = 'support';
$activeTab = $_GET['tab'] ?? 'tickets';

$userId = $_SESSION['user_id'];
$user   = dbQueryOne('SELECT * FROM users WHERE id = ?', [$userId]);
$userName = $user['name'] ?? ($_SESSION['user_name'] ?? 'Customer');
$hasSupportTickets = uthenga_table_exists('support_tickets');
$hasTicketResponses = uthenga_table_exists('ticket_responses');

// â”€â”€â”€ Handle Ticket Submission â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$ticketSuccess = '';
$ticketError   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    if (!validateCsrf()) {
        $ticketError = 'Security validation failed. Please reload.';
    } elseif (!$hasSupportTickets) {
        $ticketError = 'Support ticket storage is not available yet. Please contact support directly.';
    } else {
        $subject  = trim($_POST['subject']  ?? '');
        $message  = trim($_POST['ticket_message'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $validCats = ['Billing','Booking issue','Vendor help','Technical'];
        if (empty($subject) || empty($message) || !in_array($category, $validCats)) {
            $ticketError = 'All fields are required. Please select a valid category.';
        } else {
            $ticketId = 'TCK-' . rand(1000, 9999);
            dbExecute(
                'INSERT INTO support_tickets (id, customer_id, customer_name, subject, message, category) VALUES (?,?,?,?,?,?)',
                [$ticketId, $userId, $userName, $subject, $message, $category]
            );
            logAction('Created Support Ticket', "Customer submitted ticket: \"$subject\" (Category: $category)");
            $ticketSuccess = 'Support ticket #' . $ticketId . ' submitted! Our team will respond shortly.';
            $activeTab = 'tickets';
        }
    }
}

// â”€â”€â”€ Fetch Support Tickets â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$tickets = [];
if ($hasSupportTickets) {
    if ($hasTicketResponses) {
        $tickets = dbQuery(
            'SELECT st.*, tr.message AS last_reply, tr.sender AS reply_from, tr.created_at AS reply_time
             FROM support_tickets st
             LEFT JOIN (SELECT ticket_id, message, sender, created_at FROM ticket_responses ORDER BY created_at DESC) tr ON tr.ticket_id = st.id
             WHERE st.customer_id = ?
             GROUP BY st.id
             ORDER BY st.created_at DESC',
            [$userId]
        ) ?: [];
    } else {
        $tickets = dbQuery(
            'SELECT st.*, NULL AS last_reply, NULL AS reply_from, NULL AS reply_time
             FROM support_tickets st
             WHERE st.customer_id = ?
             ORDER BY st.created_at DESC',
            [$userId]
        ) ?: [];
    }
}

function statusClass(string $status): string {
    return match(strtolower($status)) {
        'resolved'    => 'status-confirmed',
        'open', 'in progress' => 'status-pending',
        default       => 'status-pending'
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <meta name="csrf-token" content="<?= e($_SESSION['csrf_token'] ?? '') ?>">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin.css">
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container" style="padding-top:2.5rem;padding-bottom:4rem;">
  
  <!-- Page Header -->
  <div class="page-header">
    <div>
      <h1 class="page-title">ðŸ’¬ Support Tickets</h1>
      <p class="text-muted">Need assistance? Check ticket replies or open a new support request.</p>
    </div>
  </div>

  <div class="glass-panel" style="padding:1rem 1.25rem;margin-bottom:1.5rem;">
    <div style="display:flex;gap:1rem;flex-wrap:wrap;justify-content:space-between;align-items:center;">
      <div>
        <strong>Need direct help?</strong>
        <div class="text-sm text-muted">Email <a href="mailto:<?= e(SUPPORT_CONTACT['email']) ?>"><?= e(SUPPORT_CONTACT['email']) ?></a> or call <a href="tel:<?= e(SUPPORT_CONTACT['phone']) ?>"><?= e(SUPPORT_CONTACT['phone']) ?></a>.</div>
      </div>
      <a href="<?= BASE_URL ?>contact.php" class="btn btn-secondary btn-sm">Contact Support</a>
    </div>
  </div>

  <!-- Support Tabs -->
  <div class="filter-tabs" style="margin-bottom:2rem;">
    <a href="?tab=tickets" class="filter-tab <?= $activeTab === 'tickets' ? 'active' : '' ?>">
      ðŸ’¬ My Tickets (<?= count($tickets) ?>)
    </a>
    <a href="?tab=new" class="filter-tab <?= $activeTab === 'new' ? 'active' : '' ?>">
      + Open New Ticket
    </a>
  </div>

  <!-- Tickets List -->
  <?php if ($activeTab === 'tickets'): ?>
    <?php if (empty($tickets)): ?>
      <div class="glass-panel text-center" style="padding:4rem 2rem; text-align: center;">
        <div style="font-size:3rem;margin-bottom:1rem;">ðŸ’¬</div>
        <h3>No support tickets</h3>
        <p class="text-muted" style="margin-top:0.5rem;">If you have questions or encounter issues, open a ticket.</p>
        <a href="?tab=new" class="btn btn-primary" style="margin-top:1rem;">Open Ticket</a>
      </div>
    <?php else: ?>
      <div style="display:grid;gap:1.25rem;">
        <?php foreach ($tickets as $t): ?>
        <div class="card animate-in" style="padding:1.5rem;" id="ticket-<?= e($t['id']) ?>">
          <div class="flex items-center justify-between" style="margin-bottom:0.75rem;flex-wrap:wrap;gap:0.5rem;">
            <div>
              <span class="text-xs text-muted font-mono">#<?= e($t['id']) ?></span>
              <h3 style="margin-top:0.25rem; font-size:1.15rem; font-weight:700;"><?= e($t['subject']) ?></h3>
            </div>
            <div style="display:flex;gap:0.5rem;align-items:center;">
              <span class="badge badge-pending" style="font-size:0.7rem;"><?= e($t['category']) ?></span>
              <span class="status-badge <?= statusClass($t['status']) ?>"><?= e($t['status']) ?></span>
            </div>
          </div>
          <p class="text-sm" style="color:var(--clr-text-soft); line-height:1.6; margin-bottom:1rem;"><?= nl2br(e($t['message'])) ?></p>
          
          <?php if (!empty($t['last_reply'])): ?>
            <div style="background:rgba(255,255,255,0.02); border-left:3px solid var(--clr-accent); border-radius:var(--radius-sm); padding:1rem; margin-top:0.5rem;">
              <div class="text-xs text-muted" style="margin-bottom:0.25rem; font-weight:600;">
                Reply from <?= e($t['reply_from'] ?? 'Support Representative') ?> Â· <?= e($t['reply_time']) ?>
              </div>
              <p class="text-sm" style="margin:0; font-style:italic;"><?= e($t['last_reply']) ?></p>
            </div>
          <?php endif; ?>
          <div class="text-xs text-muted" style="margin-top:0.75rem;">Submitted on: <?= e($t['created_at']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <!-- Open Ticket Form -->
  <?php elseif ($activeTab === 'new'): ?>
    <div class="glass-panel" style="max-width:600px; padding:2rem;">
      <h2 style="margin-bottom:1.5rem; font-size:1.4rem;">Open a Support Ticket</h2>

      <?php if ($ticketError): ?>
        <div class="alert alert-error">âœ• <?= e($ticketError) ?></div>
      <?php endif; ?>
      <?php if ($ticketSuccess): ?>
        <div class="alert alert-success">âœ“ <?= e($ticketSuccess) ?></div>
      <?php endif; ?>

      <form method="POST" action="?tab=new" id="ticket-form">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="submit_ticket" value="1">

        <div class="form-group">
          <label class="form-label" for="ticket-category">Category</label>
          <select id="ticket-category" name="category" class="form-control" required>
            <option value="">Select ticket categoryâ€¦</option>
            <option value="Billing">Billing & Invoice</option>
            <option value="Booking issue">Booking Issue</option>
            <option value="Vendor help">Vendor Help</option>
            <option value="Technical">Technical Glitch</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="ticket-subject">Subject</label>
          <input type="text" id="ticket-subject" name="subject" class="form-control" placeholder="Short description of your request" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="ticket-msg">Message Detail</label>
          <textarea id="ticket-msg" name="ticket_message" class="form-control" rows="6" placeholder="Describe the issue in detail, including booking IDs or listing names..." required style="resize:vertical;"></textarea>
        </div>

        <button type="submit" id="submit-ticket-btn" class="btn btn-primary btn-lg" style="width:100%;">Submit Support Ticket</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>

