<?php
/**
 * Uthenga - Admin Support Tickets Management
 */
$pageTitle = 'Support Tickets';
$activeNav = 'admin-support';

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireAdmin();

$filterStatus = $_GET['status'] ?? 'all';
$search = trim($_GET['q'] ?? '');
$replySuccess = '';
$replyError = '';
$hasSupportTickets = uthenga_table_exists('support_tickets');
$hasTicketResponses = uthenga_table_exists('ticket_responses');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_ticket']) && validateCsrf()) {
    $ticketId = trim($_POST['ticket_id'] ?? '');
    $message = trim($_POST['reply_message'] ?? '');

    if ($message === '') {
        $replyError = 'Reply cannot be empty.';
    } elseif (!$hasSupportTickets || !$hasTicketResponses) {
        $replyError = 'Support tables are missing. Please run the database migrations first.';
    } else {
        $ticket = dbQueryOne('SELECT id, ticket_code, requester_name FROM support_tickets WHERE id = ?', [$ticketId]);
        if (!$ticket) {
            $replyError = 'Support ticket not found.';
        } else {
            dbExecute("INSERT INTO ticket_responses (ticket_id, sender, message) VALUES (?, 'System Administrator', ?)", [$ticketId, $message]);
            dbExecute("UPDATE support_tickets SET status='resolved', closed_at = NOW() WHERE id=?", [$ticketId]);
            logAction('Resolved Support Ticket', "Replied to ticket {$ticket['ticket_code']} and marked resolved.");
            $replySuccess = "Ticket {$ticket['ticket_code']} resolved successfully.";
        }
    }
}

