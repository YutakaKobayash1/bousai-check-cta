<?php
/**
 * Static architecture regression for DISASTER-CTA-01 RC5.
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
$layout = file_get_contents( $root . '/integration/seo-p0-post-current-actions.patch.js' );
$sns = file_get_contents( $root . '/integration/bousai-disaster-share-v1.4.5.txt' );

must_contain( 'CTA renders inert template', $class, "add_action( 'wp_footer', array( __CLASS__, 'render_template' )" );
must_not_contain( 'CTA no longer rewrites shortcode output', $class, 'do_shortcode_tag' );
must_not_contain( 'CTA no longer has HTML section parser', $class, 'insert_before_heading_section_close' );

must_contain( 'CTA waits for stable slot', $js, '[data-bousai-slot="post-current-actions"]' );
must_contain( 'CTA mounts first within slot', $js, 'slot.insertBefore(cta, slot.firstChild)' );
must_contain( 'CTA disconnects wait observer', $js, 'waitForSlot.disconnect()' );
must_not_contain( 'CTA does not identify current section', $js, 'findCurrentSection' );
must_not_contain( 'CTA has no competing placement timer', $js, 'setTimeout(' );
must_not_contain( 'CTA has no last-child placement guard', $js, 'lastElementChild' );

must_contain( 'layout owns stable slot', $layout, 'data-bousai-slot="post-current-actions"' );
must_contain( 'layout moves slot after current', $layout, 'anchor = moveAfter(postCurrentActions, anchor)' );

must_contain( 'SNS preserves v1.4.4 five-button layout', $sns, 'grid-template-columns:repeat(5,minmax(0,1fr))' );
must_contain( 'SNS preserves v1.4.4 share-copy refinement', $sns, "var line1 = area + 'の最新災害情報を確認';" );
must_contain( 'SNS is slot aware', $sns, 'findPostCurrentActionsSlot' );
must_contain( 'SNS mounts at slot end', $sns, 'slot.appendChild(block)' );
must_contain( 'SNS preserves legacy fallback', $sns, 'current.parentNode.insertBefore(block, current.nextSibling)' );

echo "All RC5 slot-contract tests passed.\n";
