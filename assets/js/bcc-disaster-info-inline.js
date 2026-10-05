(function () {
  'use strict';

  if (window.__bccDisasterInfoInlineBound) return;
  window.__bccDisasterInfoInlineBound = true;

  var ROOT_SELECTOR = '.bousai-official-info';
  var PRIMARY_SELECTOR = ':scope > .bousai-bridge-section-primary';
  var NOTICE_SELECTOR = ':scope > .bousai-official-notice';
  var SNS_SELECTOR = ':scope > #bousai-disaster-share';
  var CTA_SELECTOR = '[data-bcc-surface="disaster_info_inline"]';
  var TEMPLATE_ID = 'bcc-disaster-info-inline-template';

  var root = document.querySelector(ROOT_SELECTOR);

  var impressionSent = false;
  var impressionObserver = null;
  var impressionTarget = null;
  var placementObserver = null;
  var observedRoot = null;

  function payload(cta) {
    var link = cta.querySelector('a[data-bcc-location="disaster_info_inline"]');
    return {
      location: 'disaster_info_inline',
      link_url: link && link.href ? link.href : ''
    };
  }

  function sendImpression(cta) {
    if (!cta || impressionSent) return false;

    var params = payload(cta);

    try {
      if (typeof window.gtag === 'function') {
        impressionSent = true;
        window.gtag('event', 'bousai_check_cta_impression', params);
        return true;
      }

      if (Array.isArray(window.dataLayer)) {
        impressionSent = true;
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
    if (!cta || impressionSent || (impressionObserver && impressionTarget === cta)) return;

    if (!('IntersectionObserver' in window)) {
      // Conservative fallback: do not fabricate an impression.
      return;
    }

    if (impressionObserver) impressionObserver.disconnect();
    impressionTarget = cta;
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
   * Place only our own node after the root-level primary recommendations.
   * Without primary, keep the CTA immediately before SNS or the About block.
   * The stable weather slot and all foreign nodes remain with their owners.
   */
  function placeOwnNode(cta) {
    var primary = root.querySelector(PRIMARY_SELECTOR);
    var before = primary ? primary.nextSibling :
      (root.querySelector(SNS_SELECTOR) || root.querySelector(NOTICE_SELECTOR));
    if (!primary && !before) return false;

    if (primary) {
      if (cta.parentNode !== root || primary.nextElementSibling !== cta) {
        root.insertBefore(cta, before);
      }
    } else if (cta.parentNode !== root || cta.nextElementSibling !== before) {
      root.insertBefore(cta, before);
    }
    return true;
  }

  /**
   * Primary/CTA/SNS can appear late. Watch only direct root children and
   * re-resolve existing CTA nodes so pageshow/re-mount cannot duplicate them.
   */
  function observeOwnPlacement() {
    if (!('MutationObserver' in window)) return;
    if (placementObserver && observedRoot === root) return;
    if (placementObserver) placementObserver.disconnect();
    observedRoot = root;
    placementObserver = new MutationObserver(mount);
    placementObserver.observe(root, { childList: true });
  }

  /**
   * Mount only our own node at the lower placement anchor.
   * Expected order: primary (when present) -> CTA -> SNS -> About.
   * SNS v1.4.7-rc1 and the independent-weather candidate are coordinated inputs.
   */
  function mount() {
    root = document.querySelector(ROOT_SELECTOR);
    var template = document.getElementById(TEMPLATE_ID);
    if (!root || !template || !template.content) return false;
    var cta = root.querySelector(CTA_SELECTOR);

    if (!cta) {
      var source = template.content.firstElementChild;
      if (!source) return false;

      cta = source.cloneNode(true);
    }
    if (!placeOwnNode(cta)) return false;
    observeOwnPlacement();
    observeImpression(cta);
    return true;
  }

  /*
   * Root, lower anchor and inert template can arrive in either order.
   * Broad observation is limited to initial assembly and stops after mounting.
   */
  if (!mount() && 'MutationObserver' in window) {
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
