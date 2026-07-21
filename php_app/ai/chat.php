<?php
/**
 * Uthenga - AI Travel Assistant Chat Page
 */
$pageTitle = 'AI Travel Assistant - Amai';
$activeNav = 'ai-chat';

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$__chatAuthPath = __DIR__ . '/../includes/auth_check.php';
if (file_exists($__chatAuthPath)) {
    require_once $__chatAuthPath;
}

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
    .ai-page{
      display:grid;
      grid-template-columns:300px minmax(0,1fr);
      min-height:calc(100vh - 72px);
      background:
        radial-gradient(circle at top right, rgba(14,165,233,.08), transparent 28%),
        radial-gradient(circle at bottom left, rgba(16,185,129,.08), transparent 24%),
        var(--clr-bg);
    }
    .ai-sidebar{
      background:var(--clr-surface);
      border-right:1px solid var(--clr-border);
      display:flex;
      flex-direction:column;
      overflow:hidden;
    }
    .ai-sidebar-header{
      display:flex;
      align-items:center;
      gap:.85rem;
      padding:1.25rem;
      border-bottom:1px solid var(--clr-border);
    }
    .ai-brand-badge,
    .ai-avatar,
    .msg-avatar,
    .tool-icon,
    .quick-icon{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      flex-shrink:0;
      font-weight:700;
      letter-spacing:.04em;
    }
    .ai-brand-badge{
      width:44px;
      height:44px;
      border-radius:14px;
      background:linear-gradient(135deg,var(--clr-primary),var(--clr-accent));
      color:#fff;
      box-shadow:0 12px 30px rgba(14,165,233,.22);
    }
    .ai-sidebar-header h2{
      font-size:1rem;
      font-weight:800;
      margin:0 0 .15rem;
    }
    .ai-sidebar-header p{
      font-size:.78rem;
      color:var(--clr-text-soft);
      margin:0;
    }
    .ai-quick-actions{
      padding:1rem 1.25rem;
      border-bottom:1px solid var(--clr-border);
    }
    .ai-qa-label{
      font-size:.7rem;
      font-weight:800;
      text-transform:uppercase;
      letter-spacing:.08em;
      color:var(--clr-text-soft);
      margin-bottom:.65rem;
    }
    .ai-qa-btn{
      display:flex;
      align-items:center;
      gap:.7rem;
      width:100%;
      text-align:left;
      padding:.7rem .85rem;
      margin-bottom:.5rem;
      border:1px solid var(--clr-border);
      border-radius:14px;
      background:var(--clr-surface2);
      color:var(--clr-text);
      font-size:.84rem;
      cursor:pointer;
      transition:transform .2s ease,border-color .2s ease,background .2s ease;
    }
    .ai-qa-btn:hover{
      transform:translateY(-1px);
      border-color:rgba(14,165,233,.32);
      background:rgba(14,165,233,.08);
    }
    .quick-icon,
    .tool-icon{
      width:28px;
      height:28px;
      border-radius:10px;
      background:rgba(14,165,233,.12);
      color:var(--clr-primary);
      font-size:.72rem;
    }
    .ai-tools{
      padding:1rem 1.25rem 1.25rem;
      flex:1;
      overflow-y:auto;
    }
    .ai-tool-card{
      display:block;
      background:var(--clr-surface2);
      border:1px solid var(--clr-border);
      border-radius:16px;
      padding:.9rem;
      margin-bottom:.75rem;
      cursor:pointer;
      color:inherit;
      text-decoration:none;
      transition:transform .2s ease,border-color .2s ease,background .2s ease;
    }
    .ai-tool-card:hover{
      transform:translateY(-1px);
      border-color:rgba(14,165,233,.32);
      background:rgba(14,165,233,.05);
    }
    .ai-tool-card h4{
      display:flex;
      align-items:center;
      gap:.55rem;
      font-size:.86rem;
      font-weight:800;
      margin:0 0 .25rem;
    }
    .ai-tool-card p{
      font-size:.74rem;
      color:var(--clr-text-soft);
      margin:0;
      line-height:1.5;
    }
    .ai-main{
      display:flex;
      flex-direction:column;
      min-width:0;
      overflow:hidden;
    }
    .ai-chat-header{
      display:flex;
      align-items:center;
      gap:.8rem;
      padding:1rem 1.25rem;
      border-bottom:1px solid var(--clr-border);
      background:var(--clr-surface);
      min-width:0;
    }
    .ai-avatar{
      width:42px;
      height:42px;
      border-radius:14px;
      background:linear-gradient(135deg,var(--clr-primary),var(--clr-accent));
      color:#fff;
      box-shadow:0 12px 30px rgba(14,165,233,.22);
      font-size:.84rem;
    }
    .ai-chat-meta{
      min-width:0;
    }
    .ai-chat-meta > div:first-child{
      font-weight:800;
      line-height:1.2;
    }
    .ai-messages{
      flex:1;
      overflow-y:auto;
      display:flex;
      flex-direction:column;
      gap:1rem;
      padding:1.25rem;
      min-width:0;
    }
    .msg{
      display:flex;
      gap:.75rem;
      max-width:min(80%,760px);
      min-width:0;
    }
    .msg.user{
      align-self:flex-end;
      flex-direction:row-reverse;
    }
    .msg-avatar{
      width:34px;
      height:34px;
      border-radius:12px;
      background:var(--clr-surface2);
      border:1px solid var(--clr-border);
      color:var(--clr-text);
      font-size:.72rem;
    }
    .msg.ai .msg-avatar{
      background:linear-gradient(135deg,var(--clr-primary),var(--clr-accent));
      border-color:transparent;
      color:#fff;
    }
    .msg-bubble{
      padding:.8rem 1rem;
      border-radius:18px;
      font-size:.9rem;
      line-height:1.6;
      word-wrap:break-word;
      overflow-wrap:anywhere;
    }
    .msg.ai .msg-bubble{
      background:var(--clr-surface);
      border:1px solid var(--clr-border);
      border-radius:0 18px 18px 18px;
      color:var(--clr-text);
      box-shadow:0 10px 22px rgba(15,23,42,.04);
    }
    .msg.user .msg-bubble{
      background:linear-gradient(135deg,var(--clr-primary),var(--clr-accent));
      color:#fff;
      border-radius:18px 0 18px 18px;
      box-shadow:0 10px 22px rgba(14,165,233,.18);
    }
    .typing-dots span{
      display:inline-block;
      width:6px;
      height:6px;
      margin:0 2px;
      border-radius:50%;
      background:var(--clr-primary);
      animation:typingBounce 1.2s infinite;
    }
    .typing-dots span:nth-child(2){animation-delay:.2s;}
    .typing-dots span:nth-child(3){animation-delay:.4s;}
    @keyframes typingBounce{
      0%,80%,100%{transform:translateY(0);}
      40%{transform:translateY(-6px);}
    }
    .suggestions-row{
      display:flex;
      flex-wrap:wrap;
      gap:.45rem;
      margin-top:.65rem;
    }
    .suggestion-chip{
      padding:.35rem .75rem;
      border-radius:999px;
      background:rgba(14,165,233,.08);
      border:1px solid rgba(14,165,233,.22);
      color:var(--clr-primary);
      font-size:.74rem;
      cursor:pointer;
      transition:transform .2s ease,background .2s ease;
    }
    .suggestion-chip:hover{
      transform:translateY(-1px);
      background:rgba(14,165,233,.16);
    }
    .ai-input-area{
      padding:1rem 1.25rem;
      border-top:1px solid var(--clr-border);
      background:var(--clr-surface);
    }
    .ai-input-row{
      display:flex;
      gap:.6rem;
      align-items:flex-end;
    }
    .ai-textarea{
      flex:1;
      min-height:46px;
      max-height:128px;
      resize:none;
      background:var(--clr-surface2);
      border:1px solid var(--clr-border);
      border-radius:14px;
      padding:.75rem .95rem;
      color:var(--clr-text);
      font-size:.92rem;
      font-family:inherit;
      transition:border-color .2s ease,box-shadow .2s ease;
    }
    .ai-textarea:focus{
      outline:none;
      border-color:var(--clr-primary);
      box-shadow:0 0 0 3px rgba(14,165,233,.12);
    }
    .ai-send-btn{
      min-width:72px;
      height:46px;
      padding:0 1rem;
      border:none;
      border-radius:14px;
      background:var(--clr-primary);
      color:#fff;
      font-size:.88rem;
      font-weight:700;
      cursor:pointer;
      transition:transform .2s ease,background .2s ease,opacity .2s ease;
      flex-shrink:0;
    }
    .ai-send-btn:hover{
      transform:translateY(-1px);
      background:var(--clr-accent);
    }
    .ai-send-btn:disabled{
      opacity:.55;
      cursor:default;
      transform:none;
    }
    .budget-tool{
      background:var(--clr-surface);
      border:1px solid var(--clr-border);
      border-radius:18px;
      padding:1.1rem;
      margin-top:1rem;
      box-shadow:0 12px 26px rgba(15,23,42,.05);
    }
    .budget-row{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:.75rem;
      padding:.45rem 0;
      border-bottom:1px solid var(--clr-border);
      font-size:.86rem;
    }
    .budget-row:last-child{
      border-bottom:none;
      font-weight:800;
      font-size:1rem;
      color:var(--clr-accent);
    }
    @media (max-width:1024px){
      .ai-page{grid-template-columns:270px minmax(0,1fr);}
      .msg{max-width:88%;}
    }
    @media (max-width:768px){
      .ai-page{
        grid-template-columns:1fr;
        min-height:auto;
      }
      .ai-sidebar{
        border-right:none;
        border-bottom:1px solid var(--clr-border);
      }
      .ai-sidebar-header,
      .ai-quick-actions,
      .ai-tools,
      .ai-chat-header,
      .ai-input-area,
      .ai-messages{
        padding-left:1rem;
        padding-right:1rem;
      }
      .ai-messages{
        padding-top:1rem;
        padding-bottom:1rem;
      }
      .msg{max-width:100%;}
      .ai-input-row{align-items:stretch;}
      .ai-send-btn{min-width:64px;}
    }
    @media (max-width:480px){
      .ai-brand-badge,
      .ai-avatar{
        width:40px;
        height:40px;
        border-radius:12px;
      }
      .ai-chat-header{
        align-items:flex-start;
      }
      .ai-input-row{
        flex-direction:column;
      }
      .ai-send-btn{
        width:100%;
      }
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="ai-page">
  <aside class="ai-sidebar">
    <div class="ai-sidebar-header">
      <div class="ai-brand-badge">AI</div>
      <div>
        <h2>Amai</h2>
        <p>AI travel assistant for Malawi</p>
      </div>
    </div>

    <div class="ai-quick-actions">
      <div class="ai-qa-label">Quick questions</div>
      <button class="ai-qa-btn" onclick="sendQuick('Plan a 3-day trip to Mangochi')"><span class="quick-icon">3D</span>3 days in Mangochi</button>
      <button class="ai-qa-btn" onclick="sendQuick('What are the top attractions in Lilongwe?')"><span class="quick-icon">LT</span>Top attractions in Lilongwe</button>
      <button class="ai-qa-btn" onclick="sendQuick('Show me upcoming events on Uthenga')"><span class="quick-icon">EV</span>Upcoming events</button>
      <button class="ai-qa-btn" onclick="sendQuick('What\\'s the weather like in Blantyre?')"><span class="quick-icon">WE</span>Weather in Blantyre</button>
      <button class="ai-qa-btn" onclick="sendQuick('How much does a trip to Lake Malawi cost?')"><span class="quick-icon">MK</span>Lake Malawi budget</button>
    </div>

    <div class="ai-tools">
      <div class="ai-qa-label">AI tools</div>
      <div class="ai-tool-card" onclick="openBudgetTool()">
        <h4><span class="tool-icon">B</span>Budget Planner</h4>
        <p>Estimate trip costs with itemised breakdown.</p>
      </div>
      <div class="ai-tool-card" onclick="openItineraryTool()">
        <h4><span class="tool-icon">I</span>Itinerary Generator</h4>
        <p>Get a day-by-day trip plan for any destination.</p>
      </div>
      <div class="ai-tool-card" onclick="sendQuick('Recommend the best accommodation options for me')">
        <h4><span class="tool-icon">R</span>Smart Recommendations</h4>
        <p>Personalised listings based on your preferences.</p>
      </div>
      <a href="<?= BASE_URL ?>trip-planner.php" class="ai-tool-card">
        <h4><span class="tool-icon">T</span>Trip Planner</h4>
        <p>Full trip planner with PDF itinerary download.</p>
      </a>
    </div>
  </aside>

  <main class="ai-main">
    <div class="ai-chat-header">
      <div class="ai-avatar">AI</div>
      <div class="ai-chat-meta">
        <div>Amai - AI Travel Assistant</div>
        <div class="text-xs text-muted" id="ai-status"><?= $aiConfigured ? 'Online - AI service connected' : 'Local fallback - AI service unavailable' ?></div>
      </div>
      <button onclick="clearChat()" class="btn btn-secondary btn-sm" style="margin-left:auto;">Clear Chat</button>
    </div>

    <div class="ai-messages" id="ai-messages">
      <div class="msg ai" id="welcome-msg">
        <div class="msg-avatar">AI</div>
        <div>
          <div class="msg-bubble">
            Hello. I am <strong>Amai</strong>, your AI travel assistant for Malawi.<br><br>
            I can help you with:
            <ul style="margin:.55rem 0 0 1rem;padding:0;list-style:disc;">
              <li>Find hotels, lodges, and accommodation</li>
              <li>Discover events, concerts, and festivals</li>
              <li>Plan tours and day trips</li>
              <li>Estimate your travel budget</li>
              <li>Build a personalised day-by-day itinerary</li>
            </ul>
            <br>What would you like to explore today?
          </div>
          <div class="suggestions-row">
            <span class="suggestion-chip" onclick="sendQuick(this.textContent)">Plan a trip</span>
            <span class="suggestion-chip" onclick="sendQuick(this.textContent)">Find events</span>
            <span class="suggestion-chip" onclick="sendQuick(this.textContent)">Find hotels</span>
            <span class="suggestion-chip" onclick="sendQuick(this.textContent)">Estimate budget</span>
          </div>
        </div>
      </div>
    </div>

    <div class="ai-input-area">
      <div class="ai-input-row">
        <textarea
          id="ai-input"
          class="ai-textarea"
          placeholder="Ask about trips, hotels, events, or budgets..."
          rows="1"
          onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMessage();}"
        ></textarea>
        <button class="ai-send-btn" id="ai-send-btn" onclick="sendMessage()" title="Send message">Send</button>
      </div>
      <div class="text-xs text-muted" style="margin-top:.4rem;text-align:center;">
        Amai may make mistakes. Verify important details before booking.
      </div>
    </div>
  </main>
