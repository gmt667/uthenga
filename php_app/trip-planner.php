<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'AI Trip Planner';
$activeNav = 'trip-planner';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= APP_VERSION ?>">
  <style>
    .timeline { position: relative; margin: 2rem 0; padding-left: 2rem; border-left: 2px dashed var(--clr-border); }
    .timeline-item { position: relative; margin-bottom: 2rem; }
    .timeline-dot { position: absolute; left: -2.4rem; top: 0.25rem; width: 14px; height: 14px; border-radius: 50%; background: var(--clr-accent); border: 3px solid var(--clr-page-bg); }
    .timeline-time { font-size: 0.8rem; font-weight: 700; color: var(--clr-cyan); margin-bottom: 0.25rem; }
    .suggestion-card { display: flex; gap: 1rem; padding: 1rem; background: var(--clr-surface2); border: 1px solid var(--clr-border); border-radius: var(--radius-md); margin-top: 1rem; align-items: center; }
    .suggestion-img { width: 80px; height: 80px; object-fit: cover; border-radius: var(--radius-sm); }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<main class="container" style="padding-top: 3rem; padding-bottom: 5rem; max-width: 960px;">
  <div style="text-align: center; margin-bottom: 3rem;">
    <span class="section-label">AI ASSISTANT</span>
    <h1 class="page-title">✨ Intelligent Trip Planner</h1>
    <p class="text-muted" style="max-width: 600px; margin: 0.5rem auto 0;">
      Input your preferred days, budget, and locations in Malawi. Our AI scans the Uthenga marketplace to compile your custom itinerary, including transport, stays, and food stops.
    </p>
  </div>

  <div class="glass-panel" style="padding: 2rem; margin-bottom: 3rem;">
    <form id="trip-planner-form">
      <div class="form-group" style="margin-bottom: 1.5rem;">
        <label class="form-label" for="prompt" style="font-weight: 600; font-size: 1.05rem; display: block; margin-bottom: 0.75rem;">Describe your perfect Malawi getaway:</label>
        <textarea 
          name="query" 
          id="prompt" 
          class="form-control" 
          rows="3" 
          required 
          placeholder='e.g., "Plan me a 5-day trip around Lake Malawi with a budget of MK800,000."'
          style="width: 100%; border-radius: var(--radius-md);"
        ></textarea>
      </div>
      <button type="submit" id="generate-btn" class="btn btn-cyan btn-lg" style="width: 100%;">
        <span id="btn-text">Generate Itinerary</span>
        <span id="btn-spinner" style="display: none;">Generating plan... Please wait...</span>
      </button>
    </form>
  </div>

  <div id="planner-alert" class="alert" style="display:none; margin-bottom: 1.5rem;"></div>

  <!-- Loading Animation -->
  <div id="planner-loading" style="display: none; text-align: center; padding: 4rem 0;">
    <div style="font-size: 2.5rem; animation: spin 2s linear infinite;">⏳</div>
    <h3 style="margin-top: 1rem;">Analyzing Marketplace Inventory</h3>
    <p class="text-muted">Fetching matching accommodations, transport routes, and tours...</p>
  </div>

  <!-- Results Panel -->
  <div id="planner-results" style="display: none;">
    <div class="grid grid-cols-3 gap-4">
      
      <!-- Itinerary Panel -->
      <div style="grid-column: span 2;">
        <div class="card" style="padding: 2rem;">
          <h2 id="plan-title" style="font-size: 1.6rem; margin-bottom: 1.5rem;">Your Custom Itinerary</h2>
          
          <div id="itinerary-days">
            <!-- Day content injected here -->
          </div>
        </div>
      </div>

      <!-- Cost Summary & Booking Suggestions -->
      <div>
        <div class="card" style="padding: 1.5rem; margin-bottom: 1.5rem; background: var(--clr-surface2);">
          <h3>Trip Overview</h3>
          <hr style="margin: 0.75rem 0; border: 0; border-top: 1px solid var(--clr-border);">
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
            <span class="text-muted">Destination:</span>
            <strong id="summary-dest">-</strong>
          </div>
          <div id="summary-district-row" style="display: none; justify-content: space-between; margin-bottom: 0.5rem;">
            <span class="text-muted">District:</span>
            <strong id="summary-district">-</strong>
          </div>
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
            <span class="text-muted">Duration:</span>
            <strong id="summary-duration">-</strong>
          </div>
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
            <span class="text-muted">Target Budget:</span>
            <strong id="summary-budget">-</strong>
          </div>
          <div id="weather-summary-row" style="display: none; justify-content: space-between; margin-bottom: 0.5rem; align-items: center; background: rgba(0,0,0,0.05); padding: 0.5rem; border-radius: 4px;">
            <span class="text-muted">Weather:</span>
            <span id="summary-weather" style="font-weight: bold;">-</span>
          </div>
          <hr style="margin: 0.75rem 0; border: 0; border-top: 1px solid var(--clr-border);">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <strong>Est. Cost:</strong>
            <strong id="summary-est-cost" style="font-size: 1.25rem; color: var(--clr-accent);">-</strong>
          </div>
          <a id="download-itinerary-btn" href="#" target="_blank" class="btn btn-cyan" style="width: 100%; display: block; text-align: center;">📄 Save / Print Itinerary</a>
        </div>

        <div class="card" style="padding: 1.5rem;">
          <h3 style="margin-bottom: 1rem;">Direct Bookings</h3>
          <p class="text-muted" style="font-size: 0.8rem; margin-bottom: 1rem;">Reserve these actual listings on Uthenga to match your plan:</p>
          <div id="booking-suggestions">
            <!-- Suggestions injected here -->
          </div>
        </div>
      </div>

    </div>
  </div>
