<?php
/**
 * Uthenga — AI Travel Assistant Chat Page
 */
$pageTitle = 'AI Travel Assistant — Amai';
$activeNav = 'ai-chat';

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// Soft-guard: include auth_check only if it exists (prevents crashes on minimal installs)
$__chatAuthPath = __DIR__ . '/../includes/auth_check.php';
if (file_exists($__chatAuthPath)) {
    require_once $__chatAuthPath;
}

// Always define $aiConfigured at file scope
$aiConfigured = defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Chat with Amai, your AI travel assistant powered by Uthenga. Get personalised recommendations, budget planning, and trip itineraries for Malawi.">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <title>Amai - AI Travel Assistant | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <style>
    .ai-page { display:grid; grid-template-columns:300px 1fr; gap:0; height:calc(100vh - 72px); background:var(--clr-bg); }
    .ai-sidebar { background:var(--clr-surface); border-right:1px solid var(--clr-border); display:flex; flex-direction:column; overflow:hidden; }
    .ai-sidebar-header { padding:1.5rem; border-bottom:1px solid var(--clr-border); }
    .ai-sidebar-header h2 { font-size:1rem; font-weight:700; margin:0 0 .25rem; }
    .ai-sidebar-header p  { font-size:.78rem; color:var(--clr-text-soft); margin:0; }
    .ai-quick-actions { padding:1rem; border-bottom:1px solid var(--clr-border); }
    .ai-qa-label { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--clr-text-soft); margin-bottom:.6rem; }
    .ai-qa-btn { display:block; width:100%; text-align:left; padding:.5rem .75rem; background:var(--clr-surface2); border:1px solid var(--clr-border); border-radius:var(--radius-md); font-size:.8rem; cursor:pointer; margin-bottom:.4rem; transition:background .2s; color:var(--clr-text); }
    .ai-qa-btn:hover { background:rgba(6,182,212,.1); border-color:var(--clr-primary); }
    .ai-tools { padding:1rem; flex:1; overflow-y:auto; }
    .ai-tool-card { background:var(--clr-surface2); border:1px solid var(--clr-border); border-radius:var(--radius-md); padding:.75rem; margin-bottom:.75rem; cursor:pointer; transition:border-color .2s; }
    .ai-tool-card:hover { border-color:var(--clr-primary); }
    .ai-tool-card h4 { font-size:.82rem; font-weight:700; margin:0 0 .25rem; }
    .ai-tool-card p  { font-size:.73rem; color:var(--clr-text-soft); margin:0; }

    .ai-main { display:flex; flex-direction:column; overflow:hidden; }
    .ai-chat-header { padding:1rem 1.5rem; border-bottom:1px solid var(--clr-border); display:flex; align-items:center; gap:.75rem; background:var(--clr-surface); }
    .ai-avatar { width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg,var(--clr-primary),var(--clr-accent)); display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
    .ai-messages { flex:1; overflow-y:auto; padding:1.5rem; display:flex; flex-direction:column; gap:1rem; }
    .msg { display:flex; gap:.75rem; max-width:80%; }
    .msg.user { align-self:flex-end; flex-direction:row-reverse; }
    .msg-bubble { padding:.75rem 1rem; border-radius:var(--radius-lg); font-size:.88rem; line-height:1.55; }
    .msg.ai   .msg-bubble { background:var(--clr-surface); border:1px solid var(--clr-border); border-radius:0 var(--radius-lg) var(--radius-lg) var(--radius-lg); color:var(--clr-text); }
    .msg.user .msg-bubble { background:var(--clr-primary); color:#fff; border-radius:var(--radius-lg) 0 var(--radius-lg) var(--radius-lg); }
    .msg-avatar { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.9rem; flex-shrink:0; }
    .msg.ai   .msg-avatar { background:linear-gradient(135deg,var(--clr-primary),var(--clr-accent)); }
    .msg.user .msg-avatar { background:var(--clr-surface2); }
    .typing-dots span { display:inline-block; width:6px; height:6px; background:var(--clr-primary); border-radius:50%; margin:0 2px; animation:typingBounce 1.2s infinite; }
    .typing-dots span:nth-child(2) { animation-delay:.2s; }
    .typing-dots span:nth-child(3) { animation-delay:.4s; }
    @keyframes typingBounce { 0%,80%,100%{transform:translateY(0)} 40%{transform:translateY(-6px)} }
    .suggestions-row { display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.5rem; }
    .suggestion-chip { padding:.25rem .65rem; background:rgba(6,182,212,.1); border:1px solid rgba(6,182,212,.3); border-radius:100px; font-size:.73rem; color:var(--clr-primary); cursor:pointer; transition:background .2s; }
    .suggestion-chip:hover { background:rgba(6,182,212,.2); }

    .ai-input-area { padding:1rem 1.5rem; border-top:1px solid var(--clr-border); background:var(--clr-surface); }
    .ai-input-row { display:flex; gap:.6rem; align-items:flex-end; }
    .ai-textarea { flex:1; background:var(--clr-surface2); border:1px solid var(--clr-border); border-radius:var(--radius-md); padding:.65rem .9rem; color:var(--clr-text); font-size:.88rem; font-family:inherit; resize:none; min-height:42px; max-height:120px; transition:border-color .2s; }
    .ai-textarea:focus { outline:none; border-color:var(--clr-primary); }
    .ai-send-btn { width:42px; height:42px; border-radius:50%; background:var(--clr-primary); border:none; color:#fff; font-size:1.1rem; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background .2s; flex-shrink:0; }
    .ai-send-btn:hover { background:var(--clr-accent); }
    .ai-send-btn:disabled { opacity:.5; cursor:default; }

    /* Budget tool modal */
    .budget-tool { background:var(--clr-surface); border:1px solid var(--clr-border); border-radius:var(--radius-lg); padding:1.5rem; margin-top:1rem; }
    .budget-row { display:flex; justify-content:space-between; align-items:center; padding:.4rem 0; border-bottom:1px solid var(--clr-border); font-size:.85rem; }
    .budget-row:last-child { border-bottom:none; font-weight:700; font-size:1rem; color:var(--clr-accent); }

    @media(max-width:768px) {
      .ai-page { grid-template-columns:1fr; }
      .ai-sidebar { display:none; }
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="ai-page">

  <!-- â”€â”€ Left Sidebar â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
  <aside class="ai-sidebar">
    <div class="ai-sidebar-header">
      <h2>ðŸ¤– Amai</h2>
      <p>Your AI travel assistant for Malawi</p>
    </div>

    <div class="ai-quick-actions">
      <div class="ai-qa-label">Quick questions</div>
      <button class="ai-qa-btn" onclick="sendQuick('Plan a 3-day trip to Mangochi')">ðŸŒŠ 3 days in Mangochi</button>
      <button class="ai-qa-btn" onclick="sendQuick('What are the top attractions in Lilongwe?')">ðŸ›ï¸ Top attractions in Lilongwe</button>
      <button class="ai-qa-btn" onclick="sendQuick('Show me upcoming events on Uthenga')">ðŸŽ« Upcoming events</button>
      <button class="ai-qa-btn" onclick="sendQuick('What\\'s the weather like in Blantyre?')">â˜€ï¸ Weather in Blantyre</button>
      <button class="ai-qa-btn" onclick="sendQuick('How much does a trip to Lake Malawi cost?')">ðŸ’° Lake Malawi budget</button>
    </div>

    <div class="ai-tools">
      <div class="ai-qa-label">AI Tools</div>
      <div class="ai-tool-card" onclick="openBudgetTool()">
        <h4>ðŸ’° Budget Planner</h4>
        <p>Estimate trip costs with itemised breakdown</p>
      </div>
      <div class="ai-tool-card" onclick="openItineraryTool()">
        <h4>ðŸ“… Itinerary Generator</h4>
        <p>Get a day-by-day trip plan for any destination</p>
      </div>
      <div class="ai-tool-card" onclick="sendQuick('Recommend the best accommodation options for me')">
        <h4>ðŸ¨ Smart Recommendations</h4>
        <p>Personalised listings based on your preferences</p>
      </div>
      <a href="<?= BASE_URL ?>trip-planner.php" class="ai-tool-card" style="text-decoration:none;display:block;">
        <h4>âœˆï¸ Trip Planner</h4>
        <p>Full trip planner with PDF itinerary download</p>
      </a>
    </div>
  </aside>

  <!-- â”€â”€ Main Chat â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
  <div class="ai-main">
    <div class="ai-chat-header">
      <div class="ai-avatar">ðŸ¤–</div>
      <div>
        <div style="font-weight:700;">Amai - AI Travel Assistant</div>
        <div class="text-xs text-muted" id="ai-status"><?= $aiConfigured ? 'Online Â· AI service connected' : 'Local fallback Â· AI service unavailable' ?></div>
      </div>
      <button onclick="clearChat()" class="btn btn-secondary btn-sm" style="margin-left:auto;">Clear Chat</button>
    </div>

    <div class="ai-messages" id="ai-messages">
      <!-- Welcome message -->
      <div class="msg ai" id="welcome-msg">
        <div class="msg-avatar">ðŸ¤–</div>
        <div>
          <div class="msg-bubble">
            ðŸ‘‹ <strong>Moni!</strong> I'm <strong>Amai</strong>, your AI travel assistant for Malawi! ðŸ‡²ðŸ‡¼<br><br>
            I can help you:
            <ul style="margin:.5rem 0 0 1rem;padding:0;list-style:disc;">
              <li>ðŸ¨ Find hotels, lodges & accommodation</li>
              <li>ðŸŽ« Discover events, concerts & festivals</li>
              <li>ðŸŒ Plan tours and day trips</li>
              <li>ðŸ’° Estimate your travel budget</li>
              <li>ðŸ“… Build a personalised day-by-day itinerary</li>
            </ul>
            <br>What would you like to explore today?
          </div>
          <div class="suggestions-row">
            <span class="suggestion-chip" onclick="sendQuick(this.textContent)">ðŸ—ºï¸ Plan a trip</span>
            <span class="suggestion-chip" onclick="sendQuick(this.textContent)">ðŸŽ« Find events</span>
            <span class="suggestion-chip" onclick="sendQuick(this.textContent)">ðŸ¨ Find hotels</span>
            <span class="suggestion-chip" onclick="sendQuick(this.textContent)">ðŸ’° Estimate budget</span>
          </div>
        </div>
      </div>
    </div>

    <div class="ai-input-area">
      <div class="ai-input-row">
        <textarea
          id="ai-input"
          class="ai-textarea"
          placeholder="Ask about trips, hotels, events, budgetsâ€¦"
          rows="1"
          onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMessage();}"
        ></textarea>
        <button class="ai-send-btn" id="ai-send-btn" onclick="sendMessage()" title="Send message">âž¤</button>
      </div>
      <div class="text-xs text-muted" style="margin-top:.4rem;text-align:center;">
        Amai may make mistakes. Verify important details before booking.
      </div>
    </div>
  </div>
</div>

<!-- Budget Tool Panel (injected into chat) -->
<template id="budget-tool-tpl">
  <div class="budget-tool">
    <h4 style="margin:0 0 1rem;">ðŸ’° Budget Planner</h4>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1rem;">
      <div class="form-group" style="margin:0;">
        <label class="form-label" for="bt-dest">Destination</label>
        <input type="text" id="bt-dest" class="form-control" value="Lilongwe" placeholder="e.g. Mangochi">
      </div>
      <div class="form-group" style="margin:0;">
        <label class="form-label" for="bt-days">Days</label>
        <input type="number" id="bt-days" class="form-control" value="3" min="1" max="30">
      </div>
      <div class="form-group" style="margin:0;">
        <label class="form-label" for="bt-pax">Travellers</label>
        <input type="number" id="bt-pax" class="form-control" value="2" min="1" max="10">
      </div>
      <div class="form-group" style="margin:0;">
        <label class="form-label" for="bt-style">Style</label>
        <select id="bt-style" class="form-control">
          <option value="budget">Budget</option>
          <option value="mid-range" selected>Mid-range</option>
          <option value="luxury">Luxury</option>
        </select>
      </div>
    </div>
    <button class="btn btn-primary btn-sm" onclick="runBudget()">Calculate Budget</button>
    <div id="bt-result" style="margin-top:1rem;"></div>
  </div>
</template>

<!-- Itinerary Tool Panel -->
<template id="itinerary-tool-tpl">
  <div class="budget-tool">
    <h4 style="margin:0 0 1rem;">ðŸ“… Itinerary Generator</h4>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1rem;">
      <div class="form-group" style="margin:0;">
        <label class="form-label" for="it-dest">Destination</label>
        <input type="text" id="it-dest" class="form-control" value="Lilongwe">
      </div>
      <div class="form-group" style="margin:0;">
        <label class="form-label" for="it-days">Days</label>
        <input type="number" id="it-days" class="form-control" value="3" min="1" max="14">
      </div>
    </div>
    <button class="btn btn-primary btn-sm" onclick="runItinerary()">Generate Itinerary</button>
    <div id="it-result" style="margin-top:1rem;"></div>
  </div>
</template>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
const BASE = document.querySelector('meta[name="base-url"]')?.content || '';
let chatHistory = [];

function scrollToBottom() {
  const el = document.getElementById('ai-messages');
  if (el) el.scrollTop = el.scrollHeight;
}

function appendMsg(role, html, suggestions = []) {
  const container = document.getElementById('ai-messages');
  const wrapper   = document.createElement('div');
  wrapper.className = 'msg ' + role;

  const emojiMap = { ai: 'ðŸ¤–', user: 'ðŸ‘¤' };
  let sugsHtml = '';
  if (suggestions.length > 0) {
    sugsHtml = '<div class="suggestions-row">' + suggestions.map(s =>
      `<span class="suggestion-chip" onclick="sendQuick(this.textContent)">${s}</span>`
    ).join('') + '</div>';
  }

  wrapper.innerHTML = `
    <div class="msg-avatar">${emojiMap[role]}</div>
    <div>
      <div class="msg-bubble">${html}</div>
      ${sugsHtml}
    </div>`;
  container.appendChild(wrapper);
  scrollToBottom();
  return wrapper;
}

function showTyping() {
  return appendMsg('ai', '<span class="typing-dots"><span></span><span></span><span></span></span>');
}

function escHtml(s) {
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function mdToHtml(text) {
  return text
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.*?)\*/g, '<em>$1</em>')
    .replace(/^- (.+)$/gm, '<li>$1</li>')
    .replace(/(<li>.*<\/li>)/s, '<ul style="margin:.5rem 0 0 1rem;padding:0;list-style:disc;">$1</ul>')
    .replace(/\n/g, '<br>');
}

async function sendMessage() {
  const input  = document.getElementById('ai-input');
  const sendBtn = document.getElementById('ai-send-btn');
  const text   = input.value.trim();
  if (!text) return;

  appendMsg('user', escHtml(text));
  chatHistory.push({ role: 'user', content: text });
  input.value = '';
  input.style.height = 'auto';
  sendBtn.disabled = true;

  const typingEl = showTyping();

  try {
    const res  = await fetch(BASE + 'api/ai/chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text, history: chatHistory.slice(-10) })
    });
    const data = await res.json();
    typingEl.remove();

    if (!res.ok || data.success === false) {
      throw new Error(data.message || 'AI service unavailable');
    }

    if (data.service_status && document.getElementById('ai-status')) {
      document.getElementById('ai-status').textContent = data.service_status === 'local_fallback' ? 'Local fallback Â· AI service unavailable' : 'Online Â· AI service connected';
    }

    const reply = data.reply || 'Sorry, I had trouble responding. Please try again.';
    chatHistory.push({ role: 'ai', content: reply });
    appendMsg('ai', mdToHtml(reply), data.suggestions || []);
  } catch (err) {
    typingEl.remove();
    appendMsg('ai', 'âš ï¸ AI service is currently unavailable. Please try again later or use the listings pages directly.');
  } finally {
    sendBtn.disabled = false;
    input.focus();
  }
}

