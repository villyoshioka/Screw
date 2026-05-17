<?php
/**
 * フロントエンドローディング表示クラス
 *
 * @package Screw
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SC_Loader
 */
class SC_Loader {
	/**
	 * シングルトンインスタンス
	 *
	 * @var SC_Loader
	 */
	private static $instance = null;

	/**
	 * 設定
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * シングルトンインスタンスを取得
	 *
	 * @return SC_Loader
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * コンストラクタ
	 */
	private function __construct() {
		$settings_instance = SC_Settings::get_instance();
		$this->settings    = $settings_instance->get_settings();

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_body_open', array( $this, 'render_loader' ), 1 );
	}

	/**
	 * ローディング画面を表示すべきかチェック
	 *
	 * @return bool
	 */
	private function should_display_loader() {
		if ( empty( $this->settings['loading_image_id'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Screw: Loader not displayed - loading_image_id is not set.' );
			}
			return false;
		}

		$loading_image = wp_get_attachment_image_src( $this->settings['loading_image_id'], 'full' );
		if ( ! $loading_image ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Screw: Loader not displayed - loading image not found (ID: ' . $this->settings['loading_image_id'] . ')' );
			}
			return false;
		}

		return true;
	}

	/**
	 * スクリプトとスタイルを読み込み
	 */
	public function enqueue_scripts() {
		if ( ! $this->should_display_loader() ) {
			return;
		}

		wp_enqueue_style(
			'screw-loader',
			SC_PLUGIN_URL . 'assets/css/loader.css',
			array(),
			SC_VERSION
		);

		wp_enqueue_script(
			'screw-loader',
			SC_PLUGIN_URL . 'assets/js/loader.js',
			array(), // jQuery依存を削除
			SC_VERSION,
			false    // headで読み込み
		);

		add_filter( 'script_loader_tag', array( $this, 'add_script_attributes' ), 10, 2 );

		wp_add_inline_script(
			'screw-loader',
			'var screwSettings = ' . wp_json_encode(
				array(
					'animationType'    => $this->settings['animation_type'],
				)
			) . ';',
			'before' // スクリプトの前に出力
		);
	}

	/**
	 * スクリプトタグに属性を追加
	 *
	 * async: ダウンロード完了次第実行（レンダリングブロック回避）
	 * data-cfasync="false": Cloudflare Rocket Loader除外
	 *
	 * @param string $tag    スクリプトタグ
	 * @param string $handle スクリプトハンドル名
	 * @return string 修正されたスクリプトタグ
	 */
	public function add_script_attributes( $tag, $handle ) {
		if ( 'screw-loader' === $handle ) {
			$tag = str_replace( ' src', ' async data-cfasync="false" src', $tag );
		}
		return $tag;
	}

