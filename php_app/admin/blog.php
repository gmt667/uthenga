<?php
/**
 * Uthenga — Admin Blog CMS
 */
$pageTitle = 'Blog CMS Editor';
$activeNav = 'admin-blog';

require_once __DIR__ . '/includes/admin_header.php';

$message = '';
$err = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrf()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $title    = trim($_POST['title'] ?? '');
        $excerpt  = trim($_POST['excerpt'] ?? '');
        $content  = trim($_POST['content'] ?? '');
        $image    = trim($_POST['image'] ?? '');
        $author   = trim($_POST['author'] ?? '');
        $category = $_POST['category'] ?? 'Tips';
        
        if (empty($title) || empty($excerpt) || empty($content) || empty($image) || empty($author)) {
            $err = 'All fields are required to create a blog post.';
        } else {
            $postId = 'POST-' . rand(1000, 9999);
            dbExecute(
                "INSERT INTO blog_posts (id, title, excerpt, content, image, author, category, post_date) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())",
                [$postId, $title, $excerpt, $content, $image, $author, $category]
            );
            logAction('Created Blog Post', "Admin created blog post: \"$title\"");
            $message = "Blog post successfully published!";
        }
    } elseif ($action === 'edit') {
        $postId   = $_POST['post_id'] ?? '';
        $title    = trim($_POST['title'] ?? '');
        $excerpt  = trim($_POST['excerpt'] ?? '');
        $content  = trim($_POST['content'] ?? '');
        $image    = trim($_POST['image'] ?? '');
        $author   = trim($_POST['author'] ?? '');
        $category = $_POST['category'] ?? 'Tips';
        
        if (empty($postId) || empty($title) || empty($excerpt) || empty($content) || empty($image) || empty($author)) {
            $err = 'All fields are required to update a blog post.';
        } else {
            dbExecute(
                "UPDATE blog_posts SET title = ?, excerpt = ?, content = ?, image = ?, author = ?, category = ? WHERE id = ?",
                [$title, $excerpt, $content, $image, $author, $category, $postId]
            );
            logAction('Updated Blog Post', "Admin updated blog post: $postId");
            $message = "Blog post updated successfully!";
        }
    } elseif ($action === 'delete') {
        $postId = $_POST['post_id'] ?? '';
        if (!empty($postId)) {
            dbExecute("DELETE FROM blog_posts WHERE id = ?", [$postId]);
            logAction('Deleted Blog Post', "Admin deleted blog post: $postId");
            $message = "Blog post deleted successfully.";
        }
    }
}

// Fetch all posts
$posts = dbQuery("SELECT * FROM blog_posts ORDER BY post_date DESC, created_at DESC");
?>

<div class="page-header">
  <div>
    <h1 class="page-title">📰 Blog CMS Editor</h1>
    <p class="text-muted">Manage travel guides, news, and culture articles shown on the customer site.</p>
  </div>
  <div>
    <button class="btn btn-primary" onclick="openCreateModal()">+ Write Post</button>
  </div>
</div>

<?php if ($message): ?><div class="alert alert-success">✓ <?= e($message) ?></div><?php endif; ?>
<?php if ($err):     ?><div class="alert alert-error">✕ <?= e($err) ?></div><?php endif; ?>