function sendQuick(text) {
  const input = document.getElementById('ai-input');
  input.value = text;
  sendMessage();
}

function clearChat() {
  chatHistory = [];
  const container = document.getElementById('ai-messages');
  container.innerHTML = '';
  appendMsg('ai', 'ðŸ’¬ Chat cleared! How can I help you plan your Malawi adventure?');
}

// â”€â”€ Budget Tool â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function openBudgetTool() {
  const tpl   = document.getElementById('budget-tool-tpl');
  const clone = tpl.content.cloneNode(true);
  const wrapper = document.createElement('div');
  wrapper.className = 'msg ai';
  wrapper.innerHTML = '<div class="msg-avatar">ðŸ’°</div><div style="flex:1;"></div>';
  wrapper.querySelector('div:last-child').appendChild(clone);
  document.getElementById('ai-messages').appendChild(wrapper);
  scrollToBottom();
}

async function runBudget() {
  const dest     = document.getElementById('bt-dest').value.trim() || 'Lilongwe';
  const days     = document.getElementById('bt-days').value;
  const pax      = document.getElementById('bt-pax').value;
  const style    = document.getElementById('bt-style').value;
  const resultEl = document.getElementById('bt-result');
  resultEl.innerHTML = 'â³ Calculatingâ€¦';

  const res  = await fetch(BASE + 'api/ai/budget.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ destination: dest, days, travellers: pax, style })
  });
  const data = await res.json();

  if (!data.success) { resultEl.innerHTML = 'âŒ Error calculating budget.'; return; }

  let html = `<div style="font-size:.8rem;color:var(--clr-text-soft);margin-bottom:.5rem;">${pax} traveller(s) Â· ${days} day(s) Â· ${style} Â· ${dest}</div>`;
  data.items.forEach(item => {
    html += `<div class="budget-row"><span>${item.category}</span><span style="font-weight:600;">MK ${item.amount.toLocaleString()}</span></div>`;
  });
  html += `<div class="budget-row"><span>TOTAL</span><span>MK ${data.total.toLocaleString()}</span></div>`;
  html += `<div style="font-size:.75rem;color:var(--clr-text-soft);margin-top:.5rem;">Per person: MK ${data.per_person.toLocaleString()}</div>`;
  if (data.tips?.length) {
    html += `<div style="margin-top:.75rem;"><div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--clr-primary);margin-bottom:.35rem;">ðŸ’¡ Tips</div>`;
    data.tips.forEach(t => { html += `<div style="font-size:.78rem;color:var(--clr-text-soft);margin-bottom:.2rem;">â€¢ ${t}</div>`; });
    html += `</div>`;
  }
  resultEl.innerHTML = html;
}

