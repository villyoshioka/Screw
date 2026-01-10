<?php
/**
 * 管理画面クラス
 *
 * @package Screw
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SC_Admin
 */
class SC_Admin {
	/**
	 * シングルトンインスタンス
	 *
	 * @var SC_Admin
	 */
	private static $instance = null;

	/**
	 * シングルトンインスタンスを取得
	 *
	 * @return SC_Admin
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
		// フックの登録
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'admin_init', array( $this, 'handle_beta_mode_params' ) );

		// Ajax
		add_action( 'wp_ajax_sc_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_sc_reset_settings', array( $this, 'ajax_reset_settings' ) );
		add_action( 'wp_ajax_sc_enable_beta', array( $this, 'ajax_enable_beta' ) );
		add_action( 'wp_ajax_sc_disable_beta', array( $this, 'ajax_disable_beta' ) );
	}

	/**
	 * 管理画面メニューを追加
	 */
	public function add_admin_menu() {
		add_menu_page(
			'Screw',
			'Screw',
			'manage_options',
			'screw',
			array( $this, 'render_settings_page' ),
			'dashicons-update',
			79
		);
	}

	/**
	 * 管理画面スクリプトを読み込み
	 *
	 * @param string $hook フック名
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'toplevel_page_screw' !== $hook ) {
			return;
		}

		// CSS
		wp_enqueue_style(
			'wp-color-picker'
		);
		wp_enqueue_style(
			'screw-admin',
			SC_PLUGIN_URL . 'assets/css/admin.css',
			array( 'wp-color-picker' ),
			SC_VERSION
		);

		// JS
		wp_enqueue_media();
		wp_enqueue_script(
			'screw-admin',
			SC_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			SC_VERSION,
			true
		);

		// JSに値を渡す
		wp_localize_script(
			'screw-admin',
			'screwAdmin',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'screw_nonce' ),
				'previewNonce' => wp_create_nonce( 'screw_preview' ),
			)
		);
	}

	/**
	 * ベータモードパラメータを処理
	 */
	public function handle_beta_mode_params() {
		if ( ! isset( $_GET['page'] ) || 'screw' !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) || 'screw' !== $_GET['page'] ) {
			return;
		}

		$settings = SC_Settings::get_instance();

		// ベータモード有効化
		if ( isset( $_GET['sc_beta'] ) && 'on' === sanitize_text_field( wp_unslash( $_GET['sc_beta'] ) ) ) {
			// 処理はJavaScriptで行う（パスワード入力）
			return;
		}

		// ベータモード無効化
		if ( isset( $_GET['sc_beta'] ) && 'off' === sanitize_text_field( wp_unslash( $_GET['sc_beta'] ) ) ) {
			// CSRF保護: nonceチェック
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'screw_disable_beta' ) ) {
				wp_die( '不正なリクエストです。' );
			}

			$settings->disable_beta_mode();
			wp_safe_redirect( admin_url( 'admin.php?page=screw&beta_disabled=1' ) );
			exit;
		}
	}

	/**
	 * 設定ページをレンダリング
	 */
	public function render_settings_page() {
		$settings_instance = SC_Settings::get_instance();
		$settings          = $settings_instance->get_settings();
		$is_beta_enabled   = $settings_instance->is_beta_mode_enabled();

		// ローディング画像
		$loading_image_url = '';
		if ( ! empty( $settings['loading_image_id'] ) ) {
			$image = wp_get_attachment_image_src( $settings['loading_image_id'], 'thumbnail' );
			if ( $image ) {
				$loading_image_url = $image[0];
			}
		}

		// 背景画像
		$bg_image_url = '';
		if ( ! empty( $settings['bg_image_id'] ) ) {
			$image = wp_get_attachment_image_src( $settings['bg_image_id'], 'thumbnail' );
			if ( $image ) {
				$bg_image_url = $image[0];
			}
		}

		// ベータモードパラメータチェック
		$show_beta_prompt = isset( $_GET['sc_beta'] ) && 'on' === $_GET['sc_beta'];

		include SC_PLUGIN_DIR . 'views/settings-page.php';
	}

	/**
	 * Ajax: 設定を保存
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'screw_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません。' ) );
		}

		$settings_data = isset( $_POST['settings'] ) ? $_POST['settings'] : array();

		$settings_instance = SC_Settings::get_instance();
		$result            = $settings_instance->save_settings( $settings_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => '設定を保存しました。' ) );
	}

	/**
	 * Ajax: 設定をリセット
	 */
	public function ajax_reset_settings() {
		check_ajax_referer( 'screw_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません。' ) );
		}

		$settings_instance = SC_Settings::get_instance();
		$result            = $settings_instance->reset_settings();

		if ( $result ) {
			wp_send_json_success( array( 'message' => '設定をリセットしました。' ) );
		}

		wp_send_json_error( array( 'message' => 'リセットに失敗しました。' ) );
	}

	/**
	 * Ajax: ベータモードを有効化
	 */
	public function ajax_enable_beta() {
		check_ajax_referer( 'screw_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません。' ) );
		}

		$password = isset( $_POST['password'] ) ? $_POST['password'] : '';

		$settings_instance = SC_Settings::get_instance();
		$result            = $settings_instance->enable_beta_mode( $password );

		if ( true === $result ) {
			wp_send_json_success( array( 'message' => 'ベータモードを有効化しました。' ) );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_error( array( 'message' => 'パスワードが正しくありません。' ) );
	}

	/**
	 * Ajax: ベータモードを無効化
	 */
	public function ajax_disable_beta() {
		check_ajax_referer( 'screw_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません。' ) );
		}

		$settings_instance = SC_Settings::get_instance();
		$settings_instance->disable_beta_mode();

		wp_send_json_success( array( 'message' => 'ベータモードを無効化しました。' ) );
	}
}
