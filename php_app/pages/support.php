<?php
/**
 * Uthenga - Customer Support Ticket Center
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../admin/includes/admin_icons.php';

requireCustomer();

$pageTitle = 'Support Tickets';
$activeNav = 'support';
$activeTab = $_GET['tab'] ?? 'tickets';

$userId = $_SESSION['user_id'];
$user = dbQueryOne('SELECT * FROM users WHERE id = ?', [$userId]);
$userName = $user['name'] ?? ($_SESSION['user_name'] ?? 'Customer');
$hasSupportTickets = uthenga_table_exists('support_tickets');
$hasTicketResponses = uthenga_table_exists('ticket_responses');

$ticketSuccess = '';
$ticketError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    if (!validateCsrf()) {
        $ticketError = 'Security validation failed. Please reload.';
    } elseif (!$hasSupportTickets) {
        $ticketError = 'Support ticket storage is not available yet. Please contact support directly.';
    } else {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['ticket_message'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $validCats = ['Billing', 'Booking issue', 'Vendor help', 'Technical'];

        if ($subject === '' || $message === '' || !in_array($category, $validCats, true)) {
            $ticketError = 'All fields are required. Please select a valid category.';
        } else {
            $ticketId = 'TCK-' . rand(1000, 9999);
            dbExecute(
                'INSERT INTO support_tickets (id, customer_id, customer_name, subject, message, category) VALUES (?,?,?,?,?,?)',
                [$ticketId, $userId, $userName, $subject, $message, $category]
            );
            logAction('Created Support Ticket', "Customer submitted ticket: \"$subject\" (Category: $category)");
            $ticketSuccess = 'Support ticket #' . $ticketId . ' submitted. Our team will respond shortly.';
            $activeTab = 'tickets';
        }
    }
}

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

function statusClass(string $status): string
{
    return match (strtolower($status)) {
        'resolved' => 'status-confirmed',
        'open', 'in progress' => 'status-pending',
        default => 'status-pending',
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
  <style>
    .support-shell { padding: 2.5rem 0 4rem; }
    .support-hero {
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:1rem;
      flex-wrap:wrap;
      margin-bottom:1.5rem;
    }
    .support-tabs {
      display:flex;
      gap:.75rem;
      flex-wrap:wrap;
      margin-bottom:1.5rem;
    }
    .support-card { padding:1.25rem; }
    .support-card + .support-card { margin-top:1rem; }
    .support-thread { display:grid; gap:.85rem; }
    .support-empty {
      padding:3rem 1.5rem;
      text-align:center;
    }
    .support-note {
      padding:1rem;
      border:1px solid var(--clr-border);
      background:var(--clr-surface2);
      border-radius:14px;
    }
    @media (max-width: 640px) {
      .support-shell { padding-top: 2rem; }
      .support-card { padding:1rem; }
      .support-tabs .filter-tab,
      .support-tabs .btn { width:100%; justify-content:center; }
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="container support-shell">
  <div class="support-hero">
    <div>
      <h1 class="page-title"><?= admin_icon_svg('support') ?> <span>Support Tickets</span></h1>
      <p class="text-muted">Need assistance? Check ticket replies or open a new support request.</p>
    </div>
  </div>

  <div class="glass-panel support-note" style="margin-bottom:1.5rem;">
    <div style="display:flex;gap:1rem;flex-wrap:wrap;justify-content:space-between;align-items:center;">
      <div>
        <strong>Need direct help?</strong>
        <div class="text-sm text-muted">Email <a href="mailto:<?= e(SUPPORT_CONTACT['email']) ?>"><?= e(SUPPORT_CONTACT['email']) ?></a> or call <a href="tel:<?= e(SUPPORT_CONTACT['phone']) ?>"><?= e(SUPPORT_CONTACT['phone']) ?></a>.</div>
      </div>
      <a href="<?= BASE_URL ?>contact.php" class="btn btn-secondary btn-sm">Contact Support</a>
    </div>
  </div>

  <div class="support-tabs">
    <a href="?tab=tickets" class="filter-tab <?= $activeTab === 'tickets' ? 'active' : '' ?>">My Tickets (<?= count($tickets) ?>)</a>
    <a href="?tab=new" class="filter-tab <?= $activeTab === 'new' ? 'active' : '' ?>">Open New Ticket</a>
  </div>

  <?php if ($activeTab === 'tickets'): ?>
    <?php if (empty($tickets)): ?>
      <div class="glass-panel support-empty">
        <div style="font-size:2.5rem;margin-bottom:1rem;"><?= admin_icon_svg('help') ?></div>
        <h3>No support tickets</h3>
        <p class="text-muted" style="margin-top:.5rem;">If you have questions or encounter issues, open a ticket.</p>
        <a href="?tab=new" class="btn btn-primary" style="margin-top:1rem;">Open Ticket</a>
      </div>
    <?php else: ?>
      <div style="display:grid;gap:1rem;">
        <?php foreach ($tickets as $ticket): ?>
          <div class="glass-panel support-card" id="ticket-<?= e($ticket['id']) ?>">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.75rem;flex-wrap:wrap;margin-bottom:0.85rem;">
              <div>
                <span class="text-xs text-muted font-mono">#<?= e($ticket['id']) ?></span>
                <h3 style="margin-top:0.25rem;font-size:1.1rem;font-weight:800;"><?= e($ticket['subject']) ?></h3>
                <div class="text-xs text-muted" style="margin-top:0.2rem;display:flex;gap:.6rem;flex-wrap:wrap;">
                  <span>Category: <?= e($ticket['category']) ?></span>
                  <span><?= e(substr($ticket['created_at'], 0, 16)) ?></span>
                </div>
              </div>
              <span class="status-badge <?= statusClass($ticket['status']) ?>"><?= e($ticket['status']) ?></span>
            </div>

            <p class="text-sm" style="color:var(--clr-text-soft);line-height:1.6;margin-bottom:1rem;"><?= nl2br(e($ticket['message'])) ?></p>

            <?php if (!empty($ticket['last_reply'])): ?>
              <div style="background:rgba(255,255,255,0.02);border-left:3px solid var(--clr-accent);border-radius:var(--radius-sm);padding:1rem;margin-top:0.5rem;">
                <div class="text-xs text-muted" style="margin-bottom:0.25rem;font-weight:600;">
                  Reply from <?= e($ticket['reply_from'] ?? 'Support Representative') ?> - <?= e($ticket['reply_time']) ?>
                </div>
                <p class="text-sm" style="margin:0;font-style:italic;"><?= e($ticket['last_reply']) ?></p>
              </div>
            <?php endif; ?>

            <div class="text-xs text-muted" style="margin-top:0.75rem;">Submitted on: <?= e($ticket['created_at']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php elseif ($activeTab === 'new'): ?>
    <div class="glass-panel support-card" style="max-width:640px;">
      <h2 style="margin-bottom:1rem;font-size:1.35rem;font-weight:800;">Open a Support Ticket</h2>

      <?php if ($ticketError): ?><div class="alert alert-error">Error: <?= e($ticketError) ?></div><?php endif; ?>
      <?php if ($ticketSuccess): ?><div class="alert alert-success">Success: <?= e($ticketSuccess) ?></div><?php endif; ?>

      <form method="POST" action="?tab=new" id="ticket-form">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="submit_ticket" value="1">

        <div class="form-group">
          <label class="form-label" for="ticket-category">Category</label>
          <select id="ticket-category" name="category" class="form-control" required>
            <option value="">Select ticket category...</option>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
