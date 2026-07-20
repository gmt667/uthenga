<?php require_once __DIR__ . '/../config.php'; ?>
</main>

<footer class="footer">
  <div class="container">
    <div class="footer-bottom" style="align-items:center;flex-wrap:wrap;">
      <span><?= APP_NAME ?> &copy; <?= date('Y') ?>. Version <?= e(APP_VERSION) ?>.</span>
      <span>
        <a href="<?= BASE_URL ?>support.php">Support</a> &middot;
        <a href="mailto:<?= e(SUPPORT_CONTACT['email']) ?>"><?= e(SUPPORT_CONTACT['email']) ?></a> ·
        <a href="tel:<?= e(SUPPORT_CONTACT['phone']) ?>"><?= e(SUPPORT_CONTACT['phone']) ?></a>
      </span>
    </div>
  </div>
</footer>

<script src="<?= BASE_URL ?>assets/js/main.js"></script>

<!-- Floating AI Chat Widget (Amai) -->
<?php if (!defined('SKIP_AI_WIDGET')): ?>
<style>
  .amai-fab { position:fixed; bottom:1.5rem; right:1.5rem; z-index:1000; width:56px; height:56px; border-radius:50%; background:linear-gradient(135deg,#06b6d4,#a855f7); border:none; color:#fff; font-size:0.82rem; font-weight:700; cursor:pointer; box-shadow:0 8px 24px rgba(6,182,212,.45); transition:transform .25s,box-shadow .25s; display:flex; align-items:center; justify-content:center; }
  .amai-fab:hover { transform:scale(1.08); box-shadow:0 12px 32px rgba(6,182,212,.6); }
  .amai-fab-label { position:fixed; bottom:4.2rem; right:1.5rem; z-index:999; background:var(--clr-surface,#1c1c2e); border:1px solid rgba(6,182,212,.3); color:#fff; font-size:.78rem; font-weight:600; padding:.3rem .7rem; border-radius:100px; pointer-events:none; opacity:0; transform:translateY(6px); transition:all .25s; white-space:nowrap; }
  .amai-fab:hover ~ .amai-fab-label,
  .amai-fab-label.show { opacity:1; transform:translateY(0); }
  .amai-popover { position:fixed; bottom:4.5rem; right:1.5rem; z-index:999; width:340px; max-height:480px; background:var(--clr-surface,#1c1c2e); border:1px solid rgba(6,182,212,.25); border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,.5); display:flex; flex-direction:column; overflow:hidden; transition:all .3s cubic-bezier(.175,.885,.32,1.275); transform-origin:bottom right; }
  .amai-popover.hidden { opacity:0; transform:scale(.85); pointer-events:none; }
  .amai-pop-header { padding:.85rem 1rem; border-bottom:1px solid rgba(255,255,255,.08); display:flex; align-items:center; gap:.6rem; background:linear-gradient(135deg,rgba(6,182,212,.15),rgba(168,85,247,.1)); }
  .amai-pop-header span { font-weight:700; font-size:.9rem; }
  .amai-pop-msgs { flex:1; overflow-y:auto; padding:.75rem; display:flex; flex-direction:column; gap:.6rem; }
  .amai-bubble { padding:.55rem .8rem; border-radius:12px; font-size:.82rem; line-height:1.5; max-width:90%; }
  .amai-bubble.ai   { background:rgba(255,255,255,.07); align-self:flex-start; border-radius:0 12px 12px 12px; }
  .amai-bubble.user { background:var(--clr-primary,#06b6d4); color:#fff; align-self:flex-end; border-radius:12px 0 12px 12px; }
  .amai-pop-input { padding:.6rem .75rem; border-top:1px solid rgba(255,255,255,.08); display:flex; gap:.4rem; }
  .amai-pop-input input { flex:1; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.12); border-radius:8px; padding:.45rem .65rem; color:#fff; font-size:.82rem; outline:none; }
  .amai-pop-input input:focus { border-color:var(--clr-primary,#06b6d4); }
  .amai-pop-input button { width:64px; height:34px; border-radius:8px; background:var(--clr-primary,#06b6d4); border:none; color:#fff; cursor:pointer; font-size:.82rem; font-weight:700; display:flex; align-items:center; justify-content:center; }
  .amai-pop-footer { padding:.5rem .75rem; border-top:1px solid rgba(255,255,255,.08); display:flex; justify-content:space-between; align-items:center; }
  .amai-pop-footer a { font-size:.73rem; color:var(--clr-primary,#06b6d4); text-decoration:none; }
  @media(max-width:480px){ .amai-popover{ width:calc(100vw - 2rem); right:1rem; bottom:4.5rem; } }
</style>

<div class="amai-popover hidden" id="amai-popover" role="dialog" aria-label="Amai AI Assistant">
  <div class="amai-pop-header">
    <span>Amai</span>
    <span>AI Assistant</span>
    <button onclick="toggleAmai()" style="margin-left:auto;background:none;border:none;color:rgba(255,255,255,.6);cursor:pointer;font-size:1rem;">Close</button>
  </div>
  <div class="amai-pop-msgs" id="amai-pop-msgs">
    <div class="amai-bubble ai">Hello! I'm Amai. Ask me anything about travel in Malawi - hotels, events, tours, or trip planning!</div>
  </div>
  <div class="amai-pop-input">
    <input type="text" id="amai-pop-input" placeholder="Ask about Malawi travel..."
           onkeydown="if(event.key==='Enter')amaiSend()">
    <button onclick="amaiSend()">Send</button>
  </div>
  <div class="amai-pop-footer">
    <span class="text-xs" style="color:rgba(255,255,255,.4);">AI may make errors</span>
    <a href="<?= BASE_URL ?>ai/chat.php">Full Chat &rarr;</a>
  </div>
</div>

<button class="amai-fab" id="amai-fab" onclick="toggleAmai()" aria-label="Open AI assistant" title="Chat with Amai">Amai</button>
<div class="amai-fab-label" id="amai-fab-label">Ask Amai</div>

<script>
(function() {
  let amaiOpen = false;
  let amaiHistory = [];

  window.toggleAmai = function() {
    amaiOpen = !amaiOpen;
    const pop = document.getElementById('amai-popover');
    const fab = document.getElementById('amai-fab');
    if (pop) pop.classList.toggle('hidden', !amaiOpen);
    if (fab) fab.textContent = amaiOpen ? 'Close' : 'Amai';
    if (amaiOpen) setTimeout(() => document.getElementById('amai-pop-input')?.focus(), 100);
  };

  window.amaiSend = async function() {
    const input = document.getElementById('amai-pop-input');
    const msgs  = document.getElementById('amai-pop-msgs');
    const text  = input?.value?.trim();
    if (!text) return;

    const addBubble = (cls, html) => {
      const el = document.createElement('div');
      el.className = 'amai-bubble ' + cls;
      el.innerHTML = html;
      msgs.appendChild(el);
      msgs.scrollTop = msgs.scrollHeight;
      return el;
    };

    addBubble('user', text.replace(/</g,'&lt;'));
    amaiHistory.push({ role:'user', content: text });
    input.value = '';

    const typingEl = addBubble('ai', 'Loading...');

    try {
      const BASE = document.querySelector('meta[name="base-url"]')?.content || '';
      const res  = await fetch(BASE + 'api/ai/chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: text, history: amaiHistory.slice(-6) })
      });
      const data = await res.json();
      typingEl.remove();
      const reply = data.reply || 'Sorry, I had an issue. Try the full chat page.';
      amaiHistory.push({ role: 'ai', content: reply });
      const replyEl = addBubble('ai', reply.replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>').replace(/\n/g,'<br>'));
      replyEl.innerHTML += `<div style="margin-top:.4rem;"><a href="${BASE}ai/chat.php" style="font-size:.7rem;color:var(--clr-primary);">Open full chat &rarr;</a></div>`;
    } catch {
      typingEl.textContent = 'Network error. Try the full chat page.';
    }
  };
})();
</script>
<?php endif; ?>

</body>
</html>