<div class="glass-panel" style="padding: 1.5rem; margin-top: 1rem;">
  <?php if (empty($posts)): ?>
    <div style="text-align: center; padding: 4rem 0;">
      <div style="font-size: 3rem; margin-bottom: 1rem;">📰</div>
      <h3>No blog posts published yet</h3>
      <button class="btn btn-primary" style="margin-top: 1rem;" onclick="openCreateModal()">Write First Post</button>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Image</th>
            <th>Title</th>
            <th>Author</th>
            <th>Category</th>
            <th>Published Date</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($posts as $post): ?>
            <tr>
              <td>
                <img src="<?= e($post['image']) ?>" alt="" style="width: 50px; height: 50px; object-fit: cover; border-radius: var(--radius-sm);">
              </td>
              <td>
                <strong style="color: var(--clr-text); font-size: 0.9rem;"><?= e($post['title']) ?></strong>
                <div class="text-xs text-muted" style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= e($post['excerpt']) ?></div>
              </td>
              <td><?= e($post['author']) ?></td>
              <td><span class="role-badge role-customer"><?= e($post['category']) ?></span></td>
              <td class="text-xs text-muted"><?= e($post['post_date']) ?></td>
              <td style="text-align: right;">
                <div style="display: inline-flex; gap: 0.5rem; justify-content: flex-end;">
                  <button class="btn btn-sm btn-secondary" onclick="openEditModal(<?= e(json_encode($post)) ?>)">Edit</button>
                  <form method="POST" style="margin:0;" onsubmit="return confirm('Are you sure you want to delete this article?');">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="post_id" value="<?= e($post['id']) ?>">
                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Blog Editor Modal -->
<div class="modal-overlay" id="post-modal" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="modal" style="max-width: 700px;">
    <div class="modal-header">
      <h3 id="modal-title">Write Blog Post</h3>
      <button class="modal-close" onclick="closeModal('post-modal')">✕</button>
    </div>
    <form method="POST" id="post-form">
      <div class="modal-body" style="max-height: 480px; overflow-y: auto;">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" id="form-action" value="create">
        <input type="hidden" name="post_id" id="form-post-id" value="">

        <div class="form-group">
          <label class="form-label">Article Title</label>
          <input type="text" name="title" id="form-title" class="form-control" placeholder="e.g. Packing Guide for Lake Malawi" required>
        </div>

        <div class="form-group">
          <label class="form-label">Author Name</label>
          <input type="text" name="author" id="form-author" class="form-control" placeholder="Grace Banda" required>
        </div>

        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category" id="form-category" class="form-control" required>
            <option value="Travel Guide">Travel Guide</option>
            <option value="Local Events">Local Events</option>
            <option value="Culture">Culture</option>
            <option value="Tips">Tips</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Featured Image URL</label>
          <input type="url" name="image" id="form-image" class="form-control" placeholder="https://images.unsplash.com/photo-..." required>
        </div>

        <div class="form-group">
          <label class="form-label">Short Excerpt (Summary)</label>
          <input type="text" name="excerpt" id="form-excerpt" class="form-control" placeholder="A one-line description summarizing the post..." required>
        </div>

        <div class="form-group">
          <label class="form-label">Article Content</label>
          <textarea name="content" id="form-content" class="form-control" rows="8" placeholder="Write full article here. Use blank lines for paragraphs..." required style="resize: vertical;"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('post-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="btn-save">Publish Article</button>
      </div>
    </form>
  </div>
</div>

<script>
function openCreateModal() {
  document.getElementById('form-action').value = 'create';
  document.getElementById('form-post-id').value = '';
  document.getElementById('form-title').value = '';
  document.getElementById('form-author').value = '<?= e($_SESSION['user_name']) ?>';
  document.getElementById('form-category').value = 'Travel Guide';
  document.getElementById('form-image').value = '';
  document.getElementById('form-excerpt').value = '';
  document.getElementById('form-content').value = '';
  document.getElementById('modal-title').textContent = 'Write Blog Post';
  document.getElementById('btn-save').textContent = 'Publish Article';
  openModal('post-modal');
}

function openEditModal(post) {
  document.getElementById('form-action').value = 'edit';
  document.getElementById('form-post-id').value = post.id;
  document.getElementById('form-title').value = post.title;
  document.getElementById('form-author').value = post.author;
  document.getElementById('form-category').value = post.category;
  document.getElementById('form-image').value = post.image;
  document.getElementById('form-excerpt').value = post.excerpt;
  document.getElementById('form-content').value = post.content;
  document.getElementById('modal-title').textContent = 'Edit Blog Post';
  document.getElementById('btn-save').textContent = 'Save Changes';
  openModal('post-modal');
}
</script>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
?>
