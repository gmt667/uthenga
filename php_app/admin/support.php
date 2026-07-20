<?php
/**
 * Uthenga — Admin Support Tickets Management
 */
$pageTitle = 'Support Tickets';
$activeNav = 'admin-support';

require_once __DIR__ . '/includes/admin_header.php';

$filterStatus = $_GET['status'] ?? 'all';
$search       = trim($_GET['q'] ?? '');
$replySuccess = '';
$replyError   = '';
$hasSupportTickets = uthenga_table_exists('support_tickets');
$hasTicketResponses = uthenga_table_exists('ticket_responses');

// Handle reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_ticket']) && validateCsrf()) {
    $ticketId = trim($_POST['ticket_id'] ?? '');
    $message  = trim($_POST['reply_message'] ?? '');
    if (empty($message)) {
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

$where  = ['1=1'];
$params = [];
if ($filterStatus !== 'all') { $where[] = 'status = ?'; $params[] = $filterStatus; }
if ($search) { $where[] = '(requester_name LIKE ? OR requester_email LIKE ? OR subject LIKE ? OR ticket_code LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
$tickets = $hasSupportTickets ? dbQuery('SELECT * FROM support_tickets WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC', $params) : [];
?>

<div class="page-header">
  <div>
    <h1 class="page-title">💬 Support Tickets</h1>
    <p class="text-muted">Respond to user inquiries, resolve booking conflicts, and manage support statuses.</p>
  </div>
</div>

<?php if ($replySuccess): ?><div class="alert alert-success">✓ <?= e($replySuccess) ?></div><?php endif; ?>
<?php if ($replyError):   ?><div class="alert alert-error">✕ <?= e($replyError) ?></div><?php endif; ?>

<!-- Filters -->
<form method="GET" action="support.php" style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:1.5rem;" id="support-filter-form">
  <input type="text" name="q" placeholder="Search tickets…" class="form-control" style="max-width:260px;" value="<?= e($search) ?>">
  <select name="status" class="form-control" style="max-width:160px;" onchange="this.form.submit()">
    <option value="all"        <?= $filterStatus==='all'        ?'selected':'' ?>>All Statuses</option>
    <option value="open"       <?= $filterStatus==='open'       ?'selected':'' ?>>Open</option>
    <option value="in_progress"<?= $filterStatus==='in_progress'?'selected':'' ?>>In Progress</option>
    <option value="resolved"   <?= $filterStatus==='resolved'   ?'selected':'' ?>>Resolved</option>
  </select>
  <button type="submit" class="btn btn-primary btn-sm" id="support-filter-btn">Filter</button>
  <a href="support.php" class="btn btn-secondary btn-sm" id="support-clear-btn">Clear</a>
</form>

<?php if (empty($tickets)): ?>
  <div class="glass-panel text-center" style="padding:4rem 0; text-align: center;">
    <div style="font-size:3rem;margin-bottom:1rem;">💬</div>
    <h3>No tickets found</h3>
  </div>
<?php else: ?>
  <div style="display:grid;gap:1.5rem;">
    <?php foreach ($tickets as $t):
      $responses = $hasTicketResponses ? dbQuery('SELECT * FROM ticket_responses WHERE ticket_id=? ORDER BY created_at ASC', [$t['id']]) : [];
    ?>
    <div class="card animate-in" style="padding:1.5rem;" id="ticket-admin-<?= e($t['id']) ?>">
      <div class="flex items-center justify-between" style="flex-wrap:wrap;gap:0.75rem;margin-bottom:1rem;">
        <div>
          <span class="font-mono text-xs text-muted">#<?= e($t['id']) ?></span>
          <h3 style="margin-top:0.2rem; font-size:1.15rem; font-weight:700;"><?= e($t['subject']) ?></h3>
          <div class="text-xs text-muted" style="margin-top:0.15rem;">
            👤 <?= e($t['requester_name']) ?> · Category: <?= e($t['category']) ?> · 📅 <?= e(substr($t['created_at'],0,16)) ?>
          </div>
        </div>
        <div>
          <?php
          $badgeClass = match($t['status']) {
              'resolved' => 'badge-approved',
              'open'     => 'badge-pending',
              default    => 'badge-pending'
          };
          ?>
          <span class="badge <?= $badgeClass ?>"><?= e($t['status']) ?></span>
        </div>
      </div>
      
      <!-- Conversation History -->
      <div class="ticket-conversation">
        <!-- Original customer inquiry -->
        <div class="chat-msg chat-msg-user">
          <p><?= nl2br(e($t['message'])) ?></p>
          <div class="chat-meta">
            <span><?= e($t['requester_name']) ?> (Customer)</span>
            <span><?= e(substr($t['created_at'],11,5)) ?></span>
          </div>
        </div>

        <!-- Subsequent replies -->
        <?php foreach ($responses as $r): 
          $isAdmin = ($r['sender'] === 'System Administrator' || $r['sender'] === 'Support');
          $msgClass = $isAdmin ? 'chat-msg-admin' : 'chat-msg-user';
        ?>
          <div class="chat-msg <?= $msgClass ?>">
            <p><?= nl2br(e($r['message'])) ?></p>
            <div class="chat-meta">
              <span><?= e($r['sender']) ?></span>
              <span><?= e(substr($r['created_at'],11,5)) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Reply form (show only if not resolved) -->
      <?php if ($t['status'] !== 'resolved'): ?>
      <form method="POST" action="support.php" id="reply-form-<?= e($t['id']) ?>" style="margin-top:1rem; border-top: 1px solid var(--clr-border); padding-top: 1rem;">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="reply_ticket" value="1">
        <input type="hidden" name="ticket_id" value="<?= e($t['id']) ?>">
        
        <div class="form-group">
          <label class="form-label">Write Response</label>
          <textarea name="reply_message" class="form-control" rows="3" placeholder="Type your reply to customer here. Sending will mark the ticket resolved..." required style="resize:vertical;"></textarea>
        </div>
        
        <div style="text-align: right;">
          <button type="submit" class="btn btn-primary" id="reply-btn-<?= e($t['id']) ?>">Send Reply & Resolve Ticket</button>
        </div>
      </form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
?>
