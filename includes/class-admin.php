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
		add_action( 'wp_ajax_sc_export_settings', array( $this, 'ajax_export_settings' ) );
		add_action( 'wp_ajax_sc_import_settings', array( $this, 'ajax_import_settings' ) );
		add_action( 'wp_ajax_sc_upload_image', array( $this, 'ajax_upload_image' ) );
		add_action( 'wp_ajax_sc_store_preview_settings', array( $this, 'ajax_store_preview_settings' ) );
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
		if ( ! isset( $_GET['page'] ) || 'screw' !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		// ベータモード無効化
		if ( isset( $_GET['sc_beta'] ) && 'off' === sanitize_text_field( wp_unslash( $_GET['sc_beta'] ) ) ) {
			$settings = SC_Settings::get_instance();
			$settings->disable_beta_mode();
			wp_safe_redirect( admin_url( 'admin.php?page=screw' ) );
			exit;
		}
	}

	/**
	 * 設定ページをレンダリング
	 */
	public function render_settings_page() {
		$settings_instance = SC_Settings::get_instance();
		$settings          = $settings_instance->get_settings();

		// ベータモードのURLパラメータ処理
		$beta_message = '';
		if ( isset( $_GET['sc_beta'] ) && 'on' === sanitize_text_field( wp_unslash( $_GET['sc_beta'] ) ) ) {
			$beta_param = sanitize_text_field( wp_unslash( $_GET['sc_beta'] ) );
			// 既にベータモードが有効な場合はスキップ
			if ( $settings_instance->is_beta_mode_enabled() ) {
				// 既に有効、何もしない
			} elseif ( isset( $_POST['sc_beta_password'] ) && isset( $_POST['sc_beta_nonce'] ) ) {
				// パスワード認証処理
				if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sc_beta_nonce'] ) ), 'sc_beta_auth' ) ) {
					$password = sanitize_text_field( wp_unslash( $_POST['sc_beta_password'] ) );
					$result   = $settings_instance->enable_beta_mode( $password );
					if ( is_wp_error( $result ) ) {
						$beta_message = 'rate_limit';
					} elseif ( true === $result ) {
						$beta_message = 'activated';
					} else {
						$beta_message = 'wrong_password';
					}
				}
			} else {
				$beta_message = 'need_password';
			}
		}

		$is_beta_enabled = $settings_instance->is_beta_mode_enabled();

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
	 * Ajax: 設定をエクスポート
	 */
	public function ajax_export_settings() {
		check_ajax_referer( 'screw_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません。' ) );
		}

		$settings_instance = SC_Settings::get_instance();
		$json              = $settings_instance->export_settings();

		wp_send_json_success( array( 'data' => $json ) );
	}

	/**
	 * Ajax: 設定をインポート
	 */
	public function ajax_import_settings() {
		check_ajax_referer( 'screw_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません。' ) );
		}

		if ( ! isset( $_POST['data'] ) ) {
			wp_send_json_error( array( 'message' => 'データが送信されていません。' ) );
		}

		// JSONデータをサニタイズ（XSS対策）
		$import_data       = sanitize_textarea_field( wp_unslash( $_POST['data'] ) );
		$settings_instance = SC_Settings::get_instance();
		$result            = $settings_instance->import_settings( $import_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => '設定をインポートしました。' ) );
	}

	/**
	 * Ajax: 画像アップロード
	 */
	public function ajax_upload_image() {
		check_ajax_referer( 'screw_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません。' ) );
		}

		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => 'ファイルが送信されていません。' ) );
		}

		// WordPress標準のメディアアップロード処理
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$file = $_FILES['file'];

		// ファイル形式チェック（wp_check_filetype使用でセキュリティ強化）
		$filetype = wp_check_filetype( $file['name'] );
		$allowed_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );

		if ( ! in_array( $filetype['type'], $allowed_types, true ) ) {
			wp_send_json_error( array( 'message' => '画像ファイルのみアップロード可能です。' ) );
		}

		// 画像ファイル内容検証（getimagesizeでバイナリチェック）
		$image_info = getimagesize( $file['tmp_name'] );
		if ( false === $image_info ) {
			wp_send_json_error( array( 'message' => '無効な画像ファイルです。' ) );
		}

		// ファイルサイズチェック (10MB)
		if ( $file['size'] > 10 * 1024 * 1024 ) {
			wp_send_json_error( array( 'message' => 'ファイルサイズは10MB以下にしてください。' ) );
		}

		// アップロード処理
		$attachment_id = media_handle_upload( 'file', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		$attachment_url = wp_get_attachment_url( $attachment_id );

		wp_send_json_success(
			array(
				'id'  => $attachment_id,
				'url' => $attachment_url,
			)
		);
	}

	/**
	 * Ajax: プレビュー設定をtransientに保存
	 */
	public function ajax_store_preview_settings() {
		check_ajax_referer( 'screw_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません。' ) );
		}

		if ( ! isset( $_POST['settings'] ) ) {
			wp_send_json_error( array( 'message' => '設定データが送信されていません。' ) );
		}

		// ランダムなkeyを生成
		$key = 'screw_preview_' . wp_generate_password( 32, false );

		// transientに設定を保存（有効期限: 5分）
		set_transient( $key, $_POST['settings'], 5 * MINUTE_IN_SECONDS );

		wp_send_json_success( array( 'key' => $key ) );
	}

}
