<?php
/**
 * 設定管理クラス
 *
 * @package Screw
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SC_Settings
 */
class SC_Settings {
	/**
	 * シングルトンインスタンス
	 *
	 * @var SC_Settings
	 */
	private static $instance = null;

	/**
	 * ベータモードパスワードハッシュ (SHA-256)
	 * パスワード: SC_test9999
	 *
	 * @var string
	 */
	private $beta_password_hash = '97e2193a5b8403122ee5d03cc9f488a900b94c1b6261d238cb4265af978e3cba';

	/**
	 * シングルトンインスタンスを取得
	 *
	 * @return SC_Settings
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
		// 何もしない（フック登録等は必要に応じて追加）
	}

	/**
	 * ベータモードが有効かどうか
	 *
	 * @return bool
	 */
	public function is_beta_mode_enabled() {
		return (bool) get_transient( 'sc_beta_channel' );
	}

	/**
	 * ベータモードを有効化
	 *
	 * @param string $password パスワード
	 * @return bool|WP_Error 成功時true、失敗時false、レート制限時WP_Error
	 */
	public function enable_beta_mode( $password ) {
		$user_id      = get_current_user_id();
		$attempts_key = 'sc_beta_attempts_' . $user_id;

		// レート制限チェック（5回失敗で10分間ロック）
		$attempts = get_transient( $attempts_key );
		if ( $attempts >= 5 ) {
			return new WP_Error(
				'rate_limit',
				'ログイン試行回数が超過しました。10分後に再試行してください。'
			);
		}

		// タイミングセーフなハッシュ比較
		if ( hash_equals( $this->beta_password_hash, hash( 'sha256', $password ) ) ) {
			// 成功時は試行回数をクリア
			delete_transient( $attempts_key );

			// ベータチャンネルを有効化（24時間）
			set_transient( 'sc_beta_channel', true, DAY_IN_SECONDS );

			return true;
		}

		// 失敗回数をインクリメント（10分間保持）
		$new_attempts = $attempts ? $attempts + 1 : 1;
		set_transient( $attempts_key, $new_attempts, 10 * MINUTE_IN_SECONDS );

		return false;
	}

	/**
	 * ベータモードを無効化
	 */
	public function disable_beta_mode() {
		delete_transient( 'sc_beta_channel' );

		// ベータ用キャッシュもクリア
		delete_transient( 'sc_github_release_cache_beta' );
	}

