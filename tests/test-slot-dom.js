const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const rootDir = path.resolve(__dirname, '..');
const ctaJs = fs.readFileSync(path.join(rootDir, 'assets/js/bcc-disaster-info-inline.js'), 'utf8');
const snsSource = fs.readFileSync(path.join(rootDir, 'integration/bousai-disaster-share-v1.4.2.txt'), 'utf8');

const scriptMatch = snsSource.match(/<script id="bousai-disaster-share-script">([\s\S]*?)<\/script>/);
if (!scriptMatch) {
  throw new Error('SNS script block not found');
}
const snsJs = scriptMatch[1];

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
  process.stdout.write('PASS: ' + message + '\n');
}

function makeDom({ withSlot, withTemplate }) {
  const slot = withSlot
    ? '<div class="bousai-post-current-actions" data-bousai-slot="post-current-actions"></div>'
    : '';

  const template = withTemplate
    ? '<template id="bcc-disaster-info-inline-template" data-bcc-template="disaster_info_inline">' +
      '<aside data-bcc-surface="disaster_info_inline">' +
      '<a data-bcc-location="disaster_info_inline" href="/check/">check</a>' +
      '</aside></template>'
    : '';

  const html = '<!doctype html><html><head><title>東京都の災害情報</title>' +
    '<link rel="canonical" href="https://bousai-matome.com/disaster-info/tokyo/">' +
    '</head><body>' +
    '<div class="bousai-official-info" data-region-page="東京都">' +
    '<section class="bousai-source-section" id="current">' +
    '<h2 class="bousai-source-title">現在発表されている警報・災害情報</h2>' +
    '<div class="all-current-items">all current items</div>' +
    '</section>' +
    slot +
    '<section id="downstream">downstream</section>' +
    '</div>' +
    template +
    '</body></html>';

  const dom = new JSDOM(html, {
    url: 'https://bousai-matome.com/disaster-info/tokyo/?bousai_cta_preview=1',
    runScripts: 'outside-only'
  });

  const win = dom.window;

  class NoopMutationObserver {
    constructor(callback) {
      this.callback = callback;
      this.disconnected = false;
    }
    observe() {}
    disconnect() {
      this.disconnected = true;
    }
  }

  class NoopIntersectionObserver {
    constructor(callback) {
      this.callback = callback;
    }
    observe() {}
    unobserve() {}
    disconnect() {}
  }

  win.MutationObserver = NoopMutationObserver;
  win.IntersectionObserver = NoopIntersectionObserver;
  win.setTimeout = function () { return 1; };
  win.clearTimeout = function () {};
  win.gtag = function () {};
  return dom;
}

function runScripts(dom, runCta) {
  const win = dom.window;
  if (runCta) {
    win.eval(ctaJs);
  }
  win.eval(snsJs);
  win.document.dispatchEvent(new win.Event('DOMContentLoaded'));
}

(function testSlotWithCtaAndSns() {
  const dom = makeDom({ withSlot: true, withTemplate: true });
  runScripts(dom, true);

  const root = dom.window.document.querySelector('.bousai-official-info');
  const current = dom.window.document.getElementById('current');
  const slot = root.querySelector(':scope > [data-bousai-slot="post-current-actions"]');
  const downstream = dom.window.document.getElementById('downstream');
  const children = Array.from(slot.children);

  assert(current.nextElementSibling === slot, 'stable slot remains immediately after current section');
  assert(slot.nextElementSibling === downstream, 'downstream content remains after stable slot');
  assert(children.length === 2, 'slot contains exactly CTA and SNS in integration fixture');
  assert(children[0].matches('[data-bcc-surface="disaster_info_inline"]'), 'CTA is first inside stable slot');
  assert(children[1].id === 'bousai-disaster-share', 'SNS share is second inside stable slot');
})();

(function testSlotWithoutCta() {
  const dom = makeDom({ withSlot: true, withTemplate: false });
  runScripts(dom, false);

  const root = dom.window.document.querySelector('.bousai-official-info');
  const slot = root.querySelector(':scope > [data-bousai-slot="post-current-actions"]');
  const children = Array.from(slot.children);

  assert(children.length === 1, 'disabled CTA leaves one SNS child in stable slot');
  assert(children[0].id === 'bousai-disaster-share', 'SNS share occupies stable slot when CTA is disabled');
})();

(function testLegacyFallbackWithoutSlot() {
  const dom = makeDom({ withSlot: false, withTemplate: false });
  runScripts(dom, false);

  const current = dom.window.document.getElementById('current');
  assert(
    current.nextElementSibling && current.nextElementSibling.id === 'bousai-disaster-share',
    'SNS preserves legacy current-next-sibling fallback when slot is absent'
  );
})();

process.stdout.write('All RC5 DOM integration tests passed.\n');
