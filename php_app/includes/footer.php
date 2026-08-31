<?php require_once __DIR__ . '/../config.php'; ?>
</main>

<footer class="footer uthenga-footer">
  <div class="container uthenga-footer__inner">
    <div class="uthenga-footer__grid">
      <section class="uthenga-footer__brand" aria-label="About Uthenga">
        <a href="<?= BASE_URL ?>index.php" class="uthenga-footer__wordmark" aria-label="Uthenga home">UTHENGA</a>
        <p>One trusted local marketplace for discovering Malawi: events, stays, transport, shopping, and complete travel plans.</p>
        <a class="uthenga-footer__facebook" href="https://web.facebook.com/profile.php?id=61592102205321" target="_blank" rel="noopener noreferrer">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.5 21v-8h2.75l.5-3h-3.25V8.05c0-.87.29-1.55 1.58-1.55H17V3.82c-.38-.05-1.16-.16-2.1-.16-2.08 0-3.5 1.24-3.5 3.54V10H9v3h2.48v8h2.02Z" fill="currentColor"/></svg>
          Follow Uthenga on Facebook
        </a>
      </section>
      <nav class="uthenga-footer__column" aria-label="Explore Uthenga">
        <h2>Explore</h2><a href="<?= BASE_URL ?>events.php">Events</a><a href="<?= BASE_URL ?>hotels.php">Stays</a><a href="<?= BASE_URL ?>transport.php">Transport</a><a href="<?= BASE_URL ?>ai.php#/driver">Quick Taxi</a><a href="<?= BASE_URL ?>shop.php">Shop</a>
      </nav>
      <nav class="uthenga-footer__column" aria-label="Uthenga company links">
        <h2>Uthenga</h2><a href="<?= BASE_URL ?>about.php">About us</a><a href="<?= BASE_URL ?>tourism.php">Discover Malawi</a><a href="<?= BASE_URL ?>vendor/register.php">Become a vendor</a><a href="<?= BASE_URL ?>support.php">Help centre</a>
      </nav>
      <section class="uthenga-footer__column uthenga-footer__contact" aria-label="Contact Uthenga">
        <h2>Get in touch</h2><p>Need help planning or booking? Our team is here for you.</p><a href="mailto:<?= e(SUPPORT_CONTACT['email']) ?>"><?= e(SUPPORT_CONTACT['email']) ?></a><a href="tel:<?= e(SUPPORT_CONTACT['phone']) ?>"><?= e(SUPPORT_CONTACT['phone']) ?></a>
      </section>
    </div>
    <div class="uthenga-footer__bottom"><span>&copy; <?= date('Y') ?> <?= APP_NAME ?>. Built for exploring Malawi.</span><span>Version <?= e(APP_VERSION) ?></span></div>
  </div>
</footer>

<script src="<?= BASE_URL ?>assets/js/main.js?v=<?= rawurlencode(APP_VERSION) ?>"></script>

<!-- Floating WhatsApp Button -->
<?php if (!defined('SKIP_AI_WIDGET')): ?>
<style>
  .wa-fab {
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    z-index: 1000;
    width: 58px;
    height: 58px;
    border-radius: 50%;
    background: #25D366;
    border: none;
    color: #fff;
    cursor: pointer;
    box-shadow: 0 8px 24px rgba(37,211,102,.45);
    transition: transform .25s, box-shadow .25s;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    animation: wa-pulse 2.5s ease-in-out infinite;
  }
  .wa-fab:hover {
    transform: scale(1.1);
    box-shadow: 0 14px 36px rgba(37,211,102,.65);
    animation: none;
    background: #20ba59;
  }
  .wa-fab svg {
    width: 32px;
    height: 32px;
    fill: #fff;
    display: block;
  }
  .wa-fab-label {
    position: fixed;
    bottom: 4.4rem;
    right: 1.5rem;
    z-index: 999;
    background: #1a1a2e;
    border: 1px solid rgba(37,211,102,.4);
    color: #fff;
    font-size: .78rem;
    font-weight: 600;
    padding: .3rem .75rem;
    border-radius: 100px;
    pointer-events: none;
    opacity: 0;
    transform: translateY(6px);
    transition: all .25s;
    white-space: nowrap;
    font-family: inherit;
  }
  .wa-fab:hover ~ .wa-fab-label { opacity: 1; transform: translateY(0); }
  @keyframes wa-pulse {
    0%, 100% { box-shadow: 0 8px 24px rgba(37,211,102,.45); }
    50%       { box-shadow: 0 8px 32px rgba(37,211,102,.75), 0 0 0 8px rgba(37,211,102,.12); }
  }
  @media (max-width: 480px) {
    .wa-fab { bottom: 1rem; right: 1rem; }
    .wa-fab-label { right: 1rem; bottom: 4rem; }
  }
</style>

<a class="wa-fab"
   id="wa-fab"
   href="https://wa.me/265885362150"
   target="_blank"
   rel="noopener noreferrer"
   aria-label="Chat with us on WhatsApp"
   title="Chat on WhatsApp">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
  </svg>
</a>
<div class="wa-fab-label">WhatsApp us</div>
<?php endif; ?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/uthenga-payment.css?v=<?= rawurlencode(APP_VERSION) ?>">
<?php require_once __DIR__ . '/payment_modal.php'; ?>

</body>
</html>


