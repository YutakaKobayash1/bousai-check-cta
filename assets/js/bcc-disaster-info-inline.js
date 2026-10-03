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
  var slotTailObserver = null;
  var observedSlot = null;
  var observedCta = null;

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
   * Keep only our own node at the tail of the stable layout-owned slot.
   *
   * This deliberately does not inspect, move, or reorder SNS/weather nodes.
   * Their relative order remains owned by those components.
   */
  function keepOwnNodeAtTail(slot, cta) {
    if (!slot || !cta || cta.parentNode !== slot) return false;

    if (slot.lastElementChild !== cta) {
      slot.appendChild(cta);
    }

    return true;
  }

  /**
   * Other slot consumers can mount after this CTA. Watch only the slot's direct
   * child list and, when needed, move only this CTA back to the tail.
   */
  function observeOwnTail(slot, cta) {
    if (!('MutationObserver' in window)) return;

    if (slotTailObserver && observedSlot === slot && observedCta === cta) {
      return;
    }

    if (slotTailObserver) {
      slotTailObserver.disconnect();
    }

    observedSlot = slot;
    observedCta = cta;

    slotTailObserver = new MutationObserver(function () {
      keepOwnNodeAtTail(slot, cta);
    });

    slotTailObserver.observe(slot, {
      childList: true
    });
  }

  /**
   * Mount only our own node into the stable layout-owned slot.
   *
   * Contract:
   * - layout owner owns the root-level slot position
   * - SNS owns only its own node
   * - weather owns only its own node
   * - CTA owns only its own node and stays at the slot tail
   * - CTA never moves foreign component nodes
   *
   * Expected order when all consumers exist:
   * SNS -> weather -> CTA
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
      slot.appendChild(cta);
    } else {
      keepOwnNodeAtTail(slot, cta);
    }

    observeOwnTail(slot, cta);
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