</main>

<script>
document.getElementById('trip-planner-form').addEventListener('submit', function(e) {
  e.preventDefault();
  
  var btnText = document.getElementById('btn-text');
  var btnSpinner = document.getElementById('btn-spinner');
  var generateBtn = document.getElementById('generate-btn');
  var loadingDiv = document.getElementById('planner-loading');
  var resultsDiv = document.getElementById('planner-results');

  btnText.style.display = 'none';
  btnSpinner.style.display = 'inline';
  generateBtn.disabled = true;
  loadingDiv.style.display = 'block';
  resultsDiv.style.display = 'none';

  var queryVal = document.getElementById('prompt').value;
  var alertBox = document.getElementById('planner-alert');
  alertBox.style.display = 'none';

  var formData = new FormData();
  formData.append('query', queryVal);

  fetch('<?= BASE_URL ?>api/trip_planner.php', {
    method: 'POST',
    body: formData
  })
  .then(async function(r) {
    var data = await r.json().catch(function() { return null; });
    if (!r.ok || !data) {
      throw new Error((data && data.message) ? data.message : 'Trip planner request failed.');
    }
    return data;
  })
  .then(data => {
    btnText.style.display = 'inline';
    btnSpinner.style.display = 'none';
    generateBtn.disabled = false;
    loadingDiv.style.display = 'none';

    if (data.success) {
      resultsDiv.style.display = 'block';
      alertBox.style.display = 'none';
      
      // Update overview summary card
      document.getElementById('summary-dest').textContent = data.destination || 'Malawi';
      var districtRow = document.getElementById('summary-district-row');
      var districtValue = document.getElementById('summary-district');
      if (data.district) {
        districtValue.textContent = data.district;
        districtRow.style.display = 'flex';
      } else {
        districtRow.style.display = 'none';
      }
      var tripDays = Number(data.days || 0);
      var tripBudget = Number(data.budget || 0);
      var estCost = Number(data.estimated_cost || 0);
      document.getElementById('summary-duration').textContent = (tripDays > 0 ? tripDays : 1) + ' Days';
      document.getElementById('summary-budget').textContent = 'MK ' + tripBudget.toLocaleString();
      document.getElementById('summary-est-cost').textContent = 'MK ' + estCost.toLocaleString();

      // Fetch Weather
      var weatherRow = document.getElementById('weather-summary-row');
      var weatherSpan = document.getElementById('summary-weather');
      weatherRow.style.display = 'none';

      fetch('<?= BASE_URL ?>api/weather.php?city=' + encodeURIComponent(data.destination || ''))
        .then(r => r.json())
        .then(wData => {
          if (wData.success && wData.weather) {
            var temp = Math.round(wData.weather.temperature);
            var weatherIcon = '🌡️';
            var wCodes = {0:'☀️',1:'🌤',2:'⛅',3:'☁️',45:'🌫',48:'🌫',51:'🌦',53:'🌧',55:'🌧',61:'🌧',63:'🌧',65:'🌧',80:'🌦',81:'🌧',82:'⛈',95:'⛈'};
            if (wCodes[wData.weather.weathercode]) {
              weatherIcon = wCodes[wData.weather.weathercode];
            }
            weatherSpan.textContent = weatherIcon + ' ' + temp + '°C';
            weatherRow.style.display = 'flex';
          }
        }).catch(err => console.log('Weather fetch failed'));

      // Set Download Link
      var downloadBtn = document.getElementById('download-itinerary-btn');
      downloadBtn.href = '<?= BASE_URL ?>print-itinerary.php?id=' + data.id;

      // Render Itinerary Day-by-Day
      var itineraryContainer = document.getElementById('itinerary-days');
      itineraryContainer.innerHTML = '';

      var itinerary = Array.isArray(data.itinerary) ? data.itinerary : [];
      if (itinerary.length === 0) {
        itineraryContainer.innerHTML = '<p class="text-muted">No itinerary could be generated for that request.</p>';
      }
      itinerary.forEach(dayPlan => {
        var dayHtml = `
          <div style="margin-bottom: 2.5rem;">
            <h4 style="font-size: 1.25rem; font-weight: 700; color: var(--clr-text); display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
              <span>📅</span> ${dayPlan.theme}
            </h4>
            <div class="timeline">
        `;

        dayPlan.activities.forEach(act => {
          dayHtml += `
            <div class="timeline-item">
              <div class="timeline-dot"></div>
              <div class="timeline-time">${act.time}</div>
              <strong style="display: block; font-size: 1.05rem; margin-bottom: 0.25rem;">${act.title}</strong>
              <p style="font-size: 0.9rem; margin-bottom: 0.5rem;">${act.description}</p>
              ${act.cost_text ? `<div style="font-size: 0.8rem; color: var(--clr-text-soft);">${act.cost_text}</div>` : ''}
              ${act.cost > 0 ? `<div style="font-size: 0.8rem; color: var(--clr-text-soft);">Est. Cost: <span style="font-weight: bold; color: var(--clr-text);">${act.cost > 0 ? 'MK ' + act.cost.toLocaleString() : 'Free'}</span></div>` : ''}
              ${act.booking_url ? `<a href="${act.booking_url}" class="btn btn-secondary btn-sm" style="margin-top: 0.5rem; padding: 0.25rem 0.75rem; font-size: 0.75rem;">Book this on Uthenga</a>` : ''}
            </div>
          `;
        });

        dayHtml += `
            </div>
          </div>
        `;
        itineraryContainer.innerHTML += dayHtml;
      });

      // Render Booking Suggestions
      var suggestionsContainer = document.getElementById('booking-suggestions');
      suggestionsContainer.innerHTML = '';
      var suggestions = Array.isArray(data.suggestions) ? data.suggestions : [];

      if (suggestions.length === 0) {
        suggestionsContainer.innerHTML = '<p class="text-muted" style="font-size: 0.85rem;">No matching listings found.</p>';
      } else {
        suggestions.forEach(s => {
          suggestionsContainer.innerHTML += `
            <div class="suggestion-card">
              <img src="${s.image}" alt="${s.title}" class="suggestion-img">
              <div style="flex: 1; min-width: 0;">
                <div style="font-size: 0.7rem; text-transform: uppercase; font-weight: bold; color: var(--clr-accent);">${s.type}</div>
                <strong style="display: block; font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${s.title}</strong>
                <span style="font-size: 0.8rem; color: var(--clr-text-soft);">${s.location}</span>
                <div style="font-size: 0.85rem; font-weight: bold; margin-top: 0.25rem; color: var(--clr-text);">${s.price_label ? s.price_label : 'MK ' + s.price.toLocaleString()}</div>
              </div>
              <a href="${s.url}" class="btn btn-primary btn-sm" style="padding: 0.4rem;">Book</a>
            </div>
          `;
        });
      }
    } else {
      alertBox.textContent = data.message || 'Trip planner could not generate a plan right now.';
      alertBox.className = 'alert alert-error';
      alertBox.style.display = 'block';
    }
  })
  .catch(err => {
    btnText.style.display = 'inline';
    btnSpinner.style.display = 'none';
    generateBtn.disabled = false;
    loadingDiv.style.display = 'none';
    alertBox.textContent = err.message || 'Failed to connect to trip planner API.';
    alertBox.className = 'alert alert-error';
    alertBox.style.display = 'block';
  });
});
</script>
</body>
</html>
