const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const rootDir = path.resolve(__dirname, '..');
const ctaJs = fs.readFileSync(path.join(rootDir, 'assets/js/bcc-disaster-info-inline.js'), 'utf8');
const snsSource = fs.readFileSync(path.join(rootDir, 'integration/bousai-disaster-share-v1.4.5.txt'), 'utf8');

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

  win.__mutationObservers = [];

  class NoopMutationObserver {
    constructor(callback) {
      this.callback = callback;
      this.disconnected = false;
      this.target = null;
      this.options = {};
      win.__mutationObservers.push(this);
    }
    observe(target, options) {
      this.target = target;
      this.options = options || {};
    }
    disconnect() {
      this.disconnected = true;
    }
    trigger() {
      if (!this.disconnected) {
        this.callback([], this);
      }
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

function triggerMutationObserversFor(win, mutationTarget) {
  win.__mutationObservers.slice().forEach(function (observer) {
    if (observer.disconnected || !observer.target) return;

    const direct = observer.target === mutationTarget;
    const descendant =
      observer.options.subtree &&
      observer.target.contains &&
      observer.target.contains(mutationTarget);

    if (direct || descendant) {
      observer.trigger();
    }
  });
}

function slotFor(win) {
  return win.document.querySelector(
    '.bousai-official-info > [data-bousai-slot="post-current-actions"]'
  );
}

function runScripts(dom, runCta) {
  const win = dom.window;

  if (runCta) {
    win.eval(ctaJs);
  }

  win.eval(snsJs);
  win.document.dispatchEvent(new win.Event('DOMContentLoaded'));

  const slot = slotFor(win);
  if (slot) {
    // SNS appends inside the slot. Only observers watching that slot should react.
    triggerMutationObserversFor(win, slot);
  }
}

(function testTemplateArrivesAfterCtaScript() {
  const dom = makeDom({ withSlot: true, withTemplate: false });
  const win = dom.window;

  win.eval(ctaJs);

  assert(
    !win.document.querySelector('[data-bcc-surface="disaster_info_inline"]'),
    'CTA is not fabricated before footer template exists'
  );

  const template = win.document.createElement('template');
  template.id = 'bcc-disaster-info-inline-template';
  template.setAttribute('data-bcc-template', 'disaster_info_inline');
  template.innerHTML =
    '<aside data-bcc-surface="disaster_info_inline">' +
    '<a data-bcc-location="disaster_info_inline" href="/check/">check</a>' +
    '</aside>';
  win.document.body.appendChild(template);

  triggerMutationObserversFor(win, win.document.body);

  const slot = slotFor(win);
  const cta = slot && slot.querySelector('[data-bcc-surface="disaster_info_inline"]');

  assert(!!cta, 'CTA mounts when footer template arrives after CTA script');
  assert(slot.lastElementChild === cta, 'late-mounted CTA owns only the slot tail');
})();

(function testSlotWithSnsAndCta() {
  const dom = makeDom({ withSlot: true, withTemplate: true });
  runScripts(dom, true);

  const win = dom.window;
  const root = win.document.querySelector('.bousai-official-info');
  const current = win.document.getElementById('current');
  const slot = slotFor(win);
  const downstream = win.document.getElementById('downstream');
  const children = Array.from(slot.children);

  assert(current.nextElementSibling === slot, 'stable slot remains immediately after current section');
  assert(slot.nextElementSibling === downstream, 'downstream content remains after stable slot');
  assert(children.length === 2, 'slot contains exactly SNS and CTA in integration fixture');
  assert(children[0].id === 'bousai-disaster-share', 'SNS remains before CTA when weather is absent');
  assert(children[1].matches('[data-bcc-surface="disaster_info_inline"]'), 'CTA remains at slot tail');
})();

(function testWeatherCanInsertBeforeCtaWithoutBeingMovedByCta() {
  const dom = makeDom({ withSlot: true, withTemplate: true });
  runScripts(dom, true);

  const win = dom.window;
  const slot = slotFor(win);
  const weather = win.document.createElement('section');
  weather.id = 'weather-forecast';
  weather.setAttribute('data-bousai-weather', 'forecast');
  weather.textContent = 'weather';

  const sns = slot.querySelector('#bousai-disaster-share');
  const cta = slot.querySelector('[data-bcc-surface="disaster_info_inline"]');

  slot.insertBefore(weather, cta);
  triggerMutationObserversFor(win, slot);

  const children = Array.from(slot.children);

  assert(children.length === 3, 'slot contains SNS, weather, and CTA');
  assert(children[0] === sns, 'CTA contract does not move SNS');
  assert(children[1] === weather, 'CTA contract does not move weather');
  assert(children[2] === cta, 'CTA remains after SNS and weather');
})();

(function testLateForeignConsumerLeavesForeignOrderUntouched() {
  const dom = makeDom({ withSlot: true, withTemplate: true });
  runScripts(dom, true);

  const win = dom.window;
  const slot = slotFor(win);
  const sns = slot.querySelector('#bousai-disaster-share');
  const cta = slot.querySelector('[data-bcc-surface="disaster_info_inline"]');
  const weather = win.document.createElement('section');
  weather.id = 'weather-forecast-late';

  slot.appendChild(weather);
  assert(slot.lastElementChild === weather, 'late foreign consumer can mount after CTA initially');

  triggerMutationObserversFor(win, slot);

  const children = Array.from(slot.children);
  assert(children[0] === sns, 'SNS relative order is preserved after CTA tail correction');
  assert(children[1] === weather, 'weather remains after SNS');
  assert(children[2] === cta, 'CTA moves only itself back to tail');
})();

(function testLateSnsTailClaimIsCorrectedByMovingOnlyCta() {
  const dom = makeDom({ withSlot: true, withTemplate: true });
  runScripts(dom, true);

  const win = dom.window;
  const slot = slotFor(win);
  const sns = slot.querySelector('#bousai-disaster-share');
  const cta = slot.querySelector('[data-bcc-surface="disaster_info_inline"]');

  // Simulate the existing SNS snippet's delayed placement pass.
  slot.appendChild(sns);
  assert(slot.lastElementChild === sns, 'SNS can temporarily move itself to slot tail');

  triggerMutationObserversFor(win, slot);

  assert(slot.children[0] === sns, 'SNS node is not moved by CTA logic');
  assert(slot.children[1] === cta, 'CTA restores only itself to the final tail position');
})();

(function testSlotWithoutCta() {
  const dom = makeDom({ withSlot: true, withTemplate: false });
  runScripts(dom, false);

  const win = dom.window;
  const slot = slotFor(win);
  const children = Array.from(slot.children);

  assert(children.length === 1, 'disabled CTA leaves one SNS child in stable slot');
  assert(children[0].id === 'bousai-disaster-share', 'SNS owns its slot position when CTA is disabled');
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

process.stdout.write('All v1.4.1-rc1 DOM integration tests passed.\n');
