(function () {
	'use strict';

	function initBousaiCheckCTA() {
		var config = window.BousaiCheckCTA || {};
		var threshold = Number(config.scrollThreshold || 0);
		var floatingWrap = document.querySelector('[data-bcc-floating]');
		var spCloseButton = document.querySelector('[data-bcc-sp-close]');
		var spDismissKey = 'bousai_check_sp_cta_dismissed';
		var spDismissed = false;

		try {
			spDismissed = window.sessionStorage.getItem(spDismissKey) === '1';
		} catch (e) {
			spDismissed = false;
		}

		function scrollPercent() {
			var doc = document.documentElement;
			var body = document.body;
			var scrollTop = window.pageYOffset || doc.scrollTop || body.scrollTop || 0;
			var scrollHeight = Math.max(
				body.scrollHeight,
				doc.scrollHeight,
				body.offsetHeight,
				doc.offsetHeight,
				body.clientHeight,
				doc.clientHeight
			);
			var viewport = window.innerHeight || doc.clientHeight || 0;
			var scrollable = Math.max(1, scrollHeight - viewport);
			return Math.min(100, Math.max(0, (scrollTop / scrollable) * 100));
		}

		function getContentRight() {
			var selectors = [
				'.content-in',
				'#content',
				'.site-content',
				'.container',
				'.wrap',
				'main'
			];

			var viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
			var bestRect = null;

			for (var i = 0; i < selectors.length; i++) {
				var nodes = document.querySelectorAll(selectors[i]);
				for (var j = 0; j < nodes.length; j++) {
					var rect = nodes[j].getBoundingClientRect();
					if (
						rect.width >= 700 &&
						rect.width <= viewportWidth - 40 &&
						rect.right > viewportWidth * 0.55 &&
						rect.left >= 0
					) {
						if (!bestRect || rect.width > bestRect.width) {
							bestRect = rect;
						}
					}
				}
			}

			return bestRect ? bestRect.right : null;
		}

		function setPcVariant(variant) {
			if (!floatingWrap) return;

			floatingWrap.classList.toggle('is-pc-large', variant === 'large');
			floatingWrap.classList.toggle('is-pc-tab', variant === 'tab');
		}

		function positionPcCard() {
			if (!floatingWrap || window.innerWidth < 1180) return;

			var mode = floatingWrap.getAttribute('data-pc-mode') || 'auto';
			var configuredWidth = Number(floatingWrap.getAttribute('data-pc-width') || 280);
			var gap = Number(floatingWrap.getAttribute('data-pc-gap') || 24);
			var viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
			var contentRight = getContentRight();
			var edgeMargin = 12;
			var minLargeWidth = 220;
			var desiredLeft = null;
			var availableWidth = 0;
			var effectiveWidth = configuredWidth;

			if (contentRight !== null) {
				desiredLeft = contentRight + gap;
				availableWidth = Math.floor(viewportWidth - desiredLeft - edgeMargin);
			}

			if (mode === 'large') {
				setPcVariant('large');
				floatingWrap.style.setProperty('--bcc-pc-width', configuredWidth + 'px');

				if (desiredLeft !== null && availableWidth >= configuredWidth) {
					floatingWrap.style.left = Math.round(desiredLeft) + 'px';
					floatingWrap.style.right = 'auto';
				} else {
					floatingWrap.style.left = 'auto';
					floatingWrap.style.right = '18px';
				}
				return;
			}

			if (mode === 'tab') {
				setPcVariant('tab');
				floatingWrap.style.left = 'auto';
				floatingWrap.style.right = '0';
				return;
			}

			/*
			 * Auto mode:
			 * Do not switch to the small tab merely because the configured card is a few
			 * pixels too wide. Shrink the large card to the actual side margin first.
			 * Only use the small tab when even a 220px card cannot fit safely.
			 */
			if (desiredLeft !== null && availableWidth >= minLargeWidth) {
				effectiveWidth = Math.min(configuredWidth, availableWidth);
				setPcVariant('large');
				floatingWrap.style.setProperty('--bcc-pc-width', effectiveWidth + 'px');
				floatingWrap.style.left = Math.round(desiredLeft) + 'px';
				floatingWrap.style.right = 'auto';
			} else {
				setPcVariant('tab');
				floatingWrap.style.setProperty('--bcc-pc-width', configuredWidth + 'px');
				floatingWrap.style.left = 'auto';
				floatingWrap.style.right = '0';
			}
		}

		function getDetectedBottomOffset() {
			if (!floatingWrap || window.innerWidth >= 1180) return 0;

			var selectors = [
				'.mobile-menu-buttons',
				'#navi-footer',
				'.navi-footer',
				'.footer-mobile-buttons',
				'.mobile-footer-menu',
				'.bottom-menu',
				'.footer-fixed-menu'
			];

			var maxHeight = 0;

			selectors.forEach(function (selector) {
				document.querySelectorAll(selector).forEach(function (el) {
					if (!el || floatingWrap.contains(el)) return;

					var style = window.getComputedStyle(el);
					if (style.display === 'none' || style.visibility === 'hidden') return;
					if (style.position !== 'fixed' && style.position !== 'sticky') return;

					var rect = el.getBoundingClientRect();
					if (rect.height <= 0) return;

					var nearBottom = rect.bottom >= (window.innerHeight - 4);
					if (!nearBottom) return;

					maxHeight = Math.max(maxHeight, Math.ceil(rect.height));
				});
			});

			return maxHeight;
		}

		function updateFloating() {
			if (!floatingWrap) return;

			if (window.innerWidth < 1180) {
				floatingWrap.classList.remove('is-pc-large', 'is-pc-tab');
				floatingWrap.style.left = '';
				floatingWrap.style.right = '';
			}

			if (window.innerWidth < 1180 && spDismissed) {
				floatingWrap.classList.remove('is-visible');
				floatingWrap.hidden = true;
				return;
			}

			if (scrollPercent() >= threshold) {
				floatingWrap.hidden = false;
				positionPcCard();
				var detectedBottomOffset = getDetectedBottomOffset();
				floatingWrap.style.setProperty('--bcc-sp-detected-offset', detectedBottomOffset + 'px');
				window.requestAnimationFrame(function () {
					floatingWrap.classList.add('is-visible');
				});
			} else {
				floatingWrap.classList.remove('is-visible');
			}
		}

		var ticking = false;
		function onScroll() {
			if (ticking) return;
			ticking = true;
			window.requestAnimationFrame(function () {
				updateFloating();
				ticking = false;
			});
		}

		if (spCloseButton) {
			spCloseButton.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();

				spDismissed = true;

				try {
					window.sessionStorage.setItem(spDismissKey, '1');
				} catch (e) {}

				if (floatingWrap) {
					floatingWrap.classList.remove('is-visible');
					floatingWrap.hidden = true;
				}
			});
		}

		if (floatingWrap) {
			window.addEventListener('scroll', onScroll, { passive: true });
			window.addEventListener('resize', onScroll, { passive: true });
			updateFloating();
		}

		var footer = document.querySelector('#footer, footer');
		if (footer && 'IntersectionObserver' in window) {
			var footerObserver = new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					document.documentElement.classList.toggle('bcc-footer-visible', entry.isIntersecting);
				});
			}, { threshold: 0.01 });
			footerObserver.observe(footer);
		}

		document.addEventListener('click', function (event) {
			var target = event.target;
			if (!target || !target.closest) return;

			var link = target.closest('.bcc-track');
			if (!link) return;

			var location = link.getAttribute('data-bcc-location') || 'unknown';
			var href = link.href || '';

			if (typeof window.gtag === 'function') {
				window.gtag('event', 'bousai_check_cta_click', {
					location: location,
					link_url: href
				});
			} else if (Array.isArray(window.dataLayer)) {
				window.dataLayer.push({
					event: 'bousai_check_cta_click',
					location: location,
					link_url: href
				});
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initBousaiCheckCTA, { once: true });
	} else {
		initBousaiCheckCTA();
	}
})();