	/**
	 * ローディング画面をレンダリング
	 */
public function render_loader() {
		static $rendered = false;
		if ( $rendered ) {
			return;
		}
		$rendered = true;

		if ( ! $this->should_display_loader() ) {
			return;
		}

		$loading_image = wp_get_attachment_image_src( $this->settings['loading_image_id'], 'full' );

		$loading_image_url = esc_url( $loading_image[0] );
		$loading_width     = $this->settings['loading_image_width'];
		$animation_type    = $this->settings['animation_type'];
		$wipe_direction    = $this->settings['wipe_direction'];
		$bg_color          = $this->settings['bg_color'];
		$bg_image_id       = $this->settings['bg_image_id'];
		$bg_image_blur     = ! empty( $this->settings['bg_image_blur'] );

		$bg_image_url = '';
		if ( ! empty( $bg_image_id ) ) {
			$bg_image = wp_get_attachment_image_src( $bg_image_id, 'full' );
			if ( $bg_image ) {
				$bg_image_url = esc_url( $bg_image[0] );
			}
		}

		$progressbar_color     = $this->settings['progressbar_color'];
		$progressbar_bg_color  = $this->lighten_color( $progressbar_color, 70 );
		$spinner_color = $this->settings['spinner_color'];

		$loader_classes = array( 'screw-loader' );
		$loader_classes[] = 'animation-' . esc_attr( $animation_type );
		if ( 'wipe' === $animation_type ) {
			$loader_classes[] = 'wipe-' . esc_attr( $wipe_direction );
		}

		$inline_styles = array();
		$inline_styles[] = '--screw-bg-color: ' . esc_attr( $bg_color ) . ';';
		$inline_styles[] = '--screw-loading-width: ' . intval( $loading_width ) . 'px;';

		if ( 'wipe' === $animation_type && in_array( $wipe_direction, array( 'left-right', 'right-left' ), true ) ) {
			$img_width = intval( $loading_width );
			$img_height = intval( $img_width * $loading_image[2] / $loading_image[1] );
			$inline_styles[] = '--screw-loading-height: ' . $img_height . 'px;';
		}

		if ( $bg_image_url ) {
			$inline_styles[] = '--screw-bg-image: url(' . esc_url( $bg_image_url ) . ');';
		}
		if ( 'progressbar' === $animation_type ) {
			$inline_styles[] = '--screw-progressbar-color: ' . esc_attr( $progressbar_color ) . ';';
			$inline_styles[] = '--screw-progressbar-bg-color: ' . esc_attr( $progressbar_bg_color ) . ';';
		}
		if ( 'spinner' === $animation_type ) {
			$inline_styles[] = '--screw-spinner-color: ' . esc_attr( $spinner_color ) . ';';
		}

		?>
		<div id="screw-loader-wrapper" class="<?php echo esc_attr( implode( ' ', $loader_classes ) ); ?>" style="<?php echo esc_attr( implode( ' ', $inline_styles ) ); ?>">
			<div class="screw-loader-bg<?php echo $bg_image_blur ? esc_attr( ' blur' ) : ''; ?>"></div>
			<div class="screw-loader-content">
				<?php if ( 'wipe' === $animation_type ) : ?>
					<!-- ワイプモード: 二重レイヤー構造 -->
					<div class="screw-loading-wipe-wrapper">
						<img src="<?php echo esc_url( $loading_image_url ); ?>"
						     alt="Loading"
						     class="screw-loading-image screw-loading-image-base"
						     style="opacity: 0.3;">
						<div class="screw-loading-wipe-container">
							<span class="screw-loading-wipe-span"
							      style="background-image: url(<?php echo esc_url( $loading_image_url ); ?>);"></span>
						</div>
					</div>
				<?php elseif ( 'progressbar' === $animation_type ) : ?>
					<!-- プログレスバーモード: 通常構造 -->
					<img src="<?php echo esc_url( $loading_image_url ); ?>" alt="Loading" class="screw-loading-image">
					<div class="screw-progressbar-container">
						<div class="screw-progressbar"></div>
					</div>
				<?php elseif ( 'spinner' === $animation_type ) : ?>
					<!-- スピナーモード: 画像 + 回転リング -->
					<img src="<?php echo esc_url( $loading_image_url ); ?>" alt="Loading" class="screw-loading-image">
					<div class="screw-spinner"></div>
				<?php else : ?>
					<!-- アニメーションなしモード: 画像のみ -->
					<img src="<?php echo esc_url( $loading_image_url ); ?>" alt="Loading" class="screw-loading-image">
				<?php endif; ?>
					<?php if ( ! empty( $this->settings['slow_load_text_enabled'] ) && ! empty( $this->settings['slow_load_text'] ) ) : ?>
					<div class="screw-slow-load-text" style="display:none; color: <?php echo esc_attr( $this->settings['slow_load_text_color'] ?: '#000000' ); ?>;"><?php echo esc_html( $this->settings['slow_load_text'] ); ?></div>
				<?php endif; ?>
			</div>
		</div>
		<script>
		(function(){
			window.screwStartTime = Date.now();
			if (!document.body.classList.contains("wp-admin")) {
				var h = function(e) { e.preventDefault(); };
				window.addEventListener("wheel", h, {passive: false});
				window.addEventListener("touchmove", h, {passive: false});
				window.screwEarlyScrollHandler = h;
			}
		})();
		</script>
		<?php
	}

	/**
	 * 色を明るくする
	 *
	 * @param string $hex HEX色コード
	 * @param int    $percent 明るくする割合（0-100）
	 * @return string
	 */
	private function lighten_color( $hex, $percent ) {
		$hex = ltrim( $hex, '#' );

		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		$r = min( 255, $r + ( ( 255 - $r ) * $percent / 100 ) );
		$g = min( 255, $g + ( ( 255 - $g ) * $percent / 100 ) );
		$b = min( 255, $b + ( ( 255 - $b ) * $percent / 100 ) );

		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}
}
