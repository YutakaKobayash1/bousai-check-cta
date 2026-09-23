(function () {
  'use strict';

  if (window.__bccDisasterInfoInlineBound) return;
  window.__bccDisasterInfoInlineBound = true;

  var selector = '[data-bcc-surface="disaster_info_inline"]';
  var ctas = Array.prototype.slice.call(document.querySelectorAll(selector));
  if (!ctas.length) return;

  var seen = new WeakSet();

  function payload(root) {
    var link = root.querySelector('a[data-bcc-location="disaster_info_inline"]');
    return {
      location: 'disaster_info_inline',
      link_url: link && link.href ? link.href : ''
    };
  }

  function sendImpression(root) {
    if (!root || seen.has(root)) return false;

    var params = payload(root);

    try {
      if (typeof window.gtag === 'function') {
        seen.add(root);
        window.gtag('event', 'bousai_check_cta_impression', params);
        return true;
      }

      if (Array.isArray(window.dataLayer)) {
        seen.add(root);
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

  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting && entry.intersectionRatio >= 0.5) {
          if (sendImpression(entry.target)) {
            io.unobserve(entry.target);
          }
        }
      });
    }, { threshold: [0.5] });

    ctas.forEach(function (cta) { io.observe(cta); });
    return;
  }

  // Conservative fallback for old browsers: do not fabricate an impression.
}());