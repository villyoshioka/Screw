<?php
/**
 * 管理画面クラス
 *
 * @package Screw
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SC_Admin {
	private static ?self $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		add_action( 'admin_init', [ $this, 'handle_beta_mode_params' ] );

		add_action( 'wp_ajax_sc_save_settings', [ $this, 'ajax_save_settings' ] );
		add_action( 'wp_ajax_sc_reset_settings', [ $this, 'ajax_reset_settings' ] );
		add_action( 'wp_ajax_sc_export_settings', [ $this, 'ajax_export_settings' ] );
		add_action( 'wp_ajax_sc_import_settings', [ $this, 'ajax_import_settings' ] );
		add_action( 'wp_ajax_sc_upload_image', [ $this, 'ajax_upload_image' ] );
		add_action( 'wp_ajax_sc_store_preview_settings', [ $this, 'ajax_store_preview_settings' ] );
	}

	public function add_admin_menu(): void {
		add_menu_page(
			'Screw',
			'Screw',
			'manage_options',
			'screw',
			[ $this, 'render_settings_page' ],
			'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMCAyMCIgZmlsbD0iY3VycmVudENvbG9yIj48cGF0aCBmaWxsLXJ1bGU9ImV2ZW5vZGQiIGQ9Ik0xMS40OSwyLjg0bC0uNDMtMS41NmgtLjU3Yy0uMDgtLjE3LS4yNy0uMjktLjQ4LS4yOXMtLjQuMTItLjQ4LjI5aC0uNTdsLS40MywxLjU2QzQuMjUsMy40OSwxLDYuODMsMSwxMC44NmMwLDQuNDksNC4wMyw4LjE0LDksOC4xNHM5LTMuNjQsOS04LjE0YzAtNC4wMy0zLjI1LTcuMzctNy41MS04LjAyWk0xMCw0LjE2YzEuMDUsMCwyLjA0LjIsMi45NS41NWwtMi42LDQuMDdjLS4xMS0uMDItLjIzLS4wMy0uMzQtLjAzaDBjLS45OSwwLTEuODMuNTYtMi4xNywxLjM0SDIuNjRjLjQyLTMuMzQsMy41NS01Ljk0LDcuMzYtNS45NFpNMi42NCwxMS42M2g1LjE5Yy4zNC43OCwxLjE4LDEuMzQsMi4xNiwxLjM0aDBjLjEyLDAsLjIzLS4wMi4zNS0uMDNsMi42LDQuMDdjLS45LjM1LTEuOS41NS0yLjk1LjU1LTMuODEsMC02Ljk0LTIuNi03LjM2LTUuOTRaTTE0LjQxLDE2LjI0bC0yLjYtNC4wN2MuMzItLjM2LjUyLS44MS41Mi0xLjMxLDAtLjUtLjItLjk1LS41Mi0xLjMxbDIuNi00LjA3YzEuODIsMS4yMiwzLDMuMTcsMyw1LjM4cy0xLjE4LDQuMTYtMyw1LjM4WiIvPjwvc3ZnPg==',
			79
		);
	}

	public function enqueue_admin_scripts( string $hook ): void {
		if ( 'toplevel_page_screw' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'nau-admin-fw',
			SC_PLUGIN_URL . 'assets/css/admin-fw.css',
			[],
			SC_VERSION
		);

		wp_enqueue_style(
			'screw-admin',
			SC_PLUGIN_URL . 'assets/css/admin.css',
			['nau-admin-fw'],
			SC_VERSION
		);

		wp_enqueue_media();
		wp_enqueue_script(
			'screw-admin',
			SC_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			SC_VERSION,
			true
		);

		wp_localize_script(
			'screw-admin',
			'screwAdmin',
			[
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'screw_nonce' ),
				'previewNonce' => wp_create_nonce( 'screw_preview' ),
				'cpIsRunning'  => (bool) ( get_transient( 'cp_manual_running' ) || get_transient( 'cp_auto_running' ) ),
			]
		);
	}

	public function handle_beta_mode_params(): void {
		if ( ! isset( $_GET['page'] ) || 'screw' !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		if ( isset( $_GET['sc_beta'] ) && 'off' === sanitize_text_field( wp_unslash( $_GET['sc_beta'] ) ) ) {
			$settings = SC_Settings::get_instance();
			$settings->disable_beta_mode();
			wp_safe_redirect( admin_url( 'admin.php?page=screw' ) );
			exit;
		}
	}

	public function render_settings_page(): void {
		$settings_instance = SC_Settings::get_instance();
		$settings          = $settings_instance->get_settings();

		$beta_message = '';
		if ( isset( $_GET['sc_beta'] ) && 'on' === sanitize_text_field( wp_unslash( $_GET['sc_beta'] ) ) ) {
			if ( $settings_instance->is_beta_mode_enabled() ) {
				// noop
			} elseif ( isset( $_POST['sc_beta_password'] ) && isset( $_POST['sc_beta_nonce'] ) ) {
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

		$loading_image_url = '';
		if ( ! empty( $settings['loading_image_id'] ) ) {
			$image = wp_get_attachment_image_src( $settings['loading_image_id'], 'thumbnail' );
			if ( $image ) {
				$loading_image_url = $image[0];
			}
		}

		$bg_image_url = '';
		if ( ! empty( $settings['bg_image_id'] ) ) {
			$image = wp_get_attachment_image_src( $settings['bg_image_id'], 'thumbnail' );
			if ( $image ) {
				$bg_image_url = $image[0];
			}
		}

		$cp_is_running = (bool) ( get_transient( 'cp_manual_running' ) || get_transient( 'cp_auto_running' ) );

		include SC_PLUGIN_DIR . 'views/settings-page.php';
	}

	public function ajax_save_settings(): void {
		check_ajax_referer( 'screw_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => '権限がありません。' ] );
		}

		if ( get_transient( 'cp_manual_running' ) || get_transient( 'cp_auto_running' ) ) {
			wp_send_json_error( [ 'message' => 'Carry Podの静的化実行中は設定を変更できません。' ] );
		}

		$settings_data = $_POST['settings'] ?? [];

		$settings_instance = SC_Settings::get_instance();
		$result            = $settings_instance->save_settings( $settings_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'message' => '設定を保存しました。' ] );
	}

	public function ajax_reset_settings(): void {
		check_ajax_referer( 'screw_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => '権限がありません。' ] );
		}

		if ( get_transient( 'cp_manual_running' ) || get_transient( 'cp_auto_running' ) ) {
			wp_send_json_error( [ 'message' => 'Carry Podの静的化実行中は設定をリセットできません。' ] );
		}

		$settings_instance = SC_Settings::get_instance();
		$result            = $settings_instance->reset_settings();

		if ( $result ) {
			wp_send_json_success( [ 'message' => '設定をリセットしました。' ] );
		}

		wp_send_json_error( [ 'message' => 'リセットに失敗しました。' ] );
	}

	public function ajax_export_settings(): void {
		check_ajax_referer( 'screw_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => '権限がありません。' ] );
		}

		$settings_instance = SC_Settings::get_instance();
		$json              = $settings_instance->export_settings();

		wp_send_json_success( [ 'data' => $json ] );
	}

	public function ajax_import_settings(): void {
		check_ajax_referer( 'screw_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => '権限がありません。' ] );
		}

		if ( get_transient( 'cp_manual_running' ) || get_transient( 'cp_auto_running' ) ) {
			wp_send_json_error( [ 'message' => 'Carry Podの静的化実行中は設定をインポートできません。' ] );
		}

		if ( ! isset( $_POST['data'] ) ) {
			wp_send_json_error( [ 'message' => 'データが送信されていません。' ] );
		}

		// XSS対策
		$import_data       = sanitize_textarea_field( wp_unslash( $_POST['data'] ) );
		$settings_instance = SC_Settings::get_instance();
		$result            = $settings_instance->import_settings( $import_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'message' => '設定をインポートしました。' ] );
	}

	public function ajax_upload_image(): void {
		check_ajax_referer( 'screw_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => '権限がありません。' ] );
		}

		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( [ 'message' => 'ファイルが送信されていません。' ] );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$file = $_FILES['file'];

		// wp_check_filetype使用でセキュリティ強化
		$filetype      = wp_check_filetype( $file['name'] );
		$allowed_types = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];

		if ( ! in_array( $filetype['type'], $allowed_types, true ) ) {
			wp_send_json_error( [ 'message' => '画像ファイルのみアップロード可能です。' ] );
		}

		// getimagesizeでバイナリチェック
		$image_info = getimagesize( $file['tmp_name'] );
		if ( false === $image_info ) {
			wp_send_json_error( [ 'message' => '無効な画像ファイルです。' ] );
		}

		if ( $file['size'] > 10 * 1024 * 1024 ) {
			wp_send_json_error( [ 'message' => 'ファイルサイズは10MB以下にしてください。' ] );
		}

		$attachment_id = media_handle_upload( 'file', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( [ 'message' => $attachment_id->get_error_message() ] );
		}

		$attachment_url = wp_get_attachment_url( $attachment_id );

		wp_send_json_success( [
			'id'  => $attachment_id,
			'url' => $attachment_url,
		] );
	}

	public function check_carry_pod_compatibility(): void {
		if ( ! defined( 'CP_VERSION' ) ) {
			return;
		}

		$cp_version = CP_VERSION;

		if ( ! preg_match( '/^\d+\.\d+\.\d+(?:-[a-zA-Z0-9\-]+)?$/', $cp_version ) ) {
			return;
		}

		if ( version_compare( $cp_version, '3.2.0', '>=' ) ) {
			return;
		}

		?>
		<div class="notice notice-warning">
			<p>
				<strong>⚠️ CarryPod連携</strong><br>
				CarryPod 3.2.0以降にアップデートすると、双方向連携機能が有効になります。<br>
				<small>現在: CarryPod <?php echo esc_html( $cp_version ); ?> → 推奨: CarryPod 3.2.0+</small>
			</p>
		</div>
		<?php
	}

	public function ajax_store_preview_settings(): void {
		check_ajax_referer( 'screw_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => '権限がありません。' ] );
		}

		if ( ! isset( $_POST['settings'] ) ) {
			wp_send_json_error( [ 'message' => '設定データが送信されていません。' ] );
		}

		$key = 'screw_preview_' . wp_generate_password( 32, false );

		set_transient( $key, $_POST['settings'], 5 * MINUTE_IN_SECONDS );

		wp_send_json_success( [ 'key' => $key ] );
	}
}