// â”€â”€ Itinerary Tool â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function openItineraryTool() {
  const tpl   = document.getElementById('itinerary-tool-tpl');
  const clone = tpl.content.cloneNode(true);
  const wrapper = document.createElement('div');
  wrapper.className = 'msg ai';
  wrapper.innerHTML = '<div class="msg-avatar">ðŸ“…</div><div style="flex:1;"></div>';
  wrapper.querySelector('div:last-child').appendChild(clone);
  document.getElementById('ai-messages').appendChild(wrapper);
  scrollToBottom();
}

async function runItinerary() {
  const dest     = document.getElementById('it-dest').value.trim() || 'Lilongwe';
  const days     = document.getElementById('it-days').value;
  const resultEl = document.getElementById('it-result');
  resultEl.innerHTML = 'â³ Generating itineraryâ€¦';

  const res  = await fetch(BASE + 'api/ai/itinerary.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ destination: dest, days })
  });
  const data = await res.json();
  if (!data.success) { resultEl.innerHTML = 'âŒ Error generating itinerary.'; return; }

  let html = '';
  data.itinerary.forEach(day => {
    html += `<div style="margin-bottom:1rem;"><div style="font-weight:700;font-size:.9rem;margin-bottom:.4rem;color:var(--clr-primary);">Day ${day.day}</div>`;
    day.activities.forEach(act => {
      html += `<div style="padding:.4rem 0 .4rem .75rem;border-left:2px solid var(--clr-border);margin-bottom:.3rem;">
        <div style="font-size:.75rem;color:var(--clr-accent);font-weight:600;">${act.time}</div>
        <div style="font-size:.85rem;">${act.activity}</div>
        <div style="font-size:.72rem;color:var(--clr-text-soft);">${act.note}</div>
        ${act.link_id ? `<a href="${BASE}event-details.php?type=${act.link_type}&id=${act.link_id}" target="_blank" style="font-size:.72rem;color:var(--clr-primary);">Book on Uthenga â†’</a>` : ''}
      </div>`;
    });
    html += '</div>';
  });

  if (data.tips?.length) {
    html += `<div style="margin-top:.75rem;border-top:1px solid var(--clr-border);padding-top:.75rem;">
      <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--clr-primary);margin-bottom:.35rem;">ðŸ’¡ Travel Tips</div>`;
    data.tips.forEach(t => { html += `<div style="font-size:.78rem;color:var(--clr-text-soft);margin-bottom:.2rem;">â€¢ ${t}</div>`; });
    html += `</div>`;
  }
  resultEl.innerHTML = html;
  scrollToBottom();
}

// Auto-grow textarea
document.getElementById('ai-input')?.addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});
</script>
</body>
</html>


