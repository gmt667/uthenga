<?php
/**
 * Uthenga — About Us Page
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$pageTitle = 'About Us';
$activeNav = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Learn more about Uthenga, Malawi's premier tourist and travel experiences marketplace.">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <style>
    .about-hero {
      background: linear-gradient(135deg, #1e1b4b 0%, #311042 100%);
      padding: 5rem 0;
      text-align: center;
      margin-bottom: 3rem;
      border-bottom: 1px solid var(--clr-border);
    }
    .about-section {
      max-width: 800px;
      margin: 0 auto 4rem;
      line-height: 1.8;
    }
    .about-section h2 {
      margin-bottom: 1rem;
      font-size: 1.6rem;
      color: var(--clr-text);
    }
    .about-section p {
      margin-bottom: 1.5rem;
      color: var(--clr-text-soft);
    }
    .stat-badge-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1.5rem;
      margin: 2.5rem 0;
      text-align: center;
    }
    .stat-item {
      background: var(--clr-surface);
      border: 1px solid var(--clr-border);
      padding: 1.5rem;
      border-radius: var(--radius-md);
    }
    .stat-num {
      font-size: 2rem;
      font-weight: 800;
      color: var(--clr-accent);
      margin-bottom: 0.25rem;
    }
    .team-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 2rem;
      margin-top: 2rem;
    }
    .team-member {
      background: var(--clr-surface);
      border: 1px solid var(--clr-border);
      border-radius: var(--radius-lg);
      overflow: hidden;
      text-align: center;
      padding: 1.5rem;
      transition: var(--transition);
    }
    .team-member:hover {
      transform: translateY(-4px);
      border-color: var(--clr-border2);
    }
    .team-img {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      object-fit: cover;
      margin: 0 auto 1rem;
      border: 3px solid var(--clr-accent-glow);
    }
    @media (max-width: 768px) {
      .team-grid, .stat-badge-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<section class="about-hero animate-in">
  <div class="container">
    <span class="card-badge badge-event" style="position:static; display:inline-flex; margin-bottom:1rem;">Our Mission</span>
    <h1 style="font-size: clamp(2rem, 5vw, 3rem); color: #fff; margin-bottom: 0.5rem;">Connecting You to Malawi</h1>
    <p style="color: var(--clr-text-soft); max-width: 600px; margin: 0 auto;">We enable travellers to discover, plan, and book unique events, accommodations, and guided travel services across Malawi.</p>
  </div>
</section>

<div class="container">
  
  <main class="about-section animate-in">
    <h2>Welcome to Uthenga</h2>
    <p>Uthenga (which means "Message" or "News" in Chichewa) is Malawi's premier digital marketplace dedicated to making local travel, events, and tourism accessible to all. Launched in 2026, our platform serves as a vital bridge between verified Malawian service providers (vendors) and travellers seeking outstanding experiences in the Warm Heart of Africa.</p>
    
    <p>Whether you're looking to dance at the Lake of Stars Festival, relax at a luxury lakeshore lodge in Cape Maclear, hike the majestic Mount Mulanje, or find a comfortable coach transfer between Lilongwe and Blantyre, Uthenga brings it all to your fingertips.</p>

    <!-- Key Platform Stats -->
    <div class="stat-badge-grid">
      <div class="stat-item">
        <div class="stat-num">100%</div>
        <div class="text-sm text-muted">Malawian Owned</div>
      </div>
      <div class="stat-item">
        <div class="stat-num">5,000+</div>
        <div class="text-sm text-muted">Successful Bookings</div>
      </div>
      <div class="stat-item">
        <div class="stat-num">24/7</div>
        <div class="text-sm text-muted">Support Availability</div>
      </div>
    </div>

    <h2>Our Core Values</h2>
    <p><strong>Trust & Security:</strong> We vet every vendor on our platform and hold funds in escrow until services are successfully rendered. Our integrated secure gateways guarantee your transactions are safe.</p>
    <p><strong>Promoting Local Tourism:</strong> We empower small and medium tourism enterprises (SMEs)—from local guides to transport providers—by giving them digital infrastructure to reach a global audience.</p>

    <h2 style="margin-top: 3rem;">Meet Our Leadership Team</h2>
    <div class="team-grid">
      <div class="team-member">
        <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&fit=crop&q=80" alt="Founder" class="team-img">
        <h4 style="font-size: 1.1rem; margin-bottom: 0.2rem;">Desire Mwalwanda</h4>
        <div class="text-xs text-accent" style="margin-bottom: 0.5rem;">Co-Founder & CEO</div>
        <p class="text-xs text-muted">Tech entrepreneur dedicated to digitizing travel logistics in Southern Africa.</p>
      </div>
      <div class="team-member">
        <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=150&fit=crop&q=80" alt="CTO" class="team-img">
        <h4 style="font-size: 1.1rem; margin-bottom: 0.2rem;">Chisomo Phiri</h4>
        <div class="text-xs text-accent" style="margin-bottom: 0.5rem;">Chief Technology Officer</div>
        <p class="text-xs text-muted">Architect behind the Uthenga modular engine and payment integrations.</p>
      </div>
      <div class="team-member">
        <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&fit=crop&q=80" alt="Customer Success" class="team-img">
        <h4 style="font-size: 1.1rem; margin-bottom: 0.2rem;">Grace Banda</h4>
        <div class="text-xs text-accent" style="margin-bottom: 0.5rem;">Head of Customer Success</div>
        <p class="text-xs text-muted">Ensures that every booking is seamless and guest inquiries are resolved instantly.</p>
      </div>
    </div>
  </main>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
