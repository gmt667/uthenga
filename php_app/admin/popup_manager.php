<?php
/**
 * Uthenga — Admin Promotional Popup Manager
 */
$pageTitle = 'Promotional Popups';
$activeNav = 'admin-promotions';

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/includes/admin_header.php';

// Success & error message state
$successMsg = '';
$errorMsg = '';
$hasPopupsTable = uthenga_table_exists('promotional_popups');

// Check actions: Add, Edit, Delete, Toggle
$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

// Create upload directory if it doesn't exist
$uploadDir = __DIR__ . '/../assets/images/popups/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

// Handle Form Submission (Add or Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_popup'])) {
    if (!validateCsrf()) {
        $errorMsg = 'CSRF verification failed.';
    } elseif (!$hasPopupsTable) {
        $errorMsg = 'The promotional_popups table does not exist yet. Please run the migrations first.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $ctaText = trim($_POST['cta_text'] ?? 'Learn More');
        $ctaUrl = trim($_POST['cta_url'] ?? '#');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $delaySeconds = (int)($_POST['display_delay_seconds'] ?? 3);

        if ($title === '') {
            $errorMsg = 'Title is required.';
        } else {
            $imageUrl = $_POST['existing_image_url'] ?? '';

            // Handle Image Upload
            if (isset($_FILES['popup_image']) && $_FILES['popup_image']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['popup_image']['tmp_name'];
                $fileName = $_FILES['popup_image']['name'];
                $fileSize = $_FILES['popup_image']['size'];
                $fileType = $_FILES['popup_image']['type'];
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));

                // Sanitise file name
                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;

                // Allowed extensions
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                if (in_array($fileExtension, $allowedExtensions, true)) {
                    $destPath = $uploadDir . $newFileName;
                    if (move_uploaded_file($fileTmpPath, $destPath)) {
                        // Store relative URL
                        $imageUrl = 'assets/images/popups/' . $newFileName;
                    } else {
                        $errorMsg = 'There was an error moving the uploaded file.';
                    }
                } else {
                    $errorMsg = 'Upload failed. Allowed file types: ' . implode(',', $allowedExtensions);
                }
            }

            if ($errorMsg === '') {
                if ($id > 0) {
                    // Update
                    try {
                        dbExecute("
                            UPDATE promotional_popups 
                            SET title = ?, description = ?, image_url = ?, cta_text = ?, cta_url = ?, 
                                is_active = ?, start_date = ?, end_date = ?, display_delay_seconds = ?, updated_at = NOW()
                            WHERE id = ?
                        ", [$title, $description, $imageUrl, $ctaText, $ctaUrl, $isActive, $startDate, $endDate, $delaySeconds, $id]);
                        $successMsg = 'Promotional popup updated successfully.';
                    } catch (Exception $e) {
                        $errorMsg = 'Failed to update popup: ' . $e->getMessage();
                    }
                } else {
                    // Insert
                    try {
                        dbExecute("
                            INSERT INTO promotional_popups 
                            (title, description, image_url, cta_text, cta_url, is_active, start_date, end_date, display_delay_seconds, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ", [$title, $description, $imageUrl, $ctaText, $ctaUrl, $isActive, $startDate, $endDate, $delaySeconds]);
                        $successMsg = 'Promotional popup added successfully.';
                    } catch (Exception $e) {
                        $errorMsg = 'Failed to create popup: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Handle Delete
if ($action === 'delete' && $id > 0) {
    if (!$hasPopupsTable) {
        $errorMsg = 'The promotional_popups table does not exist yet. Please run the migrations first.';
    } else {
        try {
            dbExecute("DELETE FROM promotional_popups WHERE id = ?", [$id]);
            $successMsg = 'Popup deleted successfully.';
        } catch (Exception $e) {
            $errorMsg = 'Failed to delete popup: ' . $e->getMessage();
        }
    }
}

// Handle Status Toggle
if ($action === 'toggle' && $id > 0) {
    if (!$hasPopupsTable) {
        $errorMsg = 'The promotional_popups table does not exist yet. Please run the migrations first.';
    } else {
        try {
            dbExecute("UPDATE promotional_popups SET is_active = 1 - is_active WHERE id = ?", [$id]);
            $successMsg = 'Popup status toggled.';
        } catch (Exception $e) {
            $errorMsg = 'Failed to toggle popup status: ' . $e->getMessage();
        }
    }
}

// Fetch single popup for edit
$editPopup = null;
if ($action === 'edit' && $id > 0) {
    $editPopup = $hasPopupsTable ? dbQueryOne("SELECT * FROM promotional_popups WHERE id = ?", [$id]) : null;
}

// Fetch all popups
$popups = [];
if ($hasPopupsTable) {
    try {
        $popups = dbQuery("SELECT * FROM promotional_popups ORDER BY id DESC");
    } catch (Exception $e) {
        $errorMsg = 'Table promotional_popups exists but could not be queried. Please check the database.';
    }
} else {
    $errorMsg = 'Table promotional_popups does not exist or SQL error occurred. Please make sure migrations have run.';
}
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Promotional Popups</h1>
    <p class="text-muted">Manage homepage marketing popups, schedules, and CTA links.</p>
  </div>
</div>

<?php if ($successMsg): ?>
  <div class="alert alert-success"><div><?= e($successMsg) ?></div></div>
<?php endif; ?>
<?php if ($errorMsg): ?>
  <div class="alert alert-error"><div><?= e($errorMsg) ?></div></div>
<?php endif; ?>

<div class="grid grid-cols-2 gap-3" style="align-items: start;">
  
  <!-- Left Side: List of existing popups -->
  <div class="glass-panel" style="padding:1.5rem;">
    <h2 style="font-size:1.2rem;margin-bottom:1rem;color:var(--clr-text);">Active Campaigns</h2>
    
    <div class="table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Popup Campaign</th>
            <th>Schedule</th>
            <th>Delay</th>
            <th>Status</th>
            <th style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($popups)): ?>
            <tr>
              <td colspan="5" style="text-align:center;padding:2rem;color:var(--clr-text-muted);">
                No promotional campaigns created yet. Use the form to schedule one.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($popups as $p): ?>
              <tr>
                <td>
                  <div style="font-weight:600;"><?= e($p['title']) ?></div>
                  <?php if ($p['image_url']): ?>
                    <div class="text-xs text-muted" style="margin-top:0.25rem;">
                      <?= admin_icon_svg('file') ?> <a href="<?= BASE_URL . e($p['image_url']) ?>" target="_blank" rel="noopener">View image</a>
                    </div>
                  <?php endif; ?>
                </td>
                <td class="text-xs text-soft">
                  <?php if (!$p['start_date'] && !$p['end_date']): ?>
                    <span style="color:var(--clr-green);">Always Displaying</span>
                  <?php else: ?>
                    <?= $p['start_date'] ? e($p['start_date']) : 'Anytime' ?> to 
                    <?= $p['end_date'] ? e($p['end_date']) : 'Anytime' ?>
                  <?php endif; ?>
                </td>
                <td class="text-xs"><?= (int)$p['display_delay_seconds'] ?>s</td>
                <td>
                  <a href="popup_manager.php?action=toggle&id=<?= $p['id'] ?>" 
                     class="status-badge <?= $p['is_active'] ? 'status-confirmed' : 'status-cancelled' ?>" 
                     style="text-decoration:none;">
                    <?= $p['is_active'] ? 'Active' : 'Disabled' ?>
                  </a>
                </td>
                <td style="text-align:right;white-space:nowrap;">
                  <a href="popup_manager.php?action=edit&id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                  <a href="popup_manager.php?action=delete&id=<?= $p['id'] ?>" 
                     onclick="return confirm('Are you sure you want to delete this popup?');" 
                     class="btn btn-danger btn-sm">Delete</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Right Side: Add / Edit Form -->
  <div class="glass-panel" style="padding:1.5rem;">
    <h2 style="font-size:1.2rem;margin-bottom:1rem;color:var(--clr-text);">
      <?= $editPopup ? 'Edit Campaign' : 'Create Campaign' ?>
    </h2>
    
    <form method="POST" action="popup_manager.php<?= $editPopup ? '?action=edit&id=' . $editPopup['id'] : '' ?>" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="existing_image_url" value="<?= e($editPopup['image_url'] ?? '') ?>">
      
      <div class="form-group">
        <label class="form-label">Popup Title *</label>
        <input type="text" name="title" class="form-control" placeholder="e.g. 50% Off Summer Festival Tickets!" 
               value="<?= e($editPopup['title'] ?? '') ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Description / Subtitle</label>
        <textarea name="description" class="form-control" placeholder="Add details about this promo campaign..." 
                  style="min-height:80px;"><?= e($editPopup['description'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Banner Image File</label>
        <input type="file" name="popup_image" class="form-control" accept="image/*">
        <?php if (!empty($editPopup['image_url'])): ?>
          <div class="text-xs text-muted" style="margin-top:0.4rem;">
            Current: <code><?= e($editPopup['image_url']) ?></code>
          </div>
        <?php endif; ?>
      </div>

      <div class="grid grid-cols-2 gap-2">
        <div class="form-group">
          <label class="form-label">CTA Button Label</label>
          <input type="text" name="cta_text" class="form-control" placeholder="e.g. Claim Now" 
                 value="<?= e($editPopup['cta_text'] ?? 'Learn More') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">CTA Destination URL</label>
          <input type="text" name="cta_url" class="form-control" placeholder="e.g. events.php" 
                 value="<?= e($editPopup['cta_url'] ?? '#') ?>">
        </div>
      </div>

      <div class="grid grid-cols-3 gap-2">
        <div class="form-group">
          <label class="form-label">Display Delay</label>
          <select name="display_delay_seconds" class="form-control">
            <?php for ($sec = 1; $sec <= 10; $sec++): ?>
              <option value="<?= $sec ?>" <?= ($editPopup && (int)$editPopup['display_delay_seconds'] === $sec) || (!$editPopup && $sec === 3) ? 'selected' : '' ?>>
                <?= $sec ?> seconds
              </option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Start Date</label>
          <input type="date" name="start_date" class="form-control" value="<?= e($editPopup['start_date'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">End Date</label>
          <input type="date" name="end_date" class="form-control" value="<?= e($editPopup['end_date'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group" style="display:flex;align-items:center;gap:0.5rem;padding:0.25rem 0;">
        <input type="checkbox" name="is_active" id="is_active" value="1" 
               <?= (!isset($editPopup) || !empty($editPopup['is_active'])) ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--clr-accent);">
        <label for="is_active" class="form-label" style="margin-bottom:0;cursor:pointer;user-select:none;">
          Enable popup immediately
        </label>
      </div>

      <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1.5rem;">
        <?php if ($editPopup): ?>
          <a href="popup_manager.php" class="btn btn-secondary">Cancel Edit</a>
        <?php endif; ?>
        <button type="submit" name="save_popup" class="btn btn-primary" <?= !$hasPopupsTable ? 'disabled' : '' ?>>
          <?= $editPopup ? 'Save Changes' : 'Create Popup' ?>
        </button>
      </div>
    </form>
    <?php if (!$hasPopupsTable): ?>
      <p class="text-xs text-muted" style="margin-top:0.75rem;">Popup creation is disabled until the promotional_popups table exists.</p>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
