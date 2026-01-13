<?php
/**
 * Plugin Name: Screw
 * Plugin URI: https://github.com/villyoshioka/Screw
 * Description: WordPressサイトにオリジナル画像でのローディング画面を表示するプラグイン
 * Version: 1.0.2
 * Requires at least: 6.0
 * Requires PHP: 7.4
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

// プラグイン定数
define( 'SC_VERSION', '1.0.2' );
define( 'SC_PLUGIN_FILE', __FILE__ );
define( 'SC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * メインプラグインクラス
 */
class Screw {
	/**
	 * シングルトンインスタンス
	 *
	 * @var Screw
	 */
	private static $instance = null;

	/**
	 * シングルトンインスタンスを取得
	 *
	 * @return Screw
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
		// クラスファイルの読み込み
		$this->load_dependencies();

		// フックの登録
		$this->register_hooks();
	}

	/**
	 * クラスファイルの読み込み
	 */
	private function load_dependencies() {
		require_once SC_PLUGIN_DIR . 'includes/class-settings.php';
		require_once SC_PLUGIN_DIR . 'includes/class-updater.php';
		require_once SC_PLUGIN_DIR . 'includes/class-loader.php';
		require_once SC_PLUGIN_DIR . 'includes/class-preview.php';

		// 管理画面のみ
		if ( is_admin() ) {
			require_once SC_PLUGIN_DIR . 'includes/class-admin.php';
		}
	}

	/**
	 * フックの登録
	 */
	private function register_hooks() {
		// 有効化・無効化・アンインストール
		register_activation_hook( SC_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( SC_PLUGIN_FILE, array( $this, 'deactivate' ) );

		// 初期化
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * プラグイン初期化
	 */
	public function init() {
		// 各クラスの初期化
		SC_Settings::get_instance();
		SC_Updater::get_instance();
		SC_Loader::get_instance();
		SC_Preview::get_instance();

		if ( is_admin() ) {
			SC_Admin::get_instance();
		}
	}

	/**
	 * プラグイン有効化時の処理
	 */
	public function activate() {
		// デフォルト設定の作成
		$default_settings = array(
			'loading_image_id'    => 0,
			'loading_image_width' => 90,
			'animation_type'      => 'wipe',
			'wipe_direction'      => 'bottom-top',
			'progressbar_color'   => '#000000',
			'bg_color'            => '#ffffff',
			'bg_image_id'         => 0,
			'display_frequency'   => 'every',
		);

		// 既存設定がない場合のみデフォルトを設定
		if ( false === get_option( 'sc_settings' ) ) {
			update_option( 'sc_settings', $default_settings );
		}

		// バージョン情報を保存
		update_option( 'sc_version', SC_VERSION );
	}

	/**
	 * プラグイン無効化時の処理
	 */
	public function deactivate() {
		// ベータモード関連のトランジェントを削除
		delete_transient( 'sc_beta_channel' );
		delete_transient( 'sc_github_release_cache' );
		delete_transient( 'sc_github_release_cache_beta' );

		// ベータモード試行回数のトランジェントを削除
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_sc_beta_attempts_%'
			OR option_name LIKE '_transient_timeout_sc_beta_attempts_%'"
		);
	}

	/**
	 * プラグインアンインストール時の処理
	 */
	public static function uninstall() {
		// 設定を削除
		delete_option( 'sc_settings' );
		delete_option( 'sc_version' );

		// トランジェントを削除
		delete_transient( 'sc_beta_channel' );
		delete_transient( 'sc_github_release_cache' );
		delete_transient( 'sc_github_release_cache_beta' );

		// ベータモード試行回数のトランジェントを削除
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_sc_beta_attempts_%'
			OR option_name LIKE '_transient_timeout_sc_beta_attempts_%'"
		);
	}
}

// アンインストールフック
register_uninstall_hook( SC_PLUGIN_FILE, array( 'Screw', 'uninstall' ) );

// プラグインの初期化
Screw::get_instance();
