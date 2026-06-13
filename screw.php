<?php
/**
 * Plugin Name: Screw
 * Plugin URI: https://github.com/villyoshioka/Screw
 * Description: WordPressサイトにオリジナル画像でのローディング画面を表示するプラグイン
 * Version: 2.1.0
 * Requires at least: 6.8
 * Tested up to: 7.0
 * Requires PHP: 8.3
 * Author: Vill Yoshioka
 * Author URI: https://github.com/villyoshioka
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: screw
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SC_VERSION', '2.1.0' );
define( 'SC_PLUGIN_FILE', __FILE__ );
define( 'SC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * メインプラグインクラス
 */
class Screw {
	private static ?self $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_dependencies();
		$this->register_hooks();
	}

	private function load_dependencies(): void {
		require_once SC_PLUGIN_DIR . 'includes/class-settings.php';
		require_once SC_PLUGIN_DIR . 'includes/class-updater.php';
		require_once SC_PLUGIN_DIR . 'includes/class-loader.php';
		require_once SC_PLUGIN_DIR . 'includes/class-preview.php';

		if ( is_admin() ) {
			require_once SC_PLUGIN_DIR . 'includes/class-admin.php';
		}
	}

	private function register_hooks(): void {
		register_activation_hook( SC_PLUGIN_FILE, [ $this, 'activate' ] );
		register_deactivation_hook( SC_PLUGIN_FILE, [ $this, 'deactivate' ] );
		add_action( 'plugins_loaded', [ $this, 'init' ] );
	}

	public function init(): void {
		SC_Settings::get_instance();
		SC_Updater::get_instance();
		SC_Loader::get_instance();
		SC_Preview::get_instance();

		if ( is_admin() ) {
			SC_Admin::get_instance();
		}
	}

	public function activate(): void {
		$default_settings = [
			'loading_image_id'    => 0,
			'loading_image_width' => 90,
			'animation_type'      => 'wipe',
			'wipe_direction'      => 'bottom-top',
			'progressbar_color'   => '#000000',
			'bg_color'            => '#ffffff',
			'bg_image_id'         => 0,
		];

		if ( false === get_option( 'sc_settings' ) ) {
			update_option( 'sc_settings', $default_settings );
		}

		update_option( 'sc_version', SC_VERSION );
	}

	public function deactivate(): void {
		delete_transient( 'sc_beta_channel' );
		delete_transient( 'sc_github_release_cache' );
		delete_transient( 'sc_github_release_cache_beta' );

		// SQL prepare使用でセキュリティ強化
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s
				OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_sc_beta_attempts_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_sc_beta_attempts_' ) . '%'
			)
		);
	}

	public static function uninstall(): void {
		delete_option( 'sc_settings' );
		delete_option( 'sc_version' );

		delete_transient( 'sc_beta_channel' );
		delete_transient( 'sc_github_release_cache' );
		delete_transient( 'sc_github_release_cache_beta' );

		// SQL prepare使用でセキュリティ強化
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s
				OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_sc_beta_attempts_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_sc_beta_attempts_' ) . '%'
			)
		);
	}
}

register_uninstall_hook( SC_PLUGIN_FILE, [ 'Screw', 'uninstall' ] );

Screw::get_instance();