</div>

<template id="budget-tool-tpl">
  <div class="budget-tool">
    <h4 style="margin:0 0 1rem;display:flex;align-items:center;gap:.5rem;"><span class="tool-icon">B</span>Budget Planner</h4>
    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem;margin-bottom:1rem;">
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

<template id="itinerary-tool-tpl">
  <div class="budget-tool">
    <h4 style="margin:0 0 1rem;display:flex;align-items:center;gap:.5rem;"><span class="tool-icon">I</span>Itinerary Generator</h4>
    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem;margin-bottom:1rem;">
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

function escHtml(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function mdToHtml(text) {
  const safe = escHtml(text);
  return safe
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.*?)\*/g, '<em>$1</em>')
    .replace(/^- (.+)$/gm, '<li>$1</li>')
    .replace(/(<li>.*<\/li>)/s, '<ul style="margin:.5rem 0 0 1rem;padding:0;list-style:disc;">$1</ul>')
    .replace(/\n/g, '<br>');
}

function appendMsg(role, html, suggestions = []) {
  const container = document.getElementById('ai-messages');
  const wrapper = document.createElement('div');
  wrapper.className = 'msg ' + role;

  const avatarText = role === 'ai' ? 'AI' : 'U';
  const sugsHtml = suggestions.length > 0
    ? '<div class="suggestions-row">' + suggestions.map(s => `<span class="suggestion-chip" onclick="sendQuick(this.textContent)">${escHtml(s)}</span>`).join('') + '</div>'
    : '';

  wrapper.innerHTML = `
    <div class="msg-avatar">${avatarText}</div>
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

async function sendMessage() {
  const input = document.getElementById('ai-input');
  const sendBtn = document.getElementById('ai-send-btn');
  const text = input.value.trim();
  if (!text) return;

  appendMsg('user', escHtml(text));
  chatHistory.push({ role: 'user', content: text });
  input.value = '';
  input.style.height = 'auto';
  sendBtn.disabled = true;

  const typingEl = showTyping();

  try {
    const res = await fetch(BASE + 'api/ai/chat.php', {
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
      document.getElementById('ai-status').textContent = data.service_status === 'local_fallback'
        ? 'Local fallback - AI service unavailable'
        : 'Online - AI service connected';
    }

    const reply = data.reply || 'Sorry, I had trouble responding. Please try again.';
    chatHistory.push({ role: 'ai', content: reply });
    appendMsg('ai', mdToHtml(reply), data.suggestions || []);
  } catch (err) {
    typingEl.remove();
    appendMsg('ai', 'AI service is currently unavailable. Please try again later or use the listings pages directly.');
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
  appendMsg('ai', 'Chat cleared. How can I help you plan your Malawi trip?');
}

function openBudgetTool() {
  const tpl = document.getElementById('budget-tool-tpl');
  const clone = tpl.content.cloneNode(true);
  const wrapper = document.createElement('div');
  wrapper.className = 'msg ai';
  wrapper.innerHTML = '<div class="msg-avatar">B</div><div style="flex:1;"></div>';
  wrapper.querySelector('div:last-child').appendChild(clone);
  document.getElementById('ai-messages').appendChild(wrapper);
  scrollToBottom();
}

async function runBudget() {
  const dest = document.getElementById('bt-dest').value.trim() || 'Lilongwe';
  const days = document.getElementById('bt-days').value;
  const pax = document.getElementById('bt-pax').value;
  const style = document.getElementById('bt-style').value;
  const resultEl = document.getElementById('bt-result');
  resultEl.innerHTML = 'Calculating...';

  const res = await fetch(BASE + 'api/ai/budget.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ destination: dest, days, travellers: pax, style })
  });
  const data = await res.json();

  if (!data.success) {
    resultEl.innerHTML = 'Error calculating budget.';
    return;
  }

  let html = `<div style="font-size:.8rem;color:var(--clr-text-soft);margin-bottom:.5rem;">${pax} traveller(s) - ${days} day(s) - ${style} - ${dest}</div>`;
  data.items.forEach(item => {
    html += `<div class="budget-row"><span>${item.category}</span><span style="font-weight:600;">MK ${item.amount.toLocaleString()}</span></div>`;
  });
  html += `<div class="budget-row"><span>TOTAL</span><span>MK ${data.total.toLocaleString()}</span></div>`;
  html += `<div style="font-size:.75rem;color:var(--clr-text-soft);margin-top:.5rem;">Per person: MK ${data.per_person.toLocaleString()}</div>`;

  if (data.tips?.length) {
    html += `<div style="margin-top:.75rem;"><div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--clr-primary);margin-bottom:.35rem;">Tips</div>`;
    data.tips.forEach(t => {
      html += `<div style="font-size:.78rem;color:var(--clr-text-soft);margin-bottom:.2rem;">- ${escHtml(t)}</div>`;
    });
    html += `</div>`;
  }

  resultEl.innerHTML = html;
}

