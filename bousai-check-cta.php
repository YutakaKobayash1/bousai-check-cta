<?php
/**
 * Plugin Name: 防災のまとめ 防災チェック導線
 * Description: 「防災チェック」固定ページへのPC追尾CTA、SP下部固定CTA、TOPカード、記事末CTAを提供します。
 * Version: 1.3.6
 * Author: 防災のまとめ
 * Text Domain: bousai-check-cta
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bousai_Check_CTA {
	const VERSION = '1.3.6';
	const OPTION_KEY = 'bousai_check_cta_options';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_floating_cta' ), 5 );

		add_shortcode( 'bousai_check_top_card', array( $this, 'shortcode_top_card' ) );
		add_shortcode( 'bousai_check_article_cta', array( $this, 'shortcode_article_cta' ) );

		add_filter( 'the_content', array( $this, 'append_article_cta' ), 30 );
	}

	public static function activate() {
		if ( false === get_option( self::OPTION_KEY, false ) ) {
			add_option( self::OPTION_KEY, self::defaults(), '', false );
		}
	}

	public static function defaults() {
		return array(
			'target_page_id'          => 0,
			'floating_enabled'        => 1,
			'article_auto_enabled'    => 0,
			'preview_mode'            => 1,
			'scroll_threshold'        => 25,
			'excluded_paths'          => "/disaster-info/",
			'excluded_category_slugs' => '',
			'pc_display_mode'         => 'auto',
			'pc_tab_text'             => '防災チェック',
			'pc_tab_width'            => 54,
			'pc_tab_height'           => 150,
			'pc_tab_writing_mode'     => 'vertical',
			'pc_banner_image_id'      => 0,
			'pc_banner_title'         => 'わが家の防災チェック',
			'pc_title_align'          => 'left',
			'pc_banner_text'          => '家族構成や暮らしに合わせて、今の備えを確認できます。',
			'pc_banner_button'        => 'チェックする',
			'pc_button_align'         => 'left',
			'pc_button_bg_color'      => '#2f8f79',
			'pc_button_text_color'    => '#ffffff',
			'pc_banner_width'         => 280,
			'pc_banner_gap'           => 24,
			'sp_banner_text'          => 'わが家の防災をチェック',
			'sp_bg_color'             => '#2f8f79',
			'sp_text_color'           => '#ffffff',
			'sp_height'               => 56,
			'sp_radius'               => 14,
			'sp_bottom_offset'        => 8,
		);
	}

	private function options() {
		$saved = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	private function target_page_id() {
		$options = $this->options();
		return absint( $options['target_page_id'] );
	}

	private function target_url() {
		$page_id = $this->target_page_id();
		if ( ! $page_id ) {
			return '';
		}
		$url = get_permalink( $page_id );
		return $url ? $url : '';
	}

	private function preview_allowed() {
		$options = $this->options();

		if ( empty( $options['preview_mode'] ) ) {
			return true;
		}

		return is_user_logged_in()
			&& current_user_can( 'manage_options' )
			&& isset( $_GET['bousai_cta_preview'] )
			&& '1' === sanitize_text_field( wp_unslash( $_GET['bousai_cta_preview'] ) );
	}

	private function current_path() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		return $path ? $path : '/';
	}

	private function is_excluded_path() {
		$options = $this->options();
		$raw = isset( $options['excluded_paths'] ) ? (string) $options['excluded_paths'] : '';
		$paths = preg_split( '/[\r\n,]+/', $raw );
		$current = trailingslashit( $this->current_path() );

		foreach ( $paths as $path ) {
			$path = trim( $path );
			if ( '' === $path ) {
				continue;
			}
			if ( '/' !== substr( $path, 0, 1 ) ) {
				$path = '/' . $path;
			}
			$path = trailingslashit( $path );

			if ( 0 === strpos( $current, $path ) ) {
				return true;
			}
		}

		return false;
	}

	private function is_excluded_category( $post_id ) {
		$options = $this->options();
		$raw = isset( $options['excluded_category_slugs'] ) ? (string) $options['excluded_category_slugs'] : '';
		$slugs = array_filter( array_map( 'sanitize_title', preg_split( '/[\r\n,]+/', $raw ) ) );

		if ( empty( $slugs ) ) {
			return false;
		}

		return has_category( $slugs, $post_id );
	}

	private function should_render_global() {
		if ( is_admin() || wp_doing_ajax() || is_feed() ) {
			return false;
		}

		if ( ! $this->target_url() ) {
			return false;
		}

		if ( ! $this->preview_allowed() ) {
			return false;
		}

		$page_id = $this->target_page_id();
		if ( $page_id && is_page( $page_id ) ) {
			return false;
		}

		if ( $this->is_excluded_path() ) {
			return false;
		}

		if ( ! is_singular() && ! is_front_page() && ! is_home() ) {
			return false;
		}

		return true;
	}


	public function admin_assets( $hook_suffix ) {
		if ( 'settings_page_bousai-check-cta' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		wp_add_inline_script(
			'wp-color-picker',
			"(function($){
				$(function(){
					var frame;

					function refreshSpPreview(){
						var text = $('#bcc-sp-banner-text').val() || 'わが家の防災をチェック';
						var bg = $('#bcc-sp-bg-color').val() || '#2f8f79';
						var color = $('#bcc-sp-text-color').val() || '#ffffff';
						var height = parseInt($('#bcc-sp-height').val(), 10) || 56;
						var radius = parseInt($('#bcc-sp-radius').val(), 10);
						if (isNaN(radius)) { radius = 14; }
						var bottomOffset = parseInt($('#bcc-sp-bottom-offset').val(), 10);
						if (isNaN(bottomOffset)) { bottomOffset = 8; }

						$('#bcc-sp-preview-text').text(text);
						$('#bcc-sp-preview-banner').css({
							'background-color': bg,
							'color': color,
							'min-height': height + 'px',
							'border-radius': radius + 'px',
							'margin-top': bottomOffset + 'px'
						});
					}

					function refreshPcPreview(){
						var tabText = $('#bcc-pc-tab-text').val() || '防災チェック';
						var tabWidth = parseInt($('#bcc-pc-tab-width').val(), 10) || 54;
						var tabHeight = parseInt($('#bcc-pc-tab-height').val(), 10) || 150;
						var tabWriting = $('#bcc-pc-tab-writing-mode').val() || 'vertical';
						var title = $('#bcc-pc-banner-title').val() || '';
						var titleAlign = $('#bcc-pc-title-align').val() || 'left';
						var text = $('#bcc-pc-banner-text').val() || '';
						var button = $('#bcc-pc-banner-button').val() || 'チェックする';
						var align = $('#bcc-pc-button-align').val() || 'left';
						var bg = $('#bcc-pc-button-bg-color').val() || '#2f8f79';
						var color = $('#bcc-pc-button-text-color').val() || '#ffffff';
						var width = parseInt($('#bcc-pc-banner-width').val(), 10) || 280;
						var justify = 'flex-start';

						if (align === 'center') { justify = 'center'; }
						if (align === 'right') { justify = 'flex-end'; }

						$('#bcc-pc-preview-tab-text').text(tabText);
						$('#bcc-pc-preview-tab').css({
							'width': tabWidth + 'px',
							'height': tabHeight + 'px',
							'flex-direction': tabWriting === 'vertical' ? 'column' : 'row'
						});
						$('#bcc-pc-preview-tab-text').css({
							'writing-mode': tabWriting === 'vertical' ? 'vertical-rl' : 'horizontal-tb',
							'text-orientation': tabWriting === 'vertical' ? 'upright' : 'mixed'
						});
						$('#bcc-pc-preview-card').css('width', width + 'px');
						$('#bcc-pc-preview-title')
							.text(title)
							.toggle(title !== '')
							.css('text-align', titleAlign);
						$('#bcc-pc-preview-text').text(text).toggle(text !== '');
						$('#bcc-pc-preview-button-label').text(button);
						$('#bcc-pc-preview-button').css({
							'background-color': bg,
							'color': color,
							'justify-content': justify
						});
						$('#bcc-pc-preview-tab-text').parent().css({
							'background-color': bg,
							'color': color
						});
					}

					function refreshAllPreviews(){
						refreshPcPreview();
						refreshSpPreview();
					}

					$('.bcc-color-field').wpColorPicker({
						change: function(event, ui){
							$(event.target).val(ui.color.toString());
							refreshAllPreviews();
						},
						clear: function(){
							setTimeout(refreshAllPreviews, 0);
						}
					});

					$('#bcc-sp-banner-text, #bcc-sp-bg-color, #bcc-sp-text-color, #bcc-sp-height, #bcc-sp-radius, #bcc-sp-bottom-offset')
						.on('input change keyup', refreshSpPreview);

					$('#bcc-pc-tab-text, #bcc-pc-tab-width, #bcc-pc-tab-height, #bcc-pc-tab-writing-mode, #bcc-pc-banner-title, #bcc-pc-title-align, #bcc-pc-banner-text, #bcc-pc-banner-button, #bcc-pc-button-align, #bcc-pc-button-bg-color, #bcc-pc-button-text-color, #bcc-pc-banner-width')
						.on('input change keyup', refreshPcPreview);

					$('#bcc-upload-pc-banner').on('click', function(e){
						e.preventDefault();

						if ( frame ) {
							frame.open();
							return;
						}

						frame = wp.media({
							title: 'PCバナー画像を選択',
							button: { text: 'この画像を使う' },
							multiple: false,
							library: { type: 'image' }
						});

						frame.on('select', function(){
							var attachment = frame.state().get('selection').first().toJSON();
							$('#bcc-pc-banner-image-id').val(attachment.id);
							$('#bcc-pc-banner-preview')
								.attr('src', attachment.url)
								.removeAttr('hidden');
							$('#bcc-pc-card-preview-image')
								.attr('src', attachment.url)
								.removeAttr('hidden');
							$('#bcc-pc-card-preview-image-wrap').removeAttr('hidden');
							$('#bcc-pc-card-preview-icon').attr('hidden', true);
							$('#bcc-remove-pc-banner').prop('hidden', false);
							refreshPcPreview();
						});

						frame.open();
					});

					$('#bcc-remove-pc-banner').on('click', function(e){
						e.preventDefault();
						$('#bcc-pc-banner-image-id').val('0');
						$('#bcc-pc-banner-preview').attr('src', '').attr('hidden', true);
						$('#bcc-pc-card-preview-image').attr('src', '').attr('hidden', true);
						$('#bcc-pc-card-preview-image-wrap').attr('hidden', true);
						$('#bcc-pc-card-preview-icon').removeAttr('hidden');
						$(this).prop('hidden', true);
						refreshPcPreview();
					});

					refreshAllPreviews();
				});
			})(jQuery);"
		);
	}

	public function register_settings() {
		register_setting(
			'bousai_check_cta_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'default'           => self::defaults(),
			)
		);
	}

	public function sanitize_options( $input ) {
		$defaults = self::defaults();
		$output = $defaults;

		$output['target_page_id'] = isset( $input['target_page_id'] ) ? absint( $input['target_page_id'] ) : 0;
		$output['floating_enabled'] = ! empty( $input['floating_enabled'] ) ? 1 : 0;
		$output['article_auto_enabled'] = ! empty( $input['article_auto_enabled'] ) ? 1 : 0;
		$output['preview_mode'] = ! empty( $input['preview_mode'] ) ? 1 : 0;

		$threshold = isset( $input['scroll_threshold'] ) ? absint( $input['scroll_threshold'] ) : 25;
		$output['scroll_threshold'] = min( 90, max( 0, $threshold ) );

		$output['excluded_paths'] = isset( $input['excluded_paths'] )
			? sanitize_textarea_field( $input['excluded_paths'] )
			: $defaults['excluded_paths'];

		$output['excluded_category_slugs'] = isset( $input['excluded_category_slugs'] )
			? sanitize_textarea_field( $input['excluded_category_slugs'] )
			: '';

		$allowed_pc_modes = array( 'auto', 'large', 'tab' );
		$pc_display_mode = isset( $input['pc_display_mode'] ) ? sanitize_key( $input['pc_display_mode'] ) : $defaults['pc_display_mode'];
		$output['pc_display_mode'] = in_array( $pc_display_mode, $allowed_pc_modes, true )
			? $pc_display_mode
			: $defaults['pc_display_mode'];

		$output['pc_tab_text'] = isset( $input['pc_tab_text'] )
			? sanitize_text_field( $input['pc_tab_text'] )
			: $defaults['pc_tab_text'];

		$pc_tab_width = isset( $input['pc_tab_width'] ) ? absint( $input['pc_tab_width'] ) : $defaults['pc_tab_width'];
		$output['pc_tab_width'] = min( 100, max( 40, $pc_tab_width ) );

		$pc_tab_height = isset( $input['pc_tab_height'] ) ? absint( $input['pc_tab_height'] ) : $defaults['pc_tab_height'];
		$output['pc_tab_height'] = min( 240, max( 100, $pc_tab_height ) );

		$allowed_tab_writing_modes = array( 'vertical', 'horizontal' );
		$pc_tab_writing_mode = isset( $input['pc_tab_writing_mode'] ) ? sanitize_key( $input['pc_tab_writing_mode'] ) : $defaults['pc_tab_writing_mode'];
		$output['pc_tab_writing_mode'] = in_array( $pc_tab_writing_mode, $allowed_tab_writing_modes, true )
			? $pc_tab_writing_mode
			: $defaults['pc_tab_writing_mode'];

		$output['pc_banner_image_id'] = isset( $input['pc_banner_image_id'] ) ? absint( $input['pc_banner_image_id'] ) : 0;
		$output['pc_banner_title'] = isset( $input['pc_banner_title'] ) ? sanitize_text_field( $input['pc_banner_title'] ) : $defaults['pc_banner_title'];

		$allowed_title_alignments = array( 'left', 'center', 'right' );
		$pc_title_align = isset( $input['pc_title_align'] ) ? sanitize_key( $input['pc_title_align'] ) : $defaults['pc_title_align'];
		$output['pc_title_align'] = in_array( $pc_title_align, $allowed_title_alignments, true )
			? $pc_title_align
			: $defaults['pc_title_align'];

		$output['pc_banner_text'] = isset( $input['pc_banner_text'] ) ? sanitize_text_field( $input['pc_banner_text'] ) : $defaults['pc_banner_text'];
		$output['pc_banner_button'] = isset( $input['pc_banner_button'] ) ? sanitize_text_field( $input['pc_banner_button'] ) : $defaults['pc_banner_button'];

		$allowed_alignments = array( 'left', 'center', 'right' );
		$pc_button_align = isset( $input['pc_button_align'] ) ? sanitize_key( $input['pc_button_align'] ) : $defaults['pc_button_align'];
		$output['pc_button_align'] = in_array( $pc_button_align, $allowed_alignments, true )
			? $pc_button_align
			: $defaults['pc_button_align'];

		$pc_button_bg = isset( $input['pc_button_bg_color'] ) ? sanitize_hex_color( $input['pc_button_bg_color'] ) : $defaults['pc_button_bg_color'];
		$output['pc_button_bg_color'] = $pc_button_bg ? $pc_button_bg : $defaults['pc_button_bg_color'];

		$pc_button_text = isset( $input['pc_button_text_color'] ) ? sanitize_hex_color( $input['pc_button_text_color'] ) : $defaults['pc_button_text_color'];
		$output['pc_button_text_color'] = $pc_button_text ? $pc_button_text : $defaults['pc_button_text_color'];

		$pc_width = isset( $input['pc_banner_width'] ) ? absint( $input['pc_banner_width'] ) : 280;
		$output['pc_banner_width'] = min( 420, max( 220, $pc_width ) );

		$pc_gap = isset( $input['pc_banner_gap'] ) ? absint( $input['pc_banner_gap'] ) : 24;
		$output['pc_banner_gap'] = min( 80, max( 8, $pc_gap ) );

		$output['sp_banner_text'] = isset( $input['sp_banner_text'] )
			? sanitize_text_field( $input['sp_banner_text'] )
			: $defaults['sp_banner_text'];

		$sp_bg = isset( $input['sp_bg_color'] ) ? sanitize_hex_color( $input['sp_bg_color'] ) : $defaults['sp_bg_color'];
		$output['sp_bg_color'] = $sp_bg ? $sp_bg : $defaults['sp_bg_color'];

		$sp_text = isset( $input['sp_text_color'] ) ? sanitize_hex_color( $input['sp_text_color'] ) : $defaults['sp_text_color'];
		$output['sp_text_color'] = $sp_text ? $sp_text : $defaults['sp_text_color'];

		$sp_height = isset( $input['sp_height'] ) ? absint( $input['sp_height'] ) : 56;
		$output['sp_height'] = min( 76, max( 48, $sp_height ) );

		$sp_radius = isset( $input['sp_radius'] ) ? absint( $input['sp_radius'] ) : 14;
		$output['sp_radius'] = min( 30, max( 0, $sp_radius ) );

		$sp_bottom_offset = isset( $input['sp_bottom_offset'] ) ? absint( $input['sp_bottom_offset'] ) : 8;
		$output['sp_bottom_offset'] = min( 80, max( 0, $sp_bottom_offset ) );

		return $output;
	}

	public function admin_menu() {
		add_options_page(
			'防災チェック導線',
			'防災チェック導線',
			'manage_options',
			'bousai-check-cta',
			array( $this, 'settings_page' )
		);
	}

	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = $this->options();
		$pages = get_pages(
			array(
				'sort_column' => 'post_title',
				'sort_order'  => 'ASC',
				'post_status' => array( 'publish', 'draft', 'private' ),
			)
		);

		$preview_url = add_query_arg( 'bousai_cta_preview', '1', home_url( '/' ) );
		?>
		<div class="wrap">
			<h1>防災チェック導線</h1>
			<p>PC追尾CTA、SP下部固定CTA、TOPカード、記事末CTAをまとめて管理します。</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'bousai_check_cta_group' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="bcc-target-page">リンク先の固定ページ</label></th>
						<td>
							<select id="bcc-target-page" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[target_page_id]">
								<option value="0">— 防災チェック固定ページを選択 —</option>
								<?php foreach ( $pages as $page ) : ?>
									<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( absint( $options['target_page_id'] ), $page->ID ); ?>>
										<?php echo esc_html( $page->post_title . '（ID:' . $page->ID . ' / ' . $page->post_status . '）' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">このページ自身では固定CTA・記事末CTAを自動的に表示しません。</p>
						</td>
					</tr>

					<tr>
						<th scope="row">固定CTA</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[floating_enabled]" value="1" <?php checked( ! empty( $options['floating_enabled'] ) ); ?>>
								PC右追尾／SP下部固定CTAを有効にする
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bcc-scroll-threshold">表示開始スクロール率</label></th>
						<td>
							<input id="bcc-scroll-threshold" type="number" min="0" max="90" step="1"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[scroll_threshold]"
								value="<?php echo esc_attr( $options['scroll_threshold'] ); ?>"> %
							<p class="description">初期推奨値は25%。ページを少し読んでから表示します。</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bcc-pc-display-mode">PC表示モード</label></th>
						<td>
							<select id="bcc-pc-display-mode"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pc_display_mode]">
								<option value="auto" <?php selected( $options['pc_display_mode'], 'auto' ); ?>>自動（推奨）</option>
								<option value="large" <?php selected( $options['pc_display_mode'], 'large' ); ?>>大型カード固定</option>
								<option value="tab" <?php selected( $options['pc_display_mode'], 'tab' ); ?>>小型タブ固定</option>
							</select>
							<p class="description">「自動」では本文右側に十分な余白がある時だけ大型カードを表示し、足りない画面では小型タブへ切り替えます。</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bcc-pc-tab-text">PC小型タブ文言</label></th>
						<td>
							<input id="bcc-pc-tab-text" class="regular-text" type="text"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pc_tab_text]"
								value="<?php echo esc_attr( $options['pc_tab_text'] ); ?>">
							<p class="description">自動切替または「小型タブ固定」で使う短い文言です。推奨は「防災チェック」。</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bcc-pc-tab-width">PC小型タブ 横幅</label></th>
						<td>
							<input id="bcc-pc-tab-width" type="number" min="40" max="100" step="2"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pc_tab_width]"
								value="<?php echo esc_attr( $options['pc_tab_width'] ); ?>"> px
							<p class="description">40〜100px。縦長タブの初期値は54pxです。</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bcc-pc-tab-height">PC小型タブ 高さ</label></th>
						<td>
							<input id="bcc-pc-tab-height" type="number" min="100" max="240" step="5"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pc_tab_height]"
								value="<?php echo esc_attr( $options['pc_tab_height'] ); ?>"> px
							<p class="description">100〜240px。初期値は150pxです。</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bcc-pc-tab-writing-mode">PC小型タブ 文字方向</label></th>
						<td>
							<select id="bcc-pc-tab-writing-mode"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pc_tab_writing_mode]">
								<option value="vertical" <?php selected( $options['pc_tab_writing_mode'], 'vertical' ); ?>>縦書き</option>
								<option value="horizontal" <?php selected( $options['pc_tab_writing_mode'], 'horizontal' ); ?>>横書き</option>
							</select>
							<p class="description">右端の小型タブは「縦書き」がおすすめです。</p>
						</td>
					</tr>

					<tr>
						<th scope="row">PCバナー画像</th>
						<td>
							<?php
							$pc_image_id  = absint( $options['pc_banner_image_id'] );
							$pc_image_url = $pc_image_id ? wp_get_attachment_image_url( $pc_image_id, 'large' ) : '';
							?>
							<input type="hidden" id="bcc-pc-banner-image-id"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pc_banner_image_id]"
								value="<?php echo esc_attr( $pc_image_id ); ?>">
							<p>
								<img id="bcc-pc-banner-preview"
									src="<?php echo esc_url( $pc_image_url ); ?>"
									alt=""
									style="display:block;max-width:320px;height:auto;margin:0 0 12px;border:1px solid #ccd0d4;border-radius:8px;"
									<?php echo $pc_image_url ? '' : 'hidden'; ?>>
							</p>
							<button type="button" class="button" id="bcc-upload-pc-banner">メディアライブラリから画像を選ぶ</button>
							<button type="button" class="button-link-delete" id="bcc-remove-pc-banner" <?php echo $pc_image_url ? '' : 'hidden'; ?>>画像を外す</button>
							<p class="description">未設定なら従来のテキストCTAを表示します。画像を入れるとPCだけ画像入りカードになります。SPは今までどおりテキスト固定CTAです。</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bcc-pc-banner-title">PCバナー見出し</label></th>
						<td>
							<input id="bcc-pc-banner-title" class="regular-text" type="text"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pc_banner_title]"
								value="<?php echo esc_attr( $options['pc_banner_title'] ); ?>">
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bcc-pc-title-align">PCバナー見出し位置</label></th>
						<td>
							<select id="bcc-pc-title-align"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pc_title_align]">
								<option value="left" <?php selected( $options['pc_title_align'], 'left' ); ?>>左寄せ</option>
								<option value="center" <?php selected( $options['pc_title_align'], 'center' ); ?>>中央</option>
								<option value="right" <?php selected( $options['pc_title_align'], 'right' ); ?>>右寄せ</option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bcc-pc-banner-text">PCバナー説明文</label></th>
						<td>
							<input id="bcc-pc-banner-text" class="large-text" type="text"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pc_banner_text]"
								value="<?php echo esc_attr( $options['pc_banner_text'] ); ?>">
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bcc-pc-banner-button">PCバナーボタン文言</label></th>
						<td>
							<input id="bcc-pc-banner-button" class="regular-text" type="text"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pc_banner_button]"
								value="<?php echo esc_attr( $options['pc_banner_button'] ); ?>">
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bcc-pc-button-align">PC CTA文字・矢印位置</label></th>
						<td>
							<select id="bcc-pc-button-align"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pc_button_align]">
								<option value="left" <?php selected( $options['pc_button_align'], 'left' ); ?>>左寄せ</option>
								<option value="center" <?php selected( $options['pc_button_align'], 'center' ); ?>>中央</option>
								<option value="right" <?php selected( $options['pc_button_align'], 'right' ); ?>>右寄せ</option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bcc-pc-button-bg-color">PC CTA背景色</label></th>
						<td>
							<input id="bcc-pc-button-bg-color" class="bcc-color-field" type="text"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pc_button_bg_color]"
								value="<?php echo esc_attr( $options['pc_button_bg_color'] ); ?>"
								data-default-color="#2f8f79">
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bcc-pc-button-text-color">PC CTA文字色</label></th>
						<td>
							<input id="bcc-pc-button-text-color" class="bcc-color-field" type="text"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pc_button_text_color]"
								value="<?php echo esc_attr( $options['pc_button_text_color'] ); ?>"
								data-default-color="#ffffff">
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bcc-pc-banner-width">PCバナー横幅</label></th>
						<td>
							<input id="bcc-pc-banner-width" type="number" min="220" max="420" step="10"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pc_banner_width]"
								value="<?php echo esc_attr( $options['pc_banner_width'] ); ?>"> px
							<p class="description">220〜420px。初期値280px。</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bcc-pc-banner-gap">本文からの間隔</label></th>
						<td>
							<input id="bcc-pc-banner-gap" type="number" min="8" max="80" step="4"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pc_banner_gap]"
								value="<?php echo esc_attr( $options['pc_banner_gap'] ); ?>"> px
							<p class="description">PCでは画面右端ではなく、メインコンテンツ右端からこの距離に追尾させます。</p>
						</td>
					</tr>

					<tr>
						<th scope="row">PCバナー プレビュー</th>
						<td>
							<div style="display:inline-block;padding:22px;background:#f1f3f5;border:1px solid #dcdcde;border-radius:14px;max-width:100%;box-sizing:border-box;">
								<div id="bcc-pc-preview-card"
									style="width:<?php echo esc_attr( absint( $options['pc_banner_width'] ) ); ?>px;max-width:100%;overflow:hidden;background:#fff;border:1px solid #d7e8e2;border-radius:18px;box-shadow:0 12px 32px rgba(27,55,78,.14);box-sizing:border-box;">

									<div id="bcc-pc-card-preview-image-wrap"
										style="width:100%;padding:10px 10px 0;background:#f8fcfa;box-sizing:border-box;"
										<?php echo $pc_image_url ? '' : 'hidden'; ?>>
										<img id="bcc-pc-card-preview-image"
											src="<?php echo esc_url( $pc_image_url ); ?>"
											alt=""
											style="display:block;width:100%;height:auto;max-height:320px;object-fit:contain;border-radius:10px;background:#fff;"
											<?php echo $pc_image_url ? '' : 'hidden'; ?>>
									</div>

									<div id="bcc-pc-card-preview-icon"
										style="display:grid;place-items:center;width:48px;height:48px;margin:18px 18px 0;border-radius:50%;background:#e7f4ef;color:#2f8f79;font-size:24px;font-weight:900;"
										<?php echo $pc_image_url ? 'hidden' : ''; ?>>✓</div>

									<div style="padding:16px 18px 18px;">
										<p id="bcc-pc-preview-title"
											style="margin:0 0 7px;color:#20342f;font-size:18px;line-height:1.45;font-weight:800;text-align:<?php echo esc_attr( $options['pc_title_align'] ); ?>;"
											<?php echo trim( (string) $options['pc_banner_title'] ) ? '' : 'hidden'; ?>><?php echo esc_html( $options['pc_banner_title'] ); ?></p>

										<p id="bcc-pc-preview-text"
											style="margin:0 0 13px;color:#53635f;font-size:13px;line-height:1.7;font-weight:400;"
											<?php echo trim( (string) $options['pc_banner_text'] ) ? '' : 'hidden'; ?>><?php echo esc_html( $options['pc_banner_text'] ); ?></p>

										<?php
										$preview_justify = 'flex-start';
										if ( 'center' === $options['pc_button_align'] ) {
											$preview_justify = 'center';
										} elseif ( 'right' === $options['pc_button_align'] ) {
											$preview_justify = 'flex-end';
										}
										?>
										<div id="bcc-pc-preview-button"
											style="display:flex;align-items:center;justify-content:<?php echo esc_attr( $preview_justify ); ?>;gap:6px;width:100%;box-sizing:border-box;padding:11px 13px;border-radius:11px;background:<?php echo esc_attr( $options['pc_button_bg_color'] ); ?>;color:<?php echo esc_attr( $options['pc_button_text_color'] ); ?>;font-size:14px;font-weight:800;">
											<span id="bcc-pc-preview-button-label"><?php echo esc_html( $options['pc_banner_button'] ); ?></span>
											<span aria-hidden="true">→</span>
										</div>
									</div>
								</div>
							</div>

							<p class="description" style="margin-top:10px;">
								選択画像・見出し・説明文・CTA文言・文字位置・色・横幅を、保存前でもここで確認できます。
							</p>

							<div style="margin-top:18px;">
								<strong style="display:block;margin-bottom:8px;">小型タブの表示イメージ</strong>
								<div id="bcc-pc-preview-tab"
									style="display:inline-flex;align-items:center;justify-content:center;flex-direction:<?php echo 'vertical' === $options['pc_tab_writing_mode'] ? 'column' : 'row'; ?>;gap:7px;width:<?php echo esc_attr( absint( $options['pc_tab_width'] ) ); ?>px;height:<?php echo esc_attr( absint( $options['pc_tab_height'] ) ); ?>px;padding:10px 8px;box-sizing:border-box;border-radius:14px 0 0 14px;background:<?php echo esc_attr( $options['pc_button_bg_color'] ); ?>;color:<?php echo esc_attr( $options['pc_button_text_color'] ); ?>;box-shadow:0 8px 22px rgba(27,55,78,.18);font-size:13px;font-weight:800;line-height:1.35;text-align:center;">
									<span id="bcc-pc-preview-tab-text"
										style="writing-mode:<?php echo 'vertical' === $options['pc_tab_writing_mode'] ? 'vertical-rl' : 'horizontal-tb'; ?>;text-orientation:<?php echo 'vertical' === $options['pc_tab_writing_mode'] ? 'upright' : 'mixed'; ?>;letter-spacing:.08em;"><?php echo esc_html( $options['pc_tab_text'] ); ?></span>
									<span aria-hidden="true">→</span>
								</div>
							</div>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bcc-sp-banner-text">SP固定CTA 文言</label></th>
						<td>
							<input id="bcc-sp-banner-text" class="regular-text" type="text"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[sp_banner_text]"
								value="<?php echo esc_attr( $options['sp_banner_text'] ); ?>">
							<p class="description">スマホ下部に固定表示するCTAの文言です。</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bcc-sp-bg-color">SP固定CTA 背景色</label></th>
						<td>
							<input id="bcc-sp-bg-color" class="bcc-color-field" type="text"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[sp_bg_color]"
								value="<?php echo esc_attr( $options['sp_bg_color'] ); ?>"
								data-default-color="#2f8f79">
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bcc-sp-text-color">SP固定CTA 文字色</label></th>
						<td>
							<input id="bcc-sp-text-color" class="bcc-color-field" type="text"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[sp_text_color]"
								value="<?php echo esc_attr( $options['sp_text_color'] ); ?>"
								data-default-color="#ffffff">
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bcc-sp-height">SP固定CTA 高さ</label></th>
						<td>
							<input id="bcc-sp-height" type="number" min="48" max="76" step="2"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[sp_height]"
								value="<?php echo esc_attr( $options['sp_height'] ); ?>"> px
							<p class="description">48〜76px。初期値56px。</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bcc-sp-radius">SP固定CTA 角丸</label></th>
						<td>
							<input id="bcc-sp-radius" type="number" min="0" max="30" step="1"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[sp_radius]"
								value="<?php echo esc_attr( $options['sp_radius'] ); ?>"> px
							<p class="description">0で四角、数値を大きくすると丸みが強くなります。</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bcc-sp-bottom-offset">SP下部オフセット</label></th>
						<td>
							<input id="bcc-sp-bottom-offset" type="number" min="0" max="80" step="1"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[sp_bottom_offset]"
								value="<?php echo esc_attr( $options['sp_bottom_offset'] ); ?>"> px
							<p class="description">スマホの固定メニューやブラウザUIとの間を追加で空けます。初期値8px。</p>
						</td>
					</tr>

					<tr>
						<th scope="row">SP固定CTA プレビュー</th>
						<td>
							<div style="max-width:390px;padding:18px 12px 12px;background:#f1f3f5;border:1px solid #dcdcde;border-radius:18px;box-sizing:border-box;">
								<div style="height:180px;background:#ffffff;border:1px solid #e3e5e8;border-radius:12px 12px 4px 4px;padding:18px;box-sizing:border-box;position:relative;overflow:hidden;">
									<div style="height:10px;width:46%;background:#eef0f2;border-radius:5px;margin-bottom:12px;"></div>
									<div style="height:8px;width:88%;background:#f2f3f5;border-radius:4px;margin-bottom:8px;"></div>
									<div style="height:8px;width:76%;background:#f2f3f5;border-radius:4px;margin-bottom:8px;"></div>
									<div style="height:8px;width:82%;background:#f2f3f5;border-radius:4px;"></div>
									<div style="position:absolute;left:50%;bottom:8px;transform:translateX(-50%);font-size:11px;color:#8a8f98;">スマホ画面イメージ</div>
								</div>

								<div id="bcc-sp-preview-banner"
									style="box-sizing:border-box;display:flex;align-items:center;justify-content:center;gap:9px;width:100%;padding:10px 16px;margin-top:<?php echo esc_attr( absint( $options['sp_bottom_offset'] ) ); ?>px;background:<?php echo esc_attr( $options['sp_bg_color'] ); ?>;color:<?php echo esc_attr( $options['sp_text_color'] ); ?>;min-height:<?php echo esc_attr( absint( $options['sp_height'] ) ); ?>px;border-radius:<?php echo esc_attr( absint( $options['sp_radius'] ) ); ?>px;box-shadow:0 8px 20px rgba(27,55,78,.16);font-size:15px;font-weight:700;text-align:center;line-height:1.35;">
									<span style="display:inline-grid;place-items:center;width:26px;height:26px;flex:0 0 26px;border-radius:50%;background:rgba(255,255,255,.92);color:#217a65;font-weight:900;">✓</span>
									<span id="bcc-sp-preview-text"><?php echo esc_html( $options['sp_banner_text'] ); ?></span>
									<span aria-hidden="true">→</span>
								</div>
							</div>

							<p class="description" style="margin-top:10px;">
								上の設定を変更すると、保存前でもこのプレビューへ即時反映されます。
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">記事末CTA</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[article_auto_enabled]" value="1" <?php checked( ! empty( $options['article_auto_enabled'] ) ); ?>>
								通常投稿の本文末へ自動表示する
							</label>
							<p class="description">最初はOFF推奨。確認後にONにしてください。個別配置は <code>[bousai_check_article_cta]</code> でも可能です。</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bcc-excluded-paths">固定CTAを出さないURL</label></th>
						<td>
							<textarea id="bcc-excluded-paths" rows="5" cols="50"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[excluded_paths]"><?php echo esc_textarea( $options['excluded_paths'] ); ?></textarea>
							<p class="description">1行1パス。前方一致です。初期値は <code>/disaster-info/</code>。</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bcc-excluded-categories">記事末CTAを出さないカテゴリslug</label></th>
						<td>
							<textarea id="bcc-excluded-categories" rows="4" cols="50"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[excluded_category_slugs]"><?php echo esc_textarea( $options['excluded_category_slugs'] ); ?></textarea>
							<p class="description">カンマまたは改行区切り。例: <code>editorial-column, operation-column</code></p>
						</td>
					</tr>

					<tr>
						<th scope="row">プレビューモード</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[preview_mode]" value="1" <?php checked( ! empty( $options['preview_mode'] ) ); ?>>
								公開前プレビューを有効にする
							</label>
							<p class="description">
								ONの間は、管理者がURL末尾に <code>?bousai_cta_preview=1</code> を付けた場合だけ表示します。
								公開時にこのチェックを外してください。
							</p>
							<p><a class="button" href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener">TOPでプレビュー</a></p>
						</td>
					</tr>
				</table>

				<?php submit_button( '設定を保存' ); ?>
			</form>

			<hr>
			<h2>TOPカードの設置</h2>
			<p>TOPのショートコードブロックへ、次をそのまま入れてください。</p>
			<p><code>[bousai_check_top_card]</code></p>

			<h2>GA4計測</h2>
			<p>クリック時にイベント <code>bousai_check_cta_click</code> を送ります。パラメータ <code>location</code> は次のいずれかです。</p>
			<p><code>pc_floating</code> / <code>sp_fixed</code> / <code>top_card</code> / <code>article_bottom</code></p>
		</div>
		<?php
	}

	public function enqueue_assets() {
		if ( ! $this->should_render_global() ) {
			return;
		}

		$options = $this->options();

		wp_enqueue_style(
			'bousai-check-cta',
			plugin_dir_url( __FILE__ ) . 'assets/css/bcc.css',
			array(),
			self::VERSION
		);

		wp_enqueue_script(
			'bousai-check-cta',
			plugin_dir_url( __FILE__ ) . 'assets/js/bcc.js',
			array(),
			self::VERSION,
			true
		);

		wp_localize_script(
			'bousai-check-cta',
			'BousaiCheckCTA',
			array(
				'scrollThreshold' => absint( $options['scroll_threshold'] ),
			)
		);
	}

	private function anchor( $class, $location, $label ) {
		$url = $this->target_url();
		if ( ! $url ) {
			return '';
		}

		return sprintf(
			'<a class="%1$s bcc-track" href="%2$s" data-bcc-location="%3$s" aria-label="%4$s"><span class="bcc-checkmark" aria-hidden="true">✓</span><span>%5$s</span><span class="bcc-arrow" aria-hidden="true">→</span></a>',
			esc_attr( $class ),
			esc_url( $url ),
			esc_attr( $location ),
			esc_attr( wp_strip_all_tags( $label ) ),
			esc_html( $label )
		);
	}

	public function render_floating_cta() {
		$options = $this->options();

		if ( empty( $options['floating_enabled'] ) || ! $this->should_render_global() ) {
			return;
		}

		$pc_image_id  = absint( $options['pc_banner_image_id'] );
		$pc_image_url = $pc_image_id ? wp_get_attachment_image_url( $pc_image_id, 'large' ) : '';
		$pc_title     = trim( (string) $options['pc_banner_title'] );
		$pc_text      = trim( (string) $options['pc_banner_text'] );
		$pc_button    = trim( (string) $options['pc_banner_button'] );
		$pc_tab_vertical = 'vertical' === $options['pc_tab_writing_mode'];
		$pc_tab_writing_mode = $pc_tab_vertical ? 'vertical-rl' : 'horizontal-tb';
		$pc_tab_text_orientation = $pc_tab_vertical ? 'upright' : 'mixed';
		$pc_tab_flex_direction = $pc_tab_vertical ? 'column' : 'row';

		$pc_title_align = in_array( $options['pc_title_align'], array( 'left', 'center', 'right' ), true ) ? $options['pc_title_align'] : 'left';

		$pc_button_justify = 'flex-start';
		if ( 'center' === $options['pc_button_align'] ) {
			$pc_button_justify = 'center';
		} elseif ( 'right' === $options['pc_button_align'] ) {
			$pc_button_justify = 'flex-end';
		}

		$url          = $this->target_url();
		?>
		<div class="bcc-floating-wrap"
			data-bcc-floating
			data-pc-mode="<?php echo esc_attr( $options['pc_display_mode'] ); ?>"
			data-pc-width="<?php echo esc_attr( absint( $options['pc_banner_width'] ) ); ?>"
			data-pc-gap="<?php echo esc_attr( absint( $options['pc_banner_gap'] ) ); ?>"
			style="<?php echo esc_attr(
				'--bcc-pc-title-align:' . $pc_title_align . ';' .
				'--bcc-pc-tab-width:' . absint( $options['pc_tab_width'] ) . 'px;' .
				'--bcc-pc-tab-height:' . absint( $options['pc_tab_height'] ) . 'px;' .
				'--bcc-pc-tab-writing-mode:' . $pc_tab_writing_mode . ';' .
				'--bcc-pc-tab-text-orientation:' . $pc_tab_text_orientation . ';' .
				'--bcc-pc-tab-flex-direction:' . $pc_tab_flex_direction . ';' .
				'--bcc-pc-button-bg:' . $options['pc_button_bg_color'] . ';' .
				'--bcc-pc-button-text:' . $options['pc_button_text_color'] . ';' .
				'--bcc-pc-button-justify:' . $pc_button_justify . ';' .
				'--bcc-sp-bg:' . $options['sp_bg_color'] . ';' .
				'--bcc-sp-text:' . $options['sp_text_color'] . ';' .
				'--bcc-sp-height:' . absint( $options['sp_height'] ) . 'px;' .
				'--bcc-sp-radius:' . absint( $options['sp_radius'] ) . 'px;' .
				'--bcc-sp-bottom-offset:' . absint( $options['sp_bottom_offset'] ) . 'px;'
			); ?>"
			hidden>

			<a class="bcc-pc-card bcc-track<?php echo $pc_image_url ? ' has-image' : ''; ?>"
				href="<?php echo esc_url( $url ); ?>"
				data-bcc-location="pc_floating"
				aria-label="<?php echo esc_attr( $pc_title ? $pc_title : 'わが家の防災チェック' ); ?>">

				<?php if ( $pc_image_url ) : ?>
					<div class="bcc-pc-card__image">
						<img src="<?php echo esc_url( $pc_image_url ); ?>" alt="">
					</div>
				<?php else : ?>
					<div class="bcc-pc-card__icon" aria-hidden="true">✓</div>
				<?php endif; ?>

				<div class="bcc-pc-card__body">
					<?php if ( $pc_title ) : ?>
						<p class="bcc-pc-card__title"><?php echo esc_html( $pc_title ); ?></p>
					<?php endif; ?>

					<?php if ( $pc_text ) : ?>
						<p class="bcc-pc-card__text"><?php echo esc_html( $pc_text ); ?></p>
					<?php endif; ?>

					<span class="bcc-pc-card__button">
						<span class="bcc-pc-card__button-label"><?php echo esc_html( $pc_button ? $pc_button : 'チェックする' ); ?></span>
						<span class="bcc-pc-card__button-arrow" aria-hidden="true">→</span>
					</span>
				</div>
			</a>

			<a class="bcc-pc-tab bcc-track"
				href="<?php echo esc_url( $url ); ?>"
				data-bcc-location="pc_tab"
				aria-label="<?php echo esc_attr( $options['pc_tab_text'] ? $options['pc_tab_text'] : '防災チェック' ); ?>">
				<span class="bcc-pc-tab__text"><?php echo esc_html( $options['pc_tab_text'] ? $options['pc_tab_text'] : '防災チェック' ); ?></span>
				<span class="bcc-pc-tab__arrow" aria-hidden="true">→</span>
			</a>

			<?php
			echo $this->anchor(
				'bcc-floating bcc-floating--sp',
				'sp_fixed',
				$options['sp_banner_text'] ? $options['sp_banner_text'] : 'わが家の防災をチェック'
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
			<button
				type="button"
				class="bcc-sp-close"
				data-bcc-sp-close
				aria-label="防災チェックのバナーを閉じる"
				title="閉じる"
			>×</button>
		</div>
		<?php
	}

	public function shortcode_top_card() {
		if ( ! $this->should_render_global() ) {
			return '';
		}

		$url = $this->target_url();

		ob_start();
		?>
		<section class="bcc-top-card" aria-label="防災チェック">
			<div class="bcc-top-card__icon" aria-hidden="true">✓</div>
			<div class="bcc-top-card__body">
				<p class="bcc-top-card__eyebrow">わが家の防災チェック</p>
				<h2 class="bcc-top-card__title">わが家の備え、足りていますか？</h2>
				<p class="bcc-top-card__text">家族構成や暮らしの状況に合わせて、今の備えをまとめて確認できます。</p>
			</div>
			<a class="bcc-top-card__button bcc-track" data-bcc-location="top_card" href="<?php echo esc_url( $url ); ?>">
				防災チェックをはじめる <span aria-hidden="true">→</span>
			</a>
		</section>
		<?php
		return ob_get_clean();
	}

	private function context_message( $post_id ) {
		$title = get_the_title( $post_id );
		$terms = wp_get_post_terms( $post_id, array( 'category', 'post_tag' ), array( 'fields' => 'names' ) );
		$haystack = $title . ' ' . ( is_wp_error( $terms ) ? '' : implode( ' ', $terms ) );

		$patterns = array(
			array(
				'keys' => array( '備蓄', '非常食', '保存水', '飲料水', 'ローリングストック' ),
				'text' => 'この記事を読んで、自宅の備蓄が足りているか気になった方へ。家族構成に合わせて必要な備えをまとめて確認できます。',
			),
			array(
				'keys' => array( 'トイレ', '衛生', '断水', '携帯トイレ' ),
				'text' => 'トイレや衛生の備えは、家族の人数や状況によって必要量が変わります。わが家に必要な備えをまとめて確認してみませんか。',
			),
			array(
				'keys' => array( '停電', '電源', '充電', 'モバイルバッテリー', 'ランタン', '照明' ),
				'text' => '停電への備えは、電源だけでなく照明や情報収集手段も含めて考えることが大切です。わが家の準備状況をまとめて確認できます。',
			),
			array(
				'keys' => array( '家具', '耐震', '感震ブレーカー', '地震', '転倒' ),
				'text' => '住まいの安全対策は、家具固定や停電・火災への備えまでまとめて確認すると抜け漏れを減らせます。わが家の備えをチェックしてみましょう。',
			),
			array(
				'keys' => array( '避難', 'ハザード', '洪水', '大雨', '土砂', '津波', '台風' ),
				'text' => '避難の準備は、持ち出し品だけでなく家族構成や住まいの状況も含めて考える必要があります。わが家の備えをまとめて確認できます。',
			),
		);

		foreach ( $patterns as $pattern ) {
			foreach ( $pattern['keys'] as $key ) {
				if ( false !== mb_strpos( $haystack, $key ) ) {
					return $pattern['text'];
				}
			}
		}

		return 'この記事をきっかけに、わが家の防災をまとめて見直してみませんか。家族構成や暮らしの状況に合わせて、今の備えを確認できます。';
	}

	private function article_cta_html( $post_id ) {
		$url = $this->target_url();
		if ( ! $url ) {
			return '';
		}

		$message = $this->context_message( $post_id );

		ob_start();
		?>
		<aside class="bcc-article-cta" aria-label="わが家の防災チェック">
			<div class="bcc-article-cta__icon" aria-hidden="true">✓</div>
			<div class="bcc-article-cta__content">
				<p class="bcc-article-cta__title">わが家の防災、まとめて確認してみませんか？</p>
				<p class="bcc-article-cta__text"><?php echo esc_html( $message ); ?></p>
				<a class="bcc-article-cta__button bcc-track" data-bcc-location="article_bottom" href="<?php echo esc_url( $url ); ?>">
					わが家の防災チェック <span aria-hidden="true">→</span>
				</a>
			</div>
		</aside>
		<?php
		return ob_get_clean();
	}

	public function shortcode_article_cta() {
		if ( ! $this->should_render_global() || ! is_singular( 'post' ) ) {
			return '';
		}
		return $this->article_cta_html( get_the_ID() );
	}

	public function append_article_cta( $content ) {
		$options = $this->options();

		if ( empty( $options['article_auto_enabled'] ) ) {
			return $content;
		}

		if (
			! $this->should_render_global()
			|| ! is_singular( 'post' )
			|| ! in_the_loop()
			|| ! is_main_query()
		) {
			return $content;
		}

		$post_id = get_the_ID();

		if ( ! $post_id || $this->is_excluded_category( $post_id ) ) {
			return $content;
		}

		return $content . $this->article_cta_html( $post_id );
	}
}

register_activation_hook( __FILE__, array( 'Bousai_Check_CTA', 'activate' ) );
Bousai_Check_CTA::instance();