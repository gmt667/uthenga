<?php
/**
 * Uthenga — Travel Guides & Blog
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$pageTitle = 'Travel Guides & Local Tips';
$activeNav = 'blog';

// Fetch all blog posts
$posts = dbQuery("SELECT * FROM blog_posts ORDER BY post_date DESC, created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Read travel guides, cultural tips, local events information and accommodation guides for Malawi on Uthenga.">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <style>
    .blog-hero {
      background: linear-gradient(135deg, #4c1d95 0%, #1e1b4b 100%);
      padding: 4rem 0;
      border-bottom: 1px solid var(--clr-border);
      margin-bottom: 3rem;
    }
    .blog-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 2rem;
    }
    .blog-card {
      background: var(--clr-surface);
      border: 1px solid var(--clr-border);
      border-radius: var(--radius-lg);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      transition: var(--transition);
    }
    .blog-card:hover {
      border-color: var(--clr-border2);
      transform: translateY(-4px);
      box-shadow: var(--shadow-md);
    }
    .blog-card-img {
      height: 200px;
      width: 100%;
      object-fit: cover;
    }
    .blog-card-body {
      padding: 1.5rem;
      display: flex;
      flex-direction: column;
      flex: 1;
    }
    .blog-category {
      font-size: 0.75rem;
      text-transform: uppercase;
      font-weight: 700;
      color: var(--clr-accent);
      letter-spacing: 0.05em;
      margin-bottom: 0.5rem;
    }
    .blog-title {
      font-size: 1.2rem;
      font-weight: 700;
      margin-bottom: 0.75rem;
      color: var(--clr-text);
      line-height: 1.3;
    }
    .blog-excerpt {
      font-size: 0.875rem;
      color: var(--clr-text-soft);
      line-height: 1.6;
      margin-bottom: 1.5rem;
      flex: 1;
    }
    .blog-meta {
      font-size: 0.75rem;
      color: var(--clr-text-muted);
      border-top: 1px solid var(--clr-border);
      padding-top: 1rem;
      display: flex;
      justify-content: space-between;
    }
    @media (max-width: 900px) {
      .blog-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 600px) {
      .blog-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<section class="blog-hero">
  <div class="container text-center" style="text-align: center;">
    <h1 style="font-size: 2.5rem; margin-bottom: 0.75rem;">📰 Uthenga Travel Blog</h1>
    <p style="color: var(--clr-text-soft); max-width: 600px; margin: 0 auto;">Your ultimate guide to exploring Malawi. Discover hidden attractions, local festivals, packing lists, and cultural travel tips.</p>
  </div>
</section>

<div class="container" style="padding-bottom: 5rem;">
  <?php if (empty($posts)): ?>
    <div class="glass-panel text-center" style="padding: 4rem 2rem; text-align: center;">
      <div style="font-size:3rem;margin-bottom:1rem;">📰</div>
      <h3>No blog posts published yet</h3>
      <p class="text-muted" style="margin-top:0.5rem;">Check back later for exciting travel articles.</p>
      <a href="<?= BASE_URL ?>index.php" class="btn btn-secondary" style="margin-top:1rem;">Back Home</a>
    </div>
  <?php else: ?>
    <div class="blog-grid">
      <?php foreach ($posts as $post): ?>
        <article class="blog-card animate-in">
          <img src="<?= e($post['image']) ?>" alt="<?= e($post['title']) ?>" class="blog-card-img" loading="lazy">
          <div class="blog-card-body">
            <span class="blog-category"><?= e($post['category']) ?></span>
            <h3 class="blog-title"><?= e($post['title']) ?></h3>
            <p class="blog-excerpt"><?= e($post['excerpt']) ?></p>
            <div class="blog-meta">
              <span>By <?= e($post['author']) ?></span>
              <span>📅 <?= e($post['post_date']) ?></span>
            </div>
            <a href="blog-post.php?id=<?= e($post['id']) ?>" class="btn btn-sm btn-secondary" style="margin-top: 1.25rem; text-align: center;">Read Article</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
