<?php
/** Standalone regression tests for the section insertion algorithm. */

function insert_after_heading_section( $html, $heading, $insert ) {
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
            $insert_at = $section_start + $offset + strlen( $tag_text );
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
    . '<section class="bousai-source-section"><div><h2>' . $heading . '</h2></div><div>current</div></section>'
    . $cta
    . '<section class="bousai-bridge-section"><h2>この状況で確認しておきたいこと</h2></section>'
    . '<section class="bousai-source-section"><h2>最近発表された東京都の災害情報</h2></section>'
    . '</div>';

assert_same( 'insert immediately after current section without reordering downstream', $expected, insert_after_heading_section( $fixture, $heading, $cta ) );

$nested = '<section><h2>' . $heading . '</h2><section><p>nested</p></section></section><div>after</div>';
assert_same( 'nested section counting', '<section><h2>' . $heading . '</h2><section><p>nested</p></section></section>' . $cta . '<div>after</div>', insert_after_heading_section( $nested, $heading, $cta ) );

$missing = '<section><h2>別の見出し</h2></section>';
assert_same( 'fail closed when heading missing', $missing, insert_after_heading_section( $missing, $heading, $cta ) );

echo "All insertion tests passed.\n";