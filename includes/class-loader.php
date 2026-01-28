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
		// 設定を取得
		$settings_instance = SC_Settings::get_instance();
		$this->settings    = $settings_instance->get_settings();

		// フックの登録
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_body_open', array( $this, 'render_loader' ), 1 );
	}

	/**
	 * ローディング画面を表示すべきかチェック
	 *
	 * @return bool
	 */
	private function should_display_loader() {
		// ローディング画像が未設定の場合は表示しない
		if ( empty( $this->settings['loading_image_id'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Screw: Loader not displayed - loading_image_id is not set.' );
			}
			return false;
		}

		// ローディング画像の存在確認
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
		// 共通チェックメソッドを使用
		if ( ! $this->should_display_loader() ) {
			return;
		}

		// CSS（圧縮版）
		wp_enqueue_style(
			'screw-loader',
			SC_PLUGIN_URL . 'assets/css/loader.css',
			array(),
			SC_VERSION
		);

		// JS（jQuery依存削除 + headで読み込み）
		wp_enqueue_script(
			'screw-loader',
			SC_PLUGIN_URL . 'assets/js/loader.js',
			array(), // jQuery依存を削除
			SC_VERSION,
			false    // headで読み込み
		);

		// defer属性とCloudflare Rocket Loader除外を追加
		add_filter( 'script_loader_tag', array( $this, 'add_defer_attribute' ), 10, 2 );

		// JSに設定値を渡す（静的化対応のためインラインスクリプトを使用）
		wp_add_inline_script(
			'screw-loader',
			'var screwSettings = ' . wp_json_encode(
				array(
					'displayFrequency' => $this->settings['display_frequency'],
					'siteUrl'          => home_url(),
					'animationType'    => $this->settings['animation_type'],
				)
			) . ';',
			'before' // スクリプトの前に出力
		);
	}

	/**
	 * スクリプトタグにdefer属性とCloudflare Rocket Loader除外を追加
	 *
	 * @param string $tag    スクリプトタグ
	 * @param string $handle スクリプトハンドル名
	 * @return string 修正されたスクリプトタグ
	 */
	public function add_defer_attribute( $tag, $handle ) {
		if ( 'screw-loader' === $handle ) {
			// defer属性を追加（DOM構築をブロックしない）
			// data-cfasync="false"でCloudflare Rocket Loaderから除外
			$tag = str_replace( ' src', ' defer data-cfasync="false" src', $tag );
		}
		return $tag;
	}

	/**
	 * ローディング画面をレンダリング
	 */
public function render_loader() {
		// 既にレンダリング済みの場合はスキップ
		static $rendered = false;
		if ( $rendered ) {
			return;
		}
		$rendered = true;

		// 共通チェックメソッドを使用
		if ( ! $this->should_display_loader() ) {
			return;
		}

		// ローディング画像を取得
		$loading_image = wp_get_attachment_image_src( $this->settings['loading_image_id'], 'full' );

		$loading_image_url = esc_url( $loading_image[0] );
		$loading_width     = $this->settings['loading_image_width'];
		$animation_type    = $this->settings['animation_type'];
		$wipe_direction    = $this->settings['wipe_direction'];
		$bg_color          = $this->settings['bg_color'];
		$bg_image_id       = $this->settings['bg_image_id'];
		$bg_image_blur     = ! empty( $this->settings['bg_image_blur'] );

		// 背景画像
		$bg_image_url = '';
		if ( ! empty( $bg_image_id ) ) {
			$bg_image = wp_get_attachment_image_src( $bg_image_id, 'full' );
			if ( $bg_image ) {
				$bg_image_url = esc_url( $bg_image[0] );
			}
		}

		// プログレスバーの色
		$progressbar_color     = $this->settings['progressbar_color'];
		$progressbar_bg_color  = $this->lighten_color( $progressbar_color, 70 );

		// クラス名
		$loader_classes = array( 'screw-loader' );
		$loader_classes[] = 'animation-' . esc_attr( $animation_type );
		if ( 'wipe' === $animation_type ) {
			$loader_classes[] = 'wipe-' . esc_attr( $wipe_direction );
		}

		// スタイル
		$inline_styles = array();
		$inline_styles[] = '--screw-bg-color: ' . esc_attr( $bg_color ) . ';';
		$inline_styles[] = '--screw-loading-width: ' . intval( $loading_width ) . 'px;';

		// 画像の高さを計算（ワイプモードの水平方向で使用）
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

		?>
		<div id="screw-loader-wrapper" class="<?php echo esc_attr( implode( ' ', $loader_classes ) ); ?>" style="<?php echo esc_attr( implode( ' ', $inline_styles ) ); ?>">
			<div class="screw-loader-bg<?php echo $bg_image_blur ? esc_attr( ' blur' ) : ''; ?>"></div>
			<div class="screw-loader-content">
				<?php if ( 'wipe' === $animation_type ) : ?>
					<!-- ワイプモード: 二重レイヤー構造 -->
					<img src="<?php echo esc_url( $loading_image_url ); ?>"
					     alt="Loading"
					     class="screw-loading-image screw-loading-image-base"
					     style="opacity: 0.3;">
					<div class="screw-loading-wipe-container">
						<span class="screw-loading-wipe-span"
						      style="background-image: url(<?php echo esc_url( $loading_image_url ); ?>);"></span>
					</div>
				<?php elseif ( 'progressbar' === $animation_type ) : ?>
					<!-- プログレスバーモード: 通常構造 -->
					<img src="<?php echo esc_url( $loading_image_url ); ?>" alt="Loading" class="screw-loading-image">
					<div class="screw-progressbar-container">
						<div class="screw-progressbar"></div>
					</div>
				<?php else : ?>
					<!-- アニメーションなしモード: 画像のみ -->
					<img src="<?php echo esc_url( $loading_image_url ); ?>" alt="Loading" class="screw-loading-image">
				<?php endif; ?>
			</div>
		</div>
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
		// #を削除
		$hex = ltrim( $hex, '#' );

		// RGBに変換
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		// 明るくする
		$r = min( 255, $r + ( ( 255 - $r ) * $percent / 100 ) );
		$g = min( 255, $g + ( ( 255 - $g ) * $percent / 100 ) );
		$b = min( 255, $b + ( ( 255 - $b ) * $percent / 100 ) );

		// HEXに戻す
		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}
}
