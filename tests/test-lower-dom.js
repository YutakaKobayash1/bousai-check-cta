const fs = require('fs'), path = require('path'), assert = require('assert/strict');
const { JSDOM } = require('jsdom');
const dir = path.resolve(__dirname, '..');
const read = p => fs.readFileSync(path.join(dir, p), 'utf8');
const ctaJs = read('assets/js/bcc-disaster-info-inline.js'), coreJs = read('assets/js/bcc.js');
const extract = (s, id) => s.match(new RegExp('<script id="' + id + '">([\\s\\S]*?)</script>'))[1];
const snsJs = extract(read('integration/sns-19-v1.4.7-rc1.txt'), 'bousai-disaster-share-script');
const weatherJs = extract(read('integration/weather-22-placement-rc1.txt'), 'bousai-weather-v1-script').replace('<?php echo esc_js( $prefecture ); ?>', '東京都');
const primary = '<section class="bousai-bridge-section-primary" id="primary">primary</section>';
const about = '<aside class="bousai-official-notice" id="about">About</aside>';
const template = '<template id="bcc-disaster-info-inline-template"><aside data-bcc-surface="disaster_info_inline"><a class="bcc-track" data-bcc-location="disaster_info_inline" href="/check/">check</a></aside></template>';
const rootHtml = body => '<div class="bousai-official-info" data-region-page="東京都"><section id="current">current</section><div data-bousai-slot="post-current-actions"><aside id="existing-weather">weather</aside></div>' + body + '<section id="source">source</section></div>';
const tick = () => new Promise(resolve => setTimeout(resolve, 20));
let checks = 0;
function ok(value, message) { assert.ok(value, message); checks++; }
function setup({withPrimary = true, withTemplate = true, withRoot = true, body} = {}) {
  const dom = new JSDOM('<!doctype html><html><head><title>東京都</title><link rel="canonical" href="https://bousai-matome.com/disaster-info/tokyo/"></head><body>' + (withRoot ? rootHtml(body === undefined ? (withPrimary ? primary : '') + about : body) : '') + (withTemplate ? template : '') + '</body></html>', {url:'https://bousai-matome.com/disaster-info/tokyo/', runScripts:'outside-only', pretendToBeVisual:true});
  const w = dom.window; w.__events = []; w.gtag = (...args) => w.__events.push(args); w.__io = [];
  w.IntersectionObserver = class { constructor(cb) { this.cb = cb; w.__io.push(this); } observe(el) { this.el = el; } unobserve() {} disconnect() {} };
  w.__mutations = 0; const NativeObserver = w.MutationObserver;
  // Native asynchronous delivery, including self-generated mutations.
  w.MutationObserver = class extends NativeObserver {
    constructor(cb) { super((records, observer) => { if (++w.__mutations > 200) throw new Error('Observer did not converge'); cb(records, observer); }); }
  };
  w.matchMedia = () => ({matches:false, addEventListener() {}, addListener() {}});
  return dom;
}
function parts(w) { const d = w.document, root = d.querySelector('.bousai-official-info'); return {root, cta:root?.querySelector('[data-bcc-surface="disaster_info_inline"]'), sns:d.getElementById('bousai-disaster-share'), primary:d.getElementById('primary'), about:d.getElementById('about'), slot:root?.querySelector('[data-bousai-slot]')}; }
const events = (w, name) => w.__events.filter(e => e[1] === name);
function checkOrder(w, label) {
  const p = parts(w);
  ok(p.cta && p.cta.parentNode === p.root, label + ': CTA at root');
  ok(!p.primary || p.primary.nextElementSibling === p.cta, label + ': primary before CTA');
  ok(p.cta.nextElementSibling === p.sns && p.sns.nextElementSibling === p.about, label + ': CTA -> SNS -> About');
  ok(w.document.querySelectorAll('[data-bcc-surface="disaster_info_inline"]').length === 1, label + ': one CTA');
}
(async () => {
  for (const withPrimary of [true, false]) for (const snsFirst of [true, false]) {
    const dom = setup({withPrimary}), w = dom.window, foreign = [...parts(w).root.children];
    w.eval(coreJs);
    for (const js of snsFirst ? [snsJs, ctaJs] : [ctaJs, snsJs]) w.eval(js);
    w.document.dispatchEvent(new w.Event('DOMContentLoaded')); await tick();
    checkOrder(w, 'initial ' + withPrimary + '/' + snsFirst);
    ok(foreign.every(el => el.parentNode === parts(w).root), 'foreign nodes retain parents');
    assert.deepEqual([...parts(w).root.children].filter(el => foreign.includes(el)), foreign); checks++;
    ok(parts(w).slot.firstElementChild.id === 'existing-weather', 'weather stays in slot');
    for (let i=0; i<3; i++) w.dispatchEvent(new w.PageTransitionEvent('pageshow', {persisted:true}));
    w.eval(ctaJs); await tick(); checkOrder(w, 'pageshow and script re-evaluation');
    const io = w.__io.at(-1);
    ok(events(w, 'bousai_check_cta_impression').length === 0, 'no fabricated impression');
    io.cb([{target:io.el, isIntersecting:true, intersectionRatio:0.49}]);
    ok(events(w, 'bousai_check_cta_impression').length === 0, '49% not counted');
    io.cb([{target:io.el, isIntersecting:true, intersectionRatio:0.5}]);
    io.cb([{target:io.el, isIntersecting:true, intersectionRatio:1}]);
    ok(events(w, 'bousai_check_cta_impression').length === 1, '50% counted once');
    const original = parts(w).cta; original.remove(); await tick();
    ok(parts(w).cta && parts(w).cta !== original, 'deleted CTA remounts');
    ok(events(w, 'bousai_check_cta_impression').length === 1, 'remount has no second pageview impression');
    const link = parts(w).cta.querySelector('a'); link.addEventListener('click', e => e.preventDefault()); link.click();
    ok(events(w, 'bousai_check_cta_click').length === 1, 'core click counted once');
    ok(events(w, 'bousai_check_cta_click')[0][2].location === 'disaster_info_inline', 'click location retained');
    parts(w).cta.insertAdjacentHTML('afterend', '<section id="late-source">late</section>');
    await tick(); ok(parts(w).cta.nextElementSibling === parts(w).sns, 'late sibling recovery');
    const mutations = w.__mutations; await tick(); ok(mutations === w.__mutations, 'native observers converge');
    dom.window.close();
  }
  for (const late of ['template', 'root', 'anchor']) {
    const dom = setup({withTemplate:late !== 'template', withRoot:late !== 'root', body:late === 'anchor' ? '' : undefined}), w = dom.window;
    w.eval(ctaJs); await tick(); ok(!parts(w).cta, late + ': no fabricated input');
    if (late === 'template') w.document.body.insertAdjacentHTML('beforeend', template);
    if (late === 'root') w.document.body.insertAdjacentHTML('afterbegin', rootHtml(primary + about));
    if (late === 'anchor') parts(w).root.insertAdjacentHTML('beforeend', about);
    w.eval(snsJs); w.document.dispatchEvent(new w.Event('DOMContentLoaded')); await tick(); checkOrder(w, late + ' arrives late');
    dom.window.close();
  }
  {
    const dom = setup({withPrimary:false}), w = dom.window;
    w.eval(ctaJs); w.eval(snsJs); w.document.dispatchEvent(new w.Event('DOMContentLoaded')); await tick();
    parts(w).about.insertAdjacentHTML('beforebegin', primary); await tick(); checkOrder(w, 'late primary');
    parts(w).primary.remove(); await tick(); checkOrder(w, 'removed primary');
    parts(w).root.outerHTML = rootHtml(primary + about);
    w.dispatchEvent(new w.PageTransitionEvent('pageshow', {persisted:true})); await tick(); checkOrder(w, 'root replacement');
    parts(w).primary.remove(); await tick(); checkOrder(w, 'root replacement then primary removal');
    parts(w).about.insertAdjacentHTML('beforebegin', primary); await tick(); checkOrder(w, 'root replacement then late primary');
    dom.window.close();
  }
  {
    const dom = setup({withTemplate:false}), w = dom.window;
    w.eval(ctaJs); w.eval(snsJs); w.document.dispatchEvent(new w.Event('DOMContentLoaded')); await tick();
    ok(!parts(w).cta, 'disabled CTA stays absent'); ok(parts(w).sns.nextElementSibling === parts(w).about, 'disabled CTA: SNS fallback');
    dom.window.close();
  }
  {
    const dom = setup({body:'<div><section class="bousai-bridge-section-primary" id="nested-primary">nested</section></div>' + about}), w = dom.window;
    w.eval(ctaJs); await tick(); ok(parts(w).cta.nextElementSibling === parts(w).about, 'nested primary ignored'); dom.window.close();
  }
  {
    const dom = setup(), w = dom.window;
    w.eval(ctaJs); w.eval(snsJs); w.eval(weatherJs); w.document.dispatchEvent(new w.Event('DOMContentLoaded')); await tick();
    checkOrder(w, 'coordinated weather'); ok(w.document.getElementById('bousai-weather-v1').parentNode === parts(w).slot, 'weather loads at original position');
    dom.window.close();
  }
  console.log('All ' + checks + ' lower-placement DOM checks passed.');
})().catch(error => {console.error(error); process.exitCode = 1;});