function openItineraryTool() {
  const tpl = document.getElementById('itinerary-tool-tpl');
  const clone = tpl.content.cloneNode(true);
  const wrapper = document.createElement('div');
  wrapper.className = 'msg ai';
  wrapper.innerHTML = '<div class="msg-avatar">I</div><div style="flex:1;"></div>';
  wrapper.querySelector('div:last-child').appendChild(clone);
  document.getElementById('ai-messages').appendChild(wrapper);
  scrollToBottom();
}

async function runItinerary() {
  const dest = document.getElementById('it-dest').value.trim() || 'Lilongwe';
  const days = document.getElementById('it-days').value;
  const resultEl = document.getElementById('it-result');
  resultEl.innerHTML = 'Generating itinerary...';

  const res = await fetch(BASE + 'api/ai/itinerary.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ destination: dest, days })
  });
  const data = await res.json();

  if (!data.success) {
    resultEl.innerHTML = 'Error generating itinerary.';
    return;
  }

  let html = '';
  data.itinerary.forEach(day => {
    html += `<div style="margin-bottom:1rem;"><div style="font-weight:700;font-size:.9rem;margin-bottom:.4rem;color:var(--clr-primary);">Day ${day.day}</div>`;
    day.activities.forEach(act => {
      html += `<div style="padding:.4rem 0 .4rem .75rem;border-left:2px solid var(--clr-border);margin-bottom:.3rem;">
        <div style="font-size:.75rem;color:var(--clr-accent);font-weight:600;">${escHtml(act.time)}</div>
        <div style="font-size:.85rem;">${escHtml(act.activity)}</div>
        <div style="font-size:.72rem;color:var(--clr-text-soft);">${escHtml(act.note)}</div>
        ${act.link_id ? `<a href="${BASE}event-details.php?type=${encodeURIComponent(act.link_type)}&id=${encodeURIComponent(act.link_id)}" target="_blank" style="font-size:.72rem;color:var(--clr-primary);">Book on Uthenga -&gt;</a>` : ''}
      </div>`;
    });
    html += '</div>';
  });

  if (data.tips?.length) {
    html += `<div style="margin-top:.75rem;border-top:1px solid var(--clr-border);padding-top:.75rem;">
      <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--clr-primary);margin-bottom:.35rem;">Travel Tips</div>`;
    data.tips.forEach(t => {
      html += `<div style="font-size:.78rem;color:var(--clr-text-soft);margin-bottom:.2rem;">- ${escHtml(t)}</div>`;
    });
    html += `</div>`;
  }

  resultEl.innerHTML = html;
  scrollToBottom();
}

document.getElementById('ai-input')?.addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});
</script>
</body>
</html>