$where = ['1=1'];
$params = [];
if ($filterStatus !== 'all') {
    $where[] = 'status = ?';
    $params[] = $filterStatus;
}
if ($search !== '') {
    $where[] = '(requester_name LIKE ? OR requester_email LIKE ? OR subject LIKE ? OR ticket_code LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$tickets = $hasSupportTickets ? dbQuery('SELECT * FROM support_tickets WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC', $params) : [];

require_once __DIR__ . '/../includes/admin_header.php';
?>

<style>
  .support-filters {
    display:flex;
    flex-wrap:wrap;
    gap:.75rem;
    margin-bottom:1.5rem;
    align-items:center;
  }
  .support-grid {
    display:grid;
    gap:1rem;
  }
  .support-card {
    padding:1.25rem;
  }
  .support-card h3 {
    margin:0;
    font-size:1.05rem;
    font-weight:800;
  }
  .support-meta {
    color:var(--clr-text-muted);
    font-size:.8rem;
    display:flex;
    flex-wrap:wrap;
    gap:.4rem .7rem;
    align-items:center;
  }
  .support-empty {
    padding:3rem 1.5rem;
    text-align:center;
  }
  .support-thread {
    display:grid;
    gap:.75rem;
  }
  .support-actions {
    margin-top:1rem;
    border-top:1px solid var(--clr-border);
    padding-top:1rem;
  }
  @media (max-width: 640px) {
    .support-card { padding:1rem; }
    .support-filters .form-control { width:100%; max-width:none !important; }
  }
</style>

<div class="page-header">
  <div>
    <h1 class="page-title"><?= admin_icon_svg('support') ?><span>Support Tickets</span></h1>
    <p class="text-muted">Respond to user inquiries, resolve booking conflicts, and manage support statuses.</p>
  </div>
</div>

<?php if ($replySuccess): ?><div class="alert alert-success">Success: <?= e($replySuccess) ?></div><?php endif; ?>
<?php if ($replyError): ?><div class="alert alert-error">Error: <?= e($replyError) ?></div><?php endif; ?>

<form method="GET" action="support.php" class="support-filters" id="support-filter-form">
  <input type="text" name="q" placeholder="Search tickets..." class="form-control" style="max-width:260px;" value="<?= e($search) ?>">
  <select name="status" class="form-control" style="max-width:160px;" onchange="this.form.submit()">
    <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All Statuses</option>
    <option value="open" <?= $filterStatus === 'open' ? 'selected' : '' ?>>Open</option>
    <option value="in_progress" <?= $filterStatus === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
    <option value="resolved" <?= $filterStatus === 'resolved' ? 'selected' : '' ?>>Resolved</option>
  </select>
  <button type="submit" class="btn btn-primary btn-sm" id="support-filter-btn"><?= admin_icon_svg('search') ?> Filter</button>
  <a href="support.php" class="btn btn-secondary btn-sm" id="support-clear-btn">Clear</a>
</form>

<?php if (empty($tickets)): ?>
  <div class="glass-panel support-empty">
    <div style="font-size:2.25rem;margin-bottom:1rem;"><?= admin_icon_svg('support') ?></div>
    <h3>No tickets found</h3>
    <p class="text-muted" style="margin-top:.5rem;">There are no support tickets matching the current filter.</p>
  </div>
<?php else: ?>
  <div class="support-grid">
    <?php foreach ($tickets as $ticket):
      $responses = $hasTicketResponses ? dbQuery('SELECT * FROM ticket_responses WHERE ticket_id=? ORDER BY created_at ASC', [$ticket['id']]) : [];
      $badgeClass = match ($ticket['status']) {
        'resolved' => 'badge-approved',
        'open' => 'badge-pending',
        default => 'badge-pending',
      };
    ?>
      <div class="glass-panel support-card" id="ticket-admin-<?= e($ticket['id']) ?>">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;">
          <div>
            <span class="font-mono text-xs text-muted">#<?= e($ticket['id']) ?></span>
            <h3 style="margin-top:.25rem;"><?= e($ticket['subject']) ?></h3>
            <div class="support-meta" style="margin-top:.35rem;">
              <span><?= admin_icon_svg('user') ?> <?= e($ticket['requester_name']) ?></span>
              <span>Category: <?= e($ticket['category']) ?></span>
              <span><?= admin_icon_svg('calendar') ?> <?= e(substr($ticket['created_at'], 0, 16)) ?></span>
            </div>
          </div>
          <span class="badge <?= $badgeClass ?>"><?= e($ticket['status']) ?></span>
        </div>

        <div class="support-thread">
          <div class="chat-msg chat-msg-user">
            <p><?= nl2br(e($ticket['message'])) ?></p>
            <div class="chat-meta">
              <span><?= e($ticket['requester_name']) ?> (Customer)</span>
              <span><?= e(substr($ticket['created_at'], 11, 5)) ?></span>
            </div>
          </div>

          <?php foreach ($responses as $response): ?>
            <?php $isAdmin = in_array($response['sender'], ['System Administrator', 'Support'], true); ?>
            <div class="chat-msg <?= $isAdmin ? 'chat-msg-admin' : 'chat-msg-user' ?>">
              <p><?= nl2br(e($response['message'])) ?></p>
              <div class="chat-meta">
                <span><?= e($response['sender']) ?></span>
                <span><?= e(substr($response['created_at'], 11, 5)) ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if ($ticket['status'] !== 'resolved'): ?>
          <form method="POST" action="support.php" class="support-actions" id="reply-form-<?= e($ticket['id']) ?>">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="reply_ticket" value="1">
            <input type="hidden" name="ticket_id" value="<?= e($ticket['id']) ?>">

            <div class="form-group">
              <label class="form-label">Write Response</label>
              <textarea name="reply_message" class="form-control" rows="3" placeholder="Type your reply to the customer here. Sending will mark the ticket resolved..." required style="resize:vertical;"></textarea>
            </div>

            <div style="text-align:right;">
              <button type="submit" class="btn btn-primary" id="reply-btn-<?= e($ticket['id']) ?>"><?= admin_icon_svg('plus') ?> Send Reply & Resolve Ticket</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
