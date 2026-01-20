<?php
/**
 * プレビュー機能クラス
 *
 * @package Screw
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SC_Preview
 */
class SC_Preview {
	/**
	 * シングルトンインスタンス
	 *
	 * @var SC_Preview
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
	 * @return SC_Preview
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
		// プレビューモードチェック
		add_action( 'template_redirect', array( $this, 'handle_preview' ) );
	}

	/**
	 * プレビューモード処理
	 */
	public function handle_preview() {
		if ( ! isset( $_GET['screw_preview'] ) || '1' !== $_GET['screw_preview'] ) {
			return;
		}

		// 権限チェック
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '権限がありません。' );
		}

		// transient keyから設定を取得（セキュリティ強化）
		if ( ! isset( $_GET['key'] ) ) {
			wp_die( 'プレビュー情報が見つかりません。' );
		}

		$key = sanitize_text_field( wp_unslash( $_GET['key'] ) );

		// transient keyの検証（プレフィックスチェック）
		if ( strpos( $key, 'screw_preview_' ) !== 0 ) {
			wp_die( '不正なリクエストです。' );
		}

		// プレビュー用設定を取得
		$preview_settings = $this->get_preview_settings( $key );
		if ( $preview_settings ) {
			$this->settings = $preview_settings;
		} else {
			// 設定が取得できない場合はエラー（有効期限切れの可能性）
			wp_die( 'プレビュー情報の有効期限が切れています。設定画面から再度プレビューしてください。' );
		}

		// プレビュー専用ページを表示
		$this->render_preview_page();
		exit;
	}

	/**
	 * プレビュー用設定を取得
	 *
	 * @param string $key Transient key.
	 * @return array|false
	 */
	private function get_preview_settings( $key ) {
		// transientから設定を取得
		$settings = get_transient( $key );

		if ( ! $settings || ! is_array( $settings ) ) {
			return false;
		}

		// バリデーション
		$settings_instance = SC_Settings::get_instance();
		$default_settings  = $settings_instance->get_settings();

		// 必須項目チェック
		if ( empty( $settings['loading_image_id'] ) ) {
			return false;
		}

		// デフォルト値とマージ
		return wp_parse_args( $settings, $default_settings );
	}

	/**
	 * プレビュー専用ページをレンダリング
	 */
	private function render_preview_page() {
		// ローディング画像が未設定の場合
		if ( empty( $this->settings['loading_image_id'] ) ) {
			wp_die( 'ローディング画像が設定されていません。' );
		}

		// ローディング画像を取得
		$loading_image = wp_get_attachment_image_src( $this->settings['loading_image_id'], 'full' );
		if ( ! $loading_image ) {
			wp_die( 'ローディング画像が見つかりません。' );
		}

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
		$progressbar_color    = $this->settings['progressbar_color'];
		$progressbar_bg_color = $this->lighten_color( $progressbar_color, 70 );

		// クラス名
		$loader_classes   = array( 'screw-loader' );
		$loader_classes[] = 'animation-' . esc_attr( $animation_type );
		if ( 'wipe' === $animation_type ) {
			$loader_classes[] = 'wipe-' . esc_attr( $wipe_direction );
		}

		// スタイル
		$inline_styles   = array();
		$inline_styles[] = '--screw-bg-color: ' . esc_attr( $bg_color ) . ';';
		$inline_styles[] = '--screw-loading-width: ' . intval( $loading_width ) . 'px;';

		// 画像の高さを計算（ワイプモードの水平方向で使用）
		if ( 'wipe' === $animation_type && in_array( $wipe_direction, array( 'left-right', 'right-left' ), true ) ) {
			$img_width       = intval( $loading_width );
			$img_height      = intval( $img_width * $loading_image[2] / $loading_image[1] );
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
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title>Screw プレビュー</title>
			<link rel="stylesheet" href="<?php echo esc_url( SC_PLUGIN_URL . 'assets/css/loader.css?ver=' . SC_VERSION ); ?>">
			<style>
				body {
					margin: 0;
					padding: 0;
					overflow: hidden;
				}
			</style>
		</head>
		<body class="screw-preview-mode">
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
					<?php else : ?>
						<!-- プログレスバーモード: 通常構造 -->
						<img src="<?php echo esc_url( $loading_image_url ); ?>" alt="Loading" class="screw-loading-image">
						<div class="screw-progressbar-container">
							<div class="screw-progressbar"></div>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</body>
		</html>
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
