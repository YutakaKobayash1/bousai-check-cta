<?php
/** Standalone regression tests for the section insertion algorithm. */

function insert_before_heading_section_close( $html, $heading, $insert ) {
    $heading_pos = strpos( $html, $heading );
    if ( false === $heading_pos ) return $html;

    $before = substr( $html, 0, $heading_pos );
    $section_start = strripos( $before, '<section' );
    if ( false === $section_start ) return $html;

    $tail = substr( $html, $section_start );
    if ( ! preg_match_all( '#</?section\b[^>]*>#i', $tail, $matches, PREG_OFFSET_CAPTURE ) ) return $html;

    $depth = 0;
    foreach ( $matches[0] as $match ) {
        $tag_text = $match[0];
        $offset   = $match[1];
        $depth += ( 0 === stripos( $tag_text, '</section' ) ) ? -1 : 1;
        if ( 0 === $depth ) {
            $insert_at = $section_start + $offset;
            return substr( $html, 0, $insert_at ) . $insert . substr( $html, $insert_at );
        }
    }

    return $html;
}

function assert_same( $label, $expected, $actual ) {
    if ( $expected !== $actual ) {
        fwrite( STDERR, "FAIL: {$label}\nExpected: {$expected}\nActual:   {$actual}\n" );
        exit( 1 );
    }
    echo "PASS: {$label}\n";
}

$heading = '現在発表されている警報・災害情報';
$cta = '<aside data-bcc-surface="disaster_info_inline">CTA</aside>';

$fixture = '<div class="bousai-official-info">'
    . '<section class="bousai-source-section"><div><h2>' . $heading . '</h2></div><div>current</div></section>'
    . '<section class="bousai-bridge-section"><h2>この状況で確認しておきたいこと</h2></section>'
    . '<section class="bousai-source-section"><h2>最近発表された東京都の災害情報</h2></section>'
    . '</div>';

$expected = '<div class="bousai-official-info">'
    . '<section class="bousai-source-section"><div><h2>' . $heading . '</h2></div><div>current</div>' . $cta . '</section>'
    . '<section class="bousai-bridge-section"><h2>この状況で確認しておきたいこと</h2></section>'
    . '<section class="bousai-source-section"><h2>最近発表された東京都の災害情報</h2></section>'
    . '</div>';

assert_same( 'insert at visual end of current section without reordering downstream', $expected, insert_before_heading_section_close( $fixture, $heading, $cta ) );

$nested = '<section><h2>' . $heading . '</h2><section><p>nested</p></section></section><div>after</div>';
assert_same( 'nested section counting', '<section><h2>' . $heading . '</h2><section><p>nested</p></section>' . $cta . '</section><div>after</div>', insert_before_heading_section_close( $nested, $heading, $cta ) );

$missing = '<section><h2>別の見出し</h2></section>';
assert_same( 'fail closed when heading missing', $missing, insert_before_heading_section_close( $missing, $heading, $cta ) );


// Compatibility with the disaster-share plugin: it always places the SNS block
// immediately after the current section. Keeping the CTA inside the section means
// the final visual order is current info -> CTA -> SNS share.
$with_cta = insert_before_heading_section_close( $fixture, $heading, $cta );
$share = '<aside class="bousai-disaster-share">SNS</aside>';
$section_close = strpos( $with_cta, '</section>');
$after_close = $section_close + strlen( '</section>' );
$with_share = substr( $with_cta, 0, $after_close ) . $share . substr( $with_cta, $after_close );
$cta_pos = strpos( $with_share, 'data-bcc-surface="disaster_info_inline"' );
$share_pos = strpos( $with_share, 'class="bousai-disaster-share"' );
if ( false === $cta_pos || false === $share_pos || $cta_pos >= $share_pos ) {
    fwrite( STDERR, "FAIL: CTA must appear before SNS share block\n" );
    exit( 1 );
}
echo "PASS: CTA remains above SNS share block\n";

echo "All insertion tests passed.\n";