	/**
	 * 設定を取得
	 *
	 * @return array
	 */
	public function get_settings() {
		$defaults = array(
			'loading_image_id'    => 0,
			'loading_image_width' => 90,
			'animation_type'      => 'wipe',
			'wipe_direction'      => 'bottom-top',
			'progressbar_color'   => '#000000',
			'bg_color'            => '#ffffff',
			'bg_image_id'         => 0,
			'display_frequency'   => 'every',
		);

		$settings = get_option( 'sc_settings', $defaults );

		// デフォルト値とマージ
		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * 設定を保存
	 *
	 * @param array $settings 設定値
	 * @return bool|WP_Error 成功時true、失敗時WP_Error
	 */
	public function save_settings( $settings ) {
		// バリデーション
		$validation = $this->validate_settings( $settings );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// サニタイズ
		$sanitized = $this->sanitize_settings( $settings );

		// 保存（update_option は値が同じ場合 false を返すが、これは正常動作）
		update_option( 'sc_settings', $sanitized );

		// CarryPodのキャッシュをクリア
		$this->clear_carrypod_cache();

		return true;
	}

	/**
	 * 設定のバリデーション
	 *
	 * @param array $settings 設定値
	 * @return bool|WP_Error 成功時true、失敗時WP_Error
	 */
	private function validate_settings( $settings ) {
		// ローディング画像IDは必須
		if ( empty( $settings['loading_image_id'] ) || 0 === intval( $settings['loading_image_id'] ) ) {
			return new WP_Error( 'missing_image', 'ローディング画像を選択してください。' );
		}

		// 画像IDの存在確認
		$image = wp_get_attachment_image_src( intval( $settings['loading_image_id'] ), 'full' );
		if ( ! $image ) {
			return new WP_Error( 'invalid_image', '選択された画像が見つかりません。' );
		}

		// 横幅は正の整数
		if ( isset( $settings['loading_image_width'] ) && intval( $settings['loading_image_width'] ) <= 0 ) {
			return new WP_Error( 'invalid_width', '画像の横幅は正の数値を指定してください。' );
		}

		// アニメーションタイプの妥当性
		$valid_types = array( 'wipe', 'progressbar' );
		if ( ! in_array( $settings['animation_type'], $valid_types, true ) ) {
			return new WP_Error( 'invalid_animation_type', '無効なアニメーションタイプです。' );
		}

		// ワイプ方向の妥当性
		$valid_directions = array( 'bottom-top', 'top-bottom', 'left-right', 'right-left' );
		if ( 'wipe' === $settings['animation_type'] && ! in_array( $settings['wipe_direction'], $valid_directions, true ) ) {
			return new WP_Error( 'invalid_wipe_direction', '無効なワイプ方向です。' );
		}

		// 色コードの妥当性
		if ( isset( $settings['bg_color'] ) && ! preg_match( '/^#[0-9A-Fa-f]{6}$/', $settings['bg_color'] ) ) {
			return new WP_Error( 'invalid_bg_color', '背景色は6桁のHEXカラーコードを指定してください。' );
		}

		if ( isset( $settings['progressbar_color'] ) && ! preg_match( '/^#[0-9A-Fa-f]{6}$/', $settings['progressbar_color'] ) ) {
			return new WP_Error( 'invalid_progressbar_color', 'プログレスバーの色は6桁のHEXカラーコードを指定してください。' );
		}

		// 表示頻度の妥当性
		$valid_frequencies = array( 'once', 'every' );
		if ( ! in_array( $settings['display_frequency'], $valid_frequencies, true ) ) {
			return new WP_Error( 'invalid_display_frequency', '無効な表示頻度です。' );
		}

		return true;
	}

	/**
	 * 設定のサニタイズ
	 *
	 * @param array $settings 設定値
	 * @return array サニタイズ済み設定値
	 */
	private function sanitize_settings( $settings ) {
		$sanitized = array();

		$sanitized['loading_image_id']    = intval( $settings['loading_image_id'] );
		$sanitized['loading_image_width'] = intval( $settings['loading_image_width'] );
		$sanitized['animation_type']      = sanitize_text_field( $settings['animation_type'] );
		$sanitized['wipe_direction']      = sanitize_text_field( $settings['wipe_direction'] );
		$sanitized['progressbar_color']   = sanitize_hex_color( $settings['progressbar_color'] );
		$sanitized['bg_color']            = sanitize_hex_color( $settings['bg_color'] );
		$sanitized['bg_image_id']         = intval( $settings['bg_image_id'] );
		$sanitized['display_frequency']   = sanitize_text_field( $settings['display_frequency'] );

		return $sanitized;
	}

	/**
	 * 設定をリセット
	 *
	 * @return bool
	 */
	public function reset_settings() {
		$defaults = array(
			'loading_image_id'    => 0,
			'loading_image_width' => 90,
			'animation_type'      => 'wipe',
			'wipe_direction'      => 'bottom-top',
			'progressbar_color'   => '#000000',
			'bg_color'            => '#ffffff',
			'bg_image_id'         => 0,
			'display_frequency'   => 'every',
		);

		// 既存の設定を削除してから再設定
		delete_option( 'sc_settings' );
		$result = update_option( 'sc_settings', $defaults );

		// CarryPodのキャッシュをクリア
		$this->clear_carrypod_cache();

		return $result;
	}

	/**
	 * CarryPodのキャッシュをクリア
	 */
	private function clear_carrypod_cache() {
		// CarryPodが有効化されているか確認
		if ( ! class_exists( 'CP_Cache' ) ) {
			return;
		}

		try {
			$cache = CP_Cache::get_instance();
			$cache->clear_all();
		} catch ( Exception $e ) {
			// エラーが発生してもScrewの設定保存は成功扱い
			error_log( 'Screw: CarryPodキャッシュクリアに失敗しました - ' . $e->getMessage() );
		}
	}
}
