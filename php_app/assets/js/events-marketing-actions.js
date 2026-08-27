/*
 * Event marketing action bridge.
 *
 * Marketing cards are rendered both in the initial document and dynamically
 * after API refreshes.  This capture-phase bridge keeps their actions live
 * without relying on an inline handler surviving a partial page refresh.
 */
(function () {
  'use strict';

  function notify(message) {
    if (typeof window.eccNotify === 'function') {
      window.eccNotify(message);
      return;
    }
    window.alert(message);
  }

  function openFallbackModal(action) {
    var id = action === 'promotion-create'
      ? 'modal-mkt-promo'
      : action === 'promo-code-create'
        ? 'modal-mkt-code'
        : 'modal-mkt-campaign-wiz';
    if (typeof window.openEccModal === 'function') {
      window.openEccModal(id);
      return true;
    }
    var modal = document.getElementById(id);
    if (!modal) return false;
    modal.style.display = 'flex';
    modal.classList.add('active');
    return true;
  }

  function controller() {
    return window.MarketingControlCenter || null;
  }

  window.UthengaMarketingAction = function (action, target, preset, button) {
    var mkt = controller();
    if (!mkt) {
      if (action === 'campaign-create' || action === 'promotion-create' || action === 'promo-code-create') {
        openFallbackModal(action);
        notify('Marketing tools are still loading. Please wait a moment, then continue.');
      } else {
        notify('The marketing workspace did not finish loading. Refresh the page and try again.');
      }
      return;
    }

    switch (action) {
      case 'tab':
        mkt.switchTab(target, button);
        break;
      case 'campaign-create':
        if (target && !preset) {
          mkt.promoteEvent(target);
        } else {
          mkt.openCreateWizard(preset || undefined);
        }
        break;
      case 'campaign-investigate':
        mkt.investigateCampaign(target || '');
        break;
      case 'campaign-view':
        mkt.viewCampaign(target);
        break;
      case 'campaign-toggle':
        mkt.toggleCampaignStatus(target);
        break;
      case 'promotion-create':
        mkt.openPromoModal();
        break;
      case 'promotion-manage':
        mkt.managePromotion(target);
        break;
      case 'promotion-toggle':
        mkt.togglePromotionStatus(target);
        break;
      case 'promo-code-create':
        mkt.openPromoCodeModal();
        break;
      case 'ad-generate':
        mkt.generateAiCardCopy();
        break;
      case 'ad-save':
        mkt.saveAdCard();
        break;
      case 'ai-toggle':
        mkt.toggleAiPanel();
        break;
      default:
        notify('That marketing action is not available.');
    }
  };

  document.addEventListener('click', function (event) {
    var source = event.target && event.target.closest
      ? event.target.closest('[data-mkt-action]')
      : null;
    if (!source) return;

    event.preventDefault();
    event.stopImmediatePropagation();
    window.UthengaMarketingAction(
      source.getAttribute('data-mkt-action'),
      source.getAttribute('data-mkt-target') || '',
      source.getAttribute('data-mkt-preset') || '',
      source
    );
  }, true);
}());
