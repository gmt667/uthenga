<?php
/**
 * Uthenga — Print/Save Trip Itinerary
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/malawi_locations.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die('Invalid itinerary ID.');
}

$plan = dbQueryOne("SELECT * FROM trip_planner_sessions WHERE id = ?", [$id]);

if (!$plan) {
    die('Itinerary not found.');
}

$planData = json_decode($plan['plan_json'], true);
$itinerary = $planData['itinerary'] ?? [];
$suggestions = $planData['suggestions'] ?? [];
$estimatedCost = $planData['total_estimated_cost'] ?? 0;
$planLocation = uthenga_malawi_find_location((string)($plan['destination'] ?? '')) ?? [];
$planHeroImage = $planLocation['image'] ?? 'https://images.unsplash.com/photo-1504701954957-2010ec3bcec1?w=1600&fit=crop&q=80';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Itinerary: <?= e($plan['destination']) ?> (<?= e($plan['days']) ?> Days) | <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --clr-primary: #06b6d4;
      --clr-text: #1f2937;
      --clr-muted: #6b7280;
      --clr-border: #e5e7eb;
    }
    body {
      font-family: 'Inter', sans-serif;
      color: var(--clr-text);
      line-height: 1.6;
      margin: 0;
      padding: 2rem;
      background:
        linear-gradient(180deg, rgba(249,250,251,0.96), rgba(249,250,251,0.92)),
        url('<?= e($planHeroImage) ?>') center top / cover fixed;
    }
    .print-container {
      max-width: 800px;
      margin: 0 auto;
      background: rgba(255,255,255,0.96);
      padding: 3rem;
      border-radius: 8px;
      box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
      border: 1px solid var(--clr-border);
      backdrop-filter: blur(8px);
    }
    .header {
      border-bottom: 2px solid var(--clr-primary);
      padding-bottom: 1.5rem;
      margin-bottom: 2rem;
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
    }
    .hero-strip {
      display: grid;
      grid-template-columns: 180px 1fr;
      gap: 1rem;
      align-items: center;
      margin-bottom: 1.5rem;
      padding: 1rem;
      border-radius: 12px;
      background: linear-gradient(135deg, rgba(6,182,212,0.12), rgba(14,165,233,0.08));
      border: 1px solid rgba(6,182,212,0.16);
    }
    .hero-strip img {
      width: 180px;
      height: 120px;
      object-fit: cover;
      border-radius: 10px;
    }
    .header h1 {
      margin: 0;
      font-size: 2rem;
      font-weight: 800;
      color: #111827;
    }
    .meta-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 1rem;
      margin-bottom: 2rem;
      padding: 1rem;
      background: #f3f4f6;
      border-radius: 6px;
    }
    .meta-item {
      font-size: 0.95rem;
    }
    .meta-item strong {
      color: #111827;
    }
    .day-block {
      margin-bottom: 2.5rem;
      page-break-inside: avoid;
    }
    .day-title {
      font-size: 1.25rem;
      font-weight: 700;
      color: #111827;
      border-bottom: 1px solid var(--clr-border);
      padding-bottom: 0.5rem;
      margin-bottom: 1rem;
    }
    .activity {
      margin-bottom: 1.5rem;
      padding-left: 1.5rem;
      border-left: 3px solid var(--clr-primary);
    }
    .activity-time {
      font-weight: 700;
      color: var(--clr-primary);
      font-size: 0.85rem;
    }
    .activity-title {
      font-size: 1.05rem;
      font-weight: 600;
      margin: 0.25rem 0;
    }
    .activity-desc {
      font-size: 0.9rem;
      color: #4b5563;
      margin: 0;
    }
    .activity-cost {
      font-size: 0.85rem;
      font-weight: 600;
      margin-top: 0.25rem;
    }
    .suggestions {
      margin-top: 3rem;
      page-break-inside: avoid;
    }
    .suggestion-item {
      padding: 1rem;
      border: 1px solid var(--clr-border);
      border-radius: 6px;
      margin-bottom: 1rem;
    }
    .no-print-bar {
      background: #374151;
      color: #fff;
      padding: 1rem;
      text-align: center;
      margin-bottom: 2rem;
      border-radius: 6px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .btn {
      background: var(--clr-primary);
      color: #fff;
      border: none;
      padding: 0.5rem 1.5rem;
      border-radius: 4px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
    }
    .btn-secondary {
      background: #4b5563;
    }
    @media print {
      body {
        background: #fff;
        padding: 0;
      }
      .print-container {
        border: none;
        box-shadow: none;
        padding: 0;
      }
      .no-print-bar {
        display: none;
      }
    }
  </style>
</head>
<body>

<div class="print-container">
  <div class="no-print-bar">
    <span>📄 Ready to print or save your itinerary.</span>
    <div>
      <button onclick="window.print()" class="btn">Print / Save as PDF</button>
      <a href="trip-planner.php" class="btn btn-secondary">Back to Planner</a>
    </div>
  </div>

  <div class="header">
    <div>
      <h1><?= e($plan['destination']) ?> Exploration Plan</h1>
      <div style="font-size: 0.9rem; color: var(--clr-muted); margin-top: 0.25rem;">Generated by <?= APP_NAME ?> AI Trip Planner</div>
    </div>
    <div style="font-size: 0.9rem; text-align: right;">
      Date: <?= date('d M Y', strtotime($plan['created_at'])) ?>
    </div>
  </div>

  <div class="hero-strip">
    <img src="<?= e($planHeroImage) ?>" alt="<?= e($plan['destination']) ?>">
    <div>
      <div style="font-size:0.72rem;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:var(--clr-primary);">Malawi Travel Snapshot</div>
      <div style="font-size:1.15rem;font-weight:700;margin-top:0.25rem;"><?= e($plan['destination']) ?></div>
      <div style="font-size:0.9rem;color:var(--clr-muted);">Quick printable overview with destination imagery, budget, and booking suggestions.</div>
    </div>
  </div>

  <div class="meta-grid">
    <div class="meta-item">Destination: <strong><?= e($plan['destination']) ?></strong></div>
    <div class="meta-item">Duration: <strong><?= e($plan['days']) ?> Days</strong></div>
    <div class="meta-item">Target Budget: <strong>MK <?= number_format((float)$plan['budget_mk']) ?></strong></div>
    <div class="meta-item">Estimated Cost: <strong>MK <?= number_format((float)$estimatedCost) ?></strong></div>
  </div>

  <div>
    <?php foreach ($itinerary as $dayPlan): ?>
      <div class="day-block">
        <div class="day-title"><?= e($dayPlan['theme']) ?></div>
        <?php foreach ($dayPlan['activities'] as $act): ?>
          <div class="activity">
            <span class="activity-time"><?= e($act['time']) ?></span>
            <div class="activity-title"><?= e($act['title']) ?></div>
            <p class="activity-desc"><?= e($act['description']) ?></p>
            <?php if (isset($act['cost']) && (float)$act['cost'] > 0): ?>
              <div class="activity-cost">Est. Cost: MK <?= number_format((float)$act['cost']) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (!empty($suggestions)): ?>
    <div class="suggestions">
      <h2 style="font-size: 1.5rem; border-bottom: 2px solid var(--clr-text); padding-bottom: 0.5rem;">Recommended Bookings on Uthenga</h2>
      <?php foreach ($suggestions as $s): ?>
        <div class="suggestion-item">
          <strong style="color: var(--clr-primary); text-transform: uppercase; font-size: 0.75rem;"><?= e($s['type']) ?></strong>
          <div style="font-size: 1.1rem; font-weight: 700; margin: 0.25rem 0;"><?= e($s['title']) ?></div>
          <div style="font-size: 0.9rem; color: var(--clr-muted);">Location: <?= e($s['location']) ?></div>
          <div style="font-size: 1rem; font-weight: 700; margin-top: 0.5rem;">Price: MK <?= number_format((float)$s['price']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div style="margin-top: 4rem; text-align: center; border-top: 1px solid var(--clr-border); padding-top: 1.5rem; font-size: 0.85rem; color: var(--clr-muted);">
    Thank you for choosing <?= APP_NAME ?> — the premier travel and tourism marketplace for Malawi.
  </div>
</div>

</body>
</html>
