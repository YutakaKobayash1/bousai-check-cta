(function () {
  'use strict';

  if (window.__bccDisasterInfoInlineBound) return;
  window.__bccDisasterInfoInlineBound = true;

  var ROOT_SELECTOR = '.bousai-official-info';
  var SLOT_SELECTOR = ':scope > [data-bousai-slot="post-current-actions"]';
  var CTA_SELECTOR = '[data-bcc-surface="disaster_info_inline"]';
  var TEMPLATE_ID = 'bcc-disaster-info-inline-template';

  var root = document.querySelector(ROOT_SELECTOR);

  if (!root) return;

  var impressionSeen = new WeakSet();
  var impressionObserver = null;

  function payload(cta) {
    var link = cta.querySelector('a[data-bcc-location="disaster_info_inline"]');
    return {
      location: 'disaster_info_inline',
      link_url: link && link.href ? link.href : ''
    };
  }

  function sendImpression(cta) {
    if (!cta || impressionSeen.has(cta)) return false;

    var params = payload(cta);

    try {
      if (typeof window.gtag === 'function') {
        impressionSeen.add(cta);
        window.gtag('event', 'bousai_check_cta_impression', params);
        return true;
      }

      if (Array.isArray(window.dataLayer)) {
        impressionSeen.add(cta);
        window.dataLayer.push({
          event: 'bousai_check_cta_impression',
          location: params.location,
          link_url: params.link_url
        });
        return true;
      }
    } catch (error) {
      // Analytics must never affect navigation or disaster information rendering.
    }

    return false;
  }

  function observeImpression(cta) {
    if (!cta || impressionSeen.has(cta) || impressionObserver) return;

    if (!('IntersectionObserver' in window)) {
      // Conservative fallback: do not fabricate an impression.
      return;
    }

    impressionObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting && entry.intersectionRatio >= 0.5) {
          if (sendImpression(entry.target)) {
            impressionObserver.unobserve(entry.target);
            impressionObserver.disconnect();
            impressionObserver = null;
          }
        }
      });
    }, { threshold: [0.5] });

    impressionObserver.observe(cta);
  }

  /**
   * Mount only our own node into the stable layout-owned slot.
   *
   * Contract:
   * - layout owner owns the slot position
   * - CTA owns only its own node
   * - SNS share owns only its own node
   * - CTA is inserted first so the slot order is CTA -> SNS
   *
   * No current-section lookup, no current-section child reordering, no timers
   * that compete with Priority View/SEO/SNS layout writers.
   */
  function mount() {
    var slot = root.querySelector(SLOT_SELECTOR);
    var template = document.getElementById(TEMPLATE_ID);

    if (!slot || !template || !template.content) return false;

    var cta = slot.querySelector(CTA_SELECTOR);

    if (!cta) {
      var source = template.content.firstElementChild;
      if (!source) return false;

      cta = source.cloneNode(true);
      slot.insertBefore(cta, slot.firstChild);
    } else if (slot.firstElementChild !== cta) {
      /*
       * This is not a page-layout reorder. It only keeps this component first
       * inside its explicitly owned action slot so SNS can follow it.
       */
      slot.insertBefore(cta, slot.firstChild);
    }

    observeImpression(cta);
    return true;
  }

  if (mount()) {
    return;
  }

  /*
   * The slot and the inert template are owned by different wp_footer/DOMContentLoaded
   * writers and can appear in either order. Re-resolve both on every mount attempt.
   * Observe initial DOM assembly only until both exist, then mount once and disconnect.
   */
  if ('MutationObserver' in window) {
    var waitForMountInputs = new MutationObserver(function () {
      if (mount()) {
        waitForMountInputs.disconnect();
      }
    });

    waitForMountInputs.observe(document.body || document.documentElement, {
      childList: true,
      subtree: true
    });
  }

  window.addEventListener('pageshow', function () {
    mount();
  });
}());
