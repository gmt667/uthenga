<?php
/**
 * Uthenga — Blog Post Reader Page
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$id = trim($_GET['id'] ?? '');
if (empty($id)) {
    redirect(BASE_URL . 'blog.php');
}

$post = dbQueryOne("SELECT * FROM blog_posts WHERE id = ?", [$id]);

if (!$post) {
    $pageTitle = 'Article Not Found';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <title>Article Not Found | Uthenga</title>
      <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    </head>
    <body>
    <?php require_once __DIR__ . '/includes/header.php'; ?>
    <div class="container" style="padding: 4rem 0; text-align: center;">
      <h2>Article Not Found</h2>
      <p class="text-muted">The requested blog post could not be found.</p>
      <a href="<?= BASE_URL ?>blog.php" class="btn btn-primary" style="margin-top: 1rem;">Back to Blog</a>
    </div>
    <?php require_once __DIR__ . '/includes/footer.php'; ?>
    </body>
    </html>
    <?php
    exit;
}

$pageTitle = $post['title'];
$activeNav = 'blog';

// Fetch recent posts for recommendations (exclude current)
$recent = dbQuery("SELECT * FROM blog_posts WHERE id != ? ORDER BY post_date DESC LIMIT 2", [$id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= e($post['excerpt']) ?>">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <title><?= e($post['title']) ?> | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <style>
    .post-hero {
      position: relative;
      height: 400px;
      overflow: hidden;
      border-radius: var(--radius-xl);
      margin-bottom: 2rem;
    }
    .post-hero img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .post-hero-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(to top, rgba(10,10,15,0.95) 0%, transparent 60%);
    }
    .post-hero-info {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      padding: 2.5rem;
    }
    .post-content {
      font-size: 1.05rem;
      line-height: 1.8;
      color: var(--clr-text-soft);
      max-width: 800px;
      margin: 0 auto 4rem;
    }
    .post-content p {
      margin-bottom: 1.5rem;
      color: var(--clr-text-soft);
    }
    .post-content h3 {
      font-size: 1.4rem;
      color: var(--clr-text);
      margin: 2.5rem 0 1rem;
    }
    .post-recommendations {
      border-top: 1px solid var(--clr-border);
      padding-top: 3rem;
      margin-top: 2rem;
    }
    .rec-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 2rem;
      margin-top: 1.5rem;
    }
    @media (max-width: 768px) {
      .rec-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container" style="padding-top: 2rem; padding-bottom: 4rem;">
  
  <!-- Breadcrumb -->
  <nav style="font-size:0.8rem;color:var(--clr-text-muted);margin-bottom:1.5rem;">
    <a href="<?= BASE_URL ?>index.php">Explore</a>
    <span style="margin:0 0.4rem;">›</span>
    <a href="<?= BASE_URL ?>blog.php">Blog</a>
    <span style="margin:0 0.4rem;">›</span>
    <span style="color:var(--clr-text-soft);"><?= e($post['title']) ?></span>
  </nav>

  <!-- Article Hero -->
  <header class="post-hero animate-in">
    <img src="<?= e($post['image']) ?>" alt="<?= e($post['title']) ?>">
    <div class="post-hero-overlay"></div>
    <div class="post-hero-info">
      <span class="card-badge badge-event" style="position:static; display:inline-flex; margin-bottom:0.75rem;"><?= e($post['category']) ?></span>
      <h1 style="font-size:clamp(1.5rem, 5vw, 2.5rem); color:#fff; text-shadow: 0 2px 8px rgba(0,0,0,0.5); margin-bottom: 0.5rem;"><?= e($post['title']) ?></h1>
      <div style="font-size: 0.9rem; color: var(--clr-text-muted); display:flex; gap: 1rem;">
        <span>✏️ By <strong><?= e($post['author']) ?></strong></span>
        <span>📅 Published: <?= e($post['post_date']) ?></span>
      </div>
    </div>
  </header>

  <!-- Article Body -->
  <main class="post-content animate-in">
    <?= nl2br(e($post['content'])) ?>
  </main>

  <!-- Article Recommendations -->
  <?php if (!empty($recent)): ?>
  <section class="post-recommendations">
    <h2>Recommended Articles</h2>
    <div class="rec-grid">
      <?php foreach ($recent as $recPost): ?>
        <div class="card" style="display:flex; flex-direction:row; gap:1rem; padding:1rem; align-items:center;">
          <img src="<?= e($recPost['image']) ?>" alt="" style="width:100px; height:100px; object-fit:cover; border-radius:var(--radius-md); flex-shrink:0;">
          <div>
            <span style="font-size:0.7rem; font-weight:700; text-transform:uppercase; color:var(--clr-accent);"><?= e($recPost['category']) ?></span>
            <h4 style="margin:0.25rem 0; font-size:1rem; line-height:1.3; font-weight:700;"><a href="blog-post.php?id=<?= e($recPost['id']) ?>"><?= e($recPost['title']) ?></a></h4>
            <div class="text-xs text-muted">📅 <?= e($recPost['post_date']) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
