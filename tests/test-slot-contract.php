<?php
/**
 * Static architecture regression for DISASTER-CTA-02 v1.4.2-rc1.
 * This intentionally verifies ownership boundaries as source invariants.
 */

function must_contain( $label, $haystack, $needle ) {
    if ( false === strpos( $haystack, $needle ) ) {
        fwrite( STDERR, "FAIL: {$label} missing: {$needle}\n" );
        exit( 1 );
    }
    echo "PASS: {$label}\n";
}

function must_not_contain( $label, $haystack, $needle ) {
    if ( false !== strpos( $haystack, $needle ) ) {
        fwrite( STDERR, "FAIL: {$label} unexpectedly contains: {$needle}\n" );
        exit( 1 );
    }
    echo "PASS: {$label}\n";
}

$root = dirname( __DIR__ );

$class = file_get_contents( $root . '/includes/class-bcc-disaster-info-inline.php' );
$js = file_get_contents( $root . '/assets/js/bcc-disaster-info-inline.js' );
$css = file_get_contents( $root . '/assets/css/bcc-disaster-info-inline.css' );
$layout = file_get_contents( $root . '/integration/seo-p0-post-current-actions.patch.js' );
$sns = file_get_contents( $root . '/integration/bousai-disaster-share-v1.4.5.txt' );

must_contain( 'CTA renders inert template', $class, "add_action( 'wp_footer', array( __CLASS__, 'render_template' )" );
must_not_contain( 'CTA does not rewrite shortcode output', $class, 'do_shortcode_tag' );
must_not_contain( 'CTA has no HTML section parser', $class, 'insert_before_heading_section_close' );

must_contain( 'CTA uses primary root anchor', $js, ':scope > .bousai-bridge-section-primary' );
must_contain( 'CTA re-resolves footer template at mount time', $js, "document.getElementById(TEMPLATE_ID)" );
must_contain( 'CTA waits across initial body assembly', $js, "waitForMountInputs.observe(document.body || document.documentElement" );
must_contain( 'desktop button max width unchanged', $css, 'width: min(100%, 560px);' );
must_contain( 'mobile button remains full width', $css, 'width: 100%;' );
must_contain( 'CTA moves only itself to lower root anchor', $js, 'root.insertBefore(cta, before)' );
must_contain( 'CTA observes only root child list', $js, 'placementObserver.observe(root, { childList: true })' );
must_contain( 'CTA corrects only its own placement', $js, 'primary.nextElementSibling !== cta' );
must_contain( 'CTA disconnects initial wait observer', $js, 'waitForMountInputs.disconnect()' );
must_not_contain( 'CTA no longer forces first-child ownership', $js, 'slot.insertBefore(cta, slot.firstChild)' );
must_not_contain( 'CTA does not identify current section', $js, 'findCurrentSection' );
must_not_contain( 'CTA has no competing placement timer', $js, 'setTimeout(' );

must_contain( 'layout still owns stable slot', $layout, 'data-bousai-slot="post-current-actions"' );
must_contain( 'layout still moves slot after current', $layout, 'anchor = moveAfter(postCurrentActions, anchor)' );

must_contain( 'SNS baseline preserves five-button layout', $sns, 'grid-template-columns:repeat(5,minmax(0,1fr))' );
must_contain( 'SNS baseline preserves share-copy refinement', $sns, "var line1 = area + 'の最新災害情報を確認';" );
must_contain( 'SNS remains slot aware', $sns, 'findPostCurrentActionsSlot' );
must_contain( 'SNS still moves only itself into slot', $sns, 'slot.appendChild(block)' );
must_contain( 'SNS preserves legacy fallback', $sns, 'current.parentNode.insertBefore(block, current.nextSibling)' );

echo "All v1.4.2-rc1 architecture checks passed.\n";
