<?php
/**
 * DISASTER-CTA-01: latest-disaster-info inline readiness CTA.
 *
 * RC5 architecture:
 * - Layout owner creates a stable root-level slot:
 *   [data-bousai-slot="post-current-actions"]
 * - This component renders an inert <template> in wp_footer.
 * - Client-side code mounts only this CTA into that slot.
 * - This component never reorders the current-information section or SNS share.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Bousai_Check_CTA_Disaster_Info_Inline {
    const SURFACE = 'disaster_info_inline';
    const SLOT = 'post-current-actions';
    const TEMPLATE_ID = 'bcc-disaster-info-inline-template';

    /**
     * Register only asset/template hooks.
     * No shortcode-output rewrite and no DOM ownership of disaster-info layout.
     */
    public static function register() {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 30 );
        add_action( 'wp_footer', array( __CLASS__, 'render_template' ), PHP_INT_MAX - 30 );
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
     * can inspect the surface with ?bousai_cta_preview=1.
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
     * Add scoped CSS/JS only on target routes.
     */
    public static function enqueue_assets() {
        if ( ! self::is_target_request() || ! self::is_surface_visible() ) {
            return;
        }

        $main_plugin_file = dirname( __DIR__ ) . '/bousai-check-cta.php';
        $version = class_exists( 'Bousai_Check_CTA' ) ? Bousai_Check_CTA::VERSION : '1.4.0-rc5';

        /*
         * Reuse the existing core click tracker. /disaster-info/ remains excluded
         * from the floating CTA itself; this enqueue does not render floating HTML.
         */
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
     * Render an inert template only.
     *
     * The template is intentionally outside .bousai-official-info and has no
     * visual effect until the RC5 client script mounts its first element into the
     * stable post-current-actions slot owned by the disaster-info layout.
     */
    public static function render_template() {
        if ( ! self::is_target_request() || ! self::is_surface_visible() ) {
            return;
        }

        ?>
        <template id="<?php echo esc_attr( self::TEMPLATE_ID ); ?>" data-bcc-template="<?php echo esc_attr( self::SURFACE ); ?>">
            <?php echo self::render_cta(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </template>
        <?php
    }

    /**
     * Render the approved Phase 1 CTA.
     */
    private static function render_cta() {
        $url = home_url( '/check/' );

        ob_start();
        ?>
        <aside class="bcc-inline-cta bcc-inline-cta--disaster-info"
               data-bcc-surface="<?php echo esc_attr( self::SURFACE ); ?>"
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
                   data-bcc-location="<?php echo esc_attr( self::SURFACE ); ?>"
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
}
