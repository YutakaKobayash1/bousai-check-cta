<?php
/**
 * DISASTER-CTA-01: latest-disaster-info inline readiness CTA.
 *
 * Integration target: existing bousai-check-cta plugin, baseline v1.3.6.
 * This file is intentionally not a standalone plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Bousai_Check_CTA_Disaster_Info_Inline {
    const SURFACE = 'disaster_info_inline';
    const SHORTCODE = 'bousai_official_info';
    const PRIORITY = 20000;

    /**
     * Register hooks. Call once from the main plugin after existing setup.
     */
    public static function register() {
        add_filter( 'do_shortcode_tag', array( __CLASS__, 'inject_cta' ), self::PRIORITY, 4 );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 30 );
    }

    /**
     * Only the nationwide /disaster-info/ page and one-level prefecture routes.
     */
    public static function is_target_request() {
        if ( is_admin() ) {
            return false;
        }

        $uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $path = wp_parse_url( $uri, PHP_URL_PATH );

        if ( ! is_string( $path ) ) {
            return false;
        }

        return (bool) preg_match( '#^/disaster-info(?:/[^/]+)?/?$#', $path );
    }

    /**
     * Public display is an explicit human gate. While disabled, administrators
     * can still inspect the surface with ?bousai_cta_preview=1.
     */
    private static function is_surface_visible() {
        $saved = get_option( Bousai_Check_CTA::OPTION_KEY, array() );
        $options = wp_parse_args( is_array( $saved ) ? $saved : array(), Bousai_Check_CTA::defaults() );

        if ( ! empty( $options['disaster_info_inline_enabled'] ) ) {
            return true;
        }

        return is_user_logged_in()
            && current_user_can( 'manage_options' )
            && isset( $_GET['bousai_cta_preview'] )
            && '1' === sanitize_text_field( wp_unslash( $_GET['bousai_cta_preview'] ) );
    }

    /**
     * Inject immediately after the complete "current warnings / disaster info" section.
     * Existing downstream blocks are not reordered.
     */
    public static function inject_cta( $output, $tag, $attr, $m ) {
        if ( self::SHORTCODE !== $tag || ! self::is_target_request() || ! self::is_surface_visible() ) {
            return $output;
        }

        if ( ! is_string( $output ) || '' === $output ) {
            return $output;
        }

        if ( false !== strpos( $output, 'data-bcc-surface="' . self::SURFACE . '"' ) ) {
            return $output;
        }

        return self::insert_after_heading_section(
            $output,
            '現在発表されている警報・災害情報',
            self::render_cta()
        );
    }

    /**
     * Add scoped CSS/JS only on target routes.
     */
    public static function enqueue_assets() {
        if ( ! self::is_target_request() || ! self::is_surface_visible() ) {
            return;
        }

        $main_plugin_file = dirname( __DIR__ ) . '/bousai-check-cta.php';
        $version = class_exists( 'Bousai_Check_CTA' ) ? Bousai_Check_CTA::VERSION : '1.4.0-rc2';

        // The existing core tracker normally is not enqueued under /disaster-info/
        // because floating CTA rendering is intentionally excluded there. Reuse that
        // same tracker here so click measurement remains single-source and does not
        // require a second click handler.
        wp_enqueue_script(
            'bousai-check-cta',
            plugins_url( 'assets/js/bcc.js', $main_plugin_file ),
            array(),
            $version,
            true
        );

        wp_enqueue_style(
            'bousai-check-cta-disaster-info-inline',
            plugins_url( 'assets/css/bcc-disaster-info-inline.css', $main_plugin_file ),
            array(),
            $version
        );

        wp_enqueue_script(
            'bousai-check-cta-disaster-info-inline',
            plugins_url( 'assets/js/bcc-disaster-info-inline.js', $main_plugin_file ),
            array( 'bousai-check-cta' ),
            $version,
            true
        );
    }

    /**
     * Render the approved Phase 1 CTA.
     */
    private static function render_cta() {
        $url = home_url( '/check/' );

        ob_start();
        ?>
        <aside class="bcc-inline-cta bcc-inline-cta--disaster-info"
               data-bcc-surface="disaster_info_inline"
               aria-labelledby="bcc-disaster-info-inline-title">
            <div class="bcc-inline-cta__icon" aria-hidden="true">
                <svg viewBox="0 0 96 96" focusable="false" aria-hidden="true">
                    <path class="bcc-inline-cta__house" d="M12 43.5 47.5 14 83 43.5V82H57V59H38v23H12Z"/>
                    <path class="bcc-inline-cta__roof" d="m7 47 40.5-34L88 47"/>
                    <path class="bcc-inline-cta__shield" d="M68 39c7 5 13 6 18 6v14c0 12-7 21-18 26-11-5-18-14-18-26V45c5 0 11-1 18-6Z"/>
                    <path class="bcc-inline-cta__check" d="m59 61 6 6 12-14"/>
                </svg>
            </div>
            <div class="bcc-inline-cta__content">
                <p class="bcc-inline-cta__title" id="bcc-disaster-info-inline-title">災害情報を確認したら、自宅の備えもチェック</p>
                <p class="bcc-inline-cta__text">家族構成や住まいに合わせて、水・食料・トイレ・停電対策・避難など、必要な備えを確認できます。</p>
                <a class="bcc-inline-cta__button bcc-track"
                   href="<?php echo esc_url( $url ); ?>"
                   data-bcc-location="disaster_info_inline"
                   aria-label="わが家の防災チェックを始める">
                    <span>わが家の防災チェックを始める</span>
                    <span class="bcc-inline-cta__arrow" aria-hidden="true">›</span>
                </a>
                <p class="bcc-inline-cta__note">チェックに名前・住所などの入力は必要ありません。</p>
            </div>
        </aside>
        <?php
        return trim( ob_get_clean() );
    }

    /**
     * Find the section containing an exact heading string and insert immediately
     * after that section's matching closing tag. Nested <section> elements are
     * counted; if the structure cannot be resolved, fail closed and return HTML
     * unchanged.
     */
    private static function insert_after_heading_section( $html, $heading, $insert ) {
        $heading_pos = strpos( $html, $heading );
        if ( false === $heading_pos ) {
            return $html;
        }

        $before = substr( $html, 0, $heading_pos );
        $section_start = strripos( $before, '<section' );
        if ( false === $section_start ) {
            return $html;
        }

        $tail = substr( $html, $section_start );
        if ( ! preg_match_all( '#</?section\b[^>]*>#i', $tail, $matches, PREG_OFFSET_CAPTURE ) ) {
            return $html;
        }

        $depth = 0;
        foreach ( $matches[0] as $match ) {
            $tag_text = $match[0];
            $offset   = $match[1];

            if ( 0 === stripos( $tag_text, '</section' ) ) {
                $depth--;
            } else {
                $depth++;
            }

            if ( 0 === $depth ) {
                $insert_at = $section_start + $offset + strlen( $tag_text );
                return substr( $html, 0, $insert_at ) . $insert . substr( $html, $insert_at );
            }
        }

        return $html;
    }
}