(function () {
  'use strict';

  if (window.__bccDisasterInfoInlineBound) return;
  window.__bccDisasterInfoInlineBound = true;

  var selector = '[data-bcc-surface="disaster_info_inline"]';
  var root = document.querySelector('.bousai-official-info');
  if (!root) return;

  function normalizeText(value) {
    return (value || '').replace(/\s+/g, ' ').trim();
  }

  function findCurrentSection() {
    var sections = root.querySelectorAll('.bousai-source-section');
    for (var i = 0; i < sections.length; i++) {
      var title = sections[i].querySelector('.bousai-source-title');
      if (title && normalizeText(title.textContent) === '現在発表されている警報・災害情報') {
        return sections[i];
      }
    }
    return null;
  }

  /*
   * Other disaster-info scripts initialize accordions and can move child nodes
   * inside the current-information section after server rendering. Keep the CTA
   * as the final child of that section. The SNS-share block remains the section's
   * immediate next sibling, so the visual order is always:
   * current information -> CTA -> SNS share.
   */
  function keepCtaAtCurrentEnd() {
    var current = findCurrentSection();
    var cta = root.querySelector(selector);
    if (!current || !cta) return false;

    if (current.lastElementChild !== cta) {
      current.appendChild(cta);
    }
    return true;
  }

  keepCtaAtCurrentEnd();

  var currentSection = findCurrentSection();
  if (currentSection && 'MutationObserver' in window) {
    var placementObserver = new MutationObserver(function () {
      keepCtaAtCurrentEnd();
    });
    placementObserver.observe(currentSection, { childList: true });
  }

  window.requestAnimationFrame(function () {
    keepCtaAtCurrentEnd();
  });

  window.setTimeout(keepCtaAtCurrentEnd, 0);
  window.setTimeout(keepCtaAtCurrentEnd, 250);
  window.setTimeout(keepCtaAtCurrentEnd, 2200);

  window.addEventListener('pageshow', function () {
    window.setTimeout(keepCtaAtCurrentEnd, 0);
  });

  var ctas = Array.prototype.slice.call(document.querySelectorAll(selector));
  if (!ctas.length) return;

  var seen = new WeakSet();

  function payload(cta) {
    var link = cta.querySelector('a[data-bcc-location="disaster_info_inline"]');
    return {
      location: 'disaster_info_inline',
      link_url: link && link.href ? link.href : ''
    };
  }

  function sendImpression(cta) {
    if (!cta || seen.has(cta)) return false;

    var params = payload(cta);

    try {
      if (typeof window.gtag === 'function') {
        seen.add(cta);
        window.gtag('event', 'bousai_check_cta_impression', params);
        return true;
      }

      if (Array.isArray(window.dataLayer)) {
        seen.add(cta);
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
  }

  // Conservative fallback for old browsers: do not fabricate an impression.
}());