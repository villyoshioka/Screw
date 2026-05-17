<?php
/**
 * プレビュー機能クラス
 *
 * @package Screw
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SC_Preview {
	private static ?self $instance = null;
	private array $settings = [];

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'template_redirect', [ $this, 'handle_preview' ] );
	}

	public function handle_preview(): void {
		if ( ! isset( $_GET['screw_preview'] ) || '1' !== $_GET['screw_preview'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '権限がありません。' );
		}

		if ( ! isset( $_GET['key'] ) ) {
			wp_die( 'プレビュー情報が見つかりません。' );
		}

		$key = sanitize_text_field( wp_unslash( $_GET['key'] ) );

		if ( ! str_starts_with( $key, 'screw_preview_' ) ) {
			wp_die( '不正なリクエストです。' );
		}

		$preview_settings = $this->get_preview_settings( $key );
		if ( $preview_settings ) {
			$this->settings = $preview_settings;
		} else {
			wp_die( 'プレビュー情報の有効期限が切れています。設定画面から再度プレビューしてください。' );
		}

		$this->render_preview_page();
		exit;
	}

	private function get_preview_settings( string $key ): array|false {
		$settings = get_transient( $key );

		if ( ! $settings || ! is_array( $settings ) ) {
			return false;
		}

		$settings_instance = SC_Settings::get_instance();
		$default_settings  = $settings_instance->get_settings();

		if ( empty( $settings['loading_image_id'] ) ) {
			return false;
		}

		return wp_parse_args( $settings, $default_settings );
	}

	private function render_preview_page(): void {
		if ( empty( $this->settings['loading_image_id'] ) ) {
			wp_die( 'ローディング画像が設定されていません。' );
		}

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

		$bg_image_url = '';
		if ( ! empty( $bg_image_id ) ) {
			$bg_image = wp_get_attachment_image_src( $bg_image_id, 'full' );
			if ( $bg_image ) {
				$bg_image_url = esc_url( $bg_image[0] );
			}
		}

		$progressbar_color    = $this->settings['progressbar_color'];
		$progressbar_bg_color = $this->lighten_color( $progressbar_color, 70 );
		$spinner_color        = $this->settings['spinner_color'];

		$loader_classes   = [ 'screw-loader' ];
		$loader_classes[] = 'animation-' . esc_attr( $animation_type );
		if ( 'wipe' === $animation_type ) {
			$loader_classes[] = 'wipe-' . esc_attr( $wipe_direction );
		}

		$inline_styles   = [];
		$inline_styles[] = '--screw-bg-color: ' . esc_attr( $bg_color ) . ';';
		$inline_styles[] = '--screw-loading-width: ' . intval( $loading_width ) . 'px;';

		if ( 'wipe' === $animation_type && in_array( $wipe_direction, [ 'left-right', 'right-left' ], true ) ) {
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
		if ( 'spinner' === $animation_type ) {
			$inline_styles[] = '--screw-spinner-color: ' . esc_attr( $spinner_color ) . ';';
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
						<img src="<?php echo esc_url( $loading_image_url ); ?>" alt="Loading" class="screw-loading-image">
						<div class="screw-progressbar-container">
							<div class="screw-progressbar"></div>
						</div>
					<?php elseif ( 'spinner' === $animation_type ) : ?>
						<img src="<?php echo esc_url( $loading_image_url ); ?>" alt="Loading" class="screw-loading-image">
						<div class="screw-spinner"></div>
					<?php else : ?>
						<img src="<?php echo esc_url( $loading_image_url ); ?>" alt="Loading" class="screw-loading-image">
					<?php endif; ?>
					<?php if ( ! empty( $this->settings['slow_load_text_enabled'] ) && ! empty( $this->settings['slow_load_text'] ) ) : ?>
						<div class="screw-slow-load-text" style="color: <?php echo esc_attr( $this->settings['slow_load_text_color'] ?: '#000000' ); ?>;"><?php echo esc_html( $this->settings['slow_load_text'] ); ?></div>
					<?php endif; ?>
				</div>
			</div>
		<script>
		(function(){
			var el = document.querySelector('.screw-slow-load-text');
			if (el) {
				setTimeout(function(){ el.classList.add('visible'); }, 5500);
			}
		})();
		</script>
		</body>
		</html>
		<?php
	}

	private function lighten_color( string $hex, int $percent ): string {
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
