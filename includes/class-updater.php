<?php
/**
 * 自動更新クラス
 *
 * @package Screw
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SC_Updater
 */
class SC_Updater {
	/**
	 * シングルトンインスタンス
	 *
	 * @var SC_Updater
	 */
	private static $instance = null;

	/**
	 * GitHubオーナー名
	 *
	 * @var string
	 */
	private $github_owner = 'villyoshioka';

	/**
	 * GitHubリポジトリ名
	 *
	 * @var string
	 */
	private $github_repo = 'Screw';

	/**
	 * プラグインベースネーム
	 *
	 * @var string
	 */
	private $plugin_basename;

	/**
	 * プラグインスラッグ
	 *
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * 現在のバージョン
	 *
	 * @var string
	 */
	private $current_version;

	/**
	 * キャッシュキー
	 *
	 * @var string
	 */
	private $cache_key = 'sc_github_release_cache';

	/**
	 * キャッシュ有効期限（秒）
	 *
	 * @var int
	 */
	private $cache_expiry = 43200; // 12時間

	/**
	 * シングルトンインスタンスを取得
	 *
	 * @return SC_Updater
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
		$this->plugin_basename = SC_PLUGIN_BASENAME;
		$this->plugin_slug     = dirname( $this->plugin_basename );
		$this->current_version = SC_VERSION;

		// フックの登録
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'on_upgrade_complete' ), 10, 2 );
	}

	/**
	 * アップグレード完了時の処理
	 *
	 * @param WP_Upgrader $upgrader アップグレーダー
	 * @param array       $options オプション
	 */
	public function on_upgrade_complete( $upgrader, $options ) {
		if ( 'update' !== $options['action'] || 'plugin' !== $options['type'] ) {
			return;
		}

		$plugins = isset( $options['plugins'] ) ? $options['plugins'] : array();

		if ( in_array( $this->plugin_basename, $plugins, true ) ) {
			// GitHubリリースキャッシュをクリア
			delete_transient( $this->cache_key );
			delete_transient( $this->cache_key . '_beta' );

			// WordPressの更新トランジェントを更新
			$update_plugins = get_site_transient( 'update_plugins' );
			if ( $update_plugins ) {
				// responseから削除
				if ( isset( $update_plugins->response[ $this->plugin_basename ] ) ) {
					unset( $update_plugins->response[ $this->plugin_basename ] );
				}
				// checkedを新しいバージョンに更新
				if ( isset( $update_plugins->checked ) ) {
					$update_plugins->checked[ $this->plugin_basename ] = SC_VERSION;
				}
				set_site_transient( 'update_plugins', $update_plugins );
			}
		}
	}

	/**
	 * アップデートをチェック
	 *
	 * @param object $transient トランジェント
	 * @return object
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $transient;
		}

		$latest_version = $this->get_version_from_release( $release );
		if ( ! $latest_version ) {
			return $transient;
		}

		// WordPressが認識している実際のインストール済みバージョンを使用
		$current_version = isset( $transient->checked[ $this->plugin_basename ] )
			? $transient->checked[ $this->plugin_basename ]
			: $this->current_version;

		// バージョン比較（同じバージョンの場合は更新を表示しない）
		if ( version_compare( $current_version, $latest_version, '<' ) ) {
			// メジャーバージョンが異なる場合は自動更新を提供しない
			$current_major = $this->get_major_version( $current_version );
			$latest_major  = $this->get_major_version( $latest_version );

			if ( $current_major !== $latest_major ) {
				return $transient;
			}

			$download_url = $this->get_download_url( $release );
			if ( ! $download_url ) {
				return $transient;
			}

			$plugin_data = array(
				'slug'        => $this->plugin_slug,
				'plugin'      => $this->plugin_basename,
				'new_version' => $latest_version,
				'url'         => 'https://github.com/' . $this->github_owner . '/' . $this->github_repo,
				'package'     => $download_url,
			);

			$transient->response[ $this->plugin_basename ] = (object) $plugin_data;
		} else {
			// 更新不要の場合、responseから削除してno_updateに移動
			if ( isset( $transient->response[ $this->plugin_basename ] ) ) {
				unset( $transient->response[ $this->plugin_basename ] );
			}
			// no_updateに登録（最新版であることを明示）
			if ( ! isset( $transient->no_update[ $this->plugin_basename ] ) ) {
				$transient->no_update[ $this->plugin_basename ] = (object) array(
					'slug'        => $this->plugin_slug,
					'plugin'      => $this->plugin_basename,
					'new_version' => $current_version,
					'url'         => '',
					'package'     => '',
				);
			}
		}

		return $transient;
	}

	/**
	 * プラグイン情報を取得
	 *
	 * @param false|object|array $result プラグイン情報
	 * @param string             $action アクション
	 * @param object             $args 引数
	 * @return false|object
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( 'screw' !== $args->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		$version = $this->get_version_from_release( $release );
		if ( ! $version ) {
			return $result;
		}

		$download_url = $this->get_download_url( $release );

		$info = array(
			'name'          => 'Screw',
			'slug'          => 'screw',
			'version'       => $version,
			'author'        => '<a href="https://github.com/villyoshioka">Vill Yoshioka</a>',
			'homepage'      => 'https://github.com/' . $this->github_owner . '/' . $this->github_repo,
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'download_link' => $download_url,
			'sections'      => array(
				'description' => 'WordPressサイトにオリジナル画像でのローディング画面を表示するプラグイン',
			),
		);

		return (object) $info;
	}

	/**
	 * 最新リリースを取得
	 *
	 * @return array|false
	 */
	private function get_latest_release() {
		$include_prerelease = $this->is_beta_channel_enabled();
		$cache_key          = $include_prerelease ? $this->cache_key . '_beta' : $this->cache_key;

		// キャッシュをチェック
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		// API URL
		if ( $include_prerelease ) {
			$url = sprintf(
				'https://api.github.com/repos/%s/%s/releases',
				$this->github_owner,
				$this->github_repo
			);
		} else {
			$url = sprintf(
				'https://api.github.com/repos/%s/%s/releases/latest',
				$this->github_owner,
				$this->github_repo
			);
		}

		// API呼び出し
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/vnd.github.v3+json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! $data ) {
			return false;
		}

		$release = null;

		if ( $include_prerelease && is_array( $data ) ) {
			// 全リリースから最新を取得
			foreach ( $data as $item ) {
				if ( isset( $item['tag_name'] ) ) {
					$release = $item;
					break;
				}
			}
		} else {
			$release = $data;
		}

		if ( ! $release ) {
			return false;
		}

		// キャッシュに保存
		set_transient( $cache_key, $release, $this->cache_expiry );

		return $release;
	}

	/**
	 * ダウンロードURLを取得
	 *
	 * @param array $release リリース情報
	 * @return string|false
	 */
	private function get_download_url( $release ) {
		if ( ! isset( $release['assets'] ) || ! is_array( $release['assets'] ) ) {
			return false;
		}

		// screw.zip を探す
		foreach ( $release['assets'] as $asset ) {
			if ( isset( $asset['name'] ) && 'screw.zip' === $asset['name'] ) {
				$url = isset( $asset['browser_download_url'] ) ? $asset['browser_download_url'] : '';

				// セキュリティチェック
				if ( $this->is_valid_github_url( $url ) ) {
					return $url;
				}
			}
		}

		return false;
	}

	/**
	 * GitHub URLの妥当性を検証
	 *
	 * @param string $url URL
	 * @return bool
	 */
	private function is_valid_github_url( $url ) {
		$allowed_hosts = array(
			'api.github.com',
			'github.com',
			'codeload.github.com',
			'objects.githubusercontent.com',
		);

		$parsed = wp_parse_url( $url );
		if ( ! isset( $parsed['host'] ) ) {
			return false;
		}

		return in_array( $parsed['host'], $allowed_hosts, true );
	}

	/**
	 * リリースからバージョンを取得
	 *
	 * @param array $release リリース情報
	 * @return string|false
	 */
	private function get_version_from_release( $release ) {
		if ( ! isset( $release['tag_name'] ) ) {
			return false;
		}

		// v1.0.0 → 1.0.0
		return ltrim( $release['tag_name'], 'v' );
	}

	/**
	 * メジャーバージョンを取得
	 *
	 * @param string $version バージョン
	 * @return string
	 */
	private function get_major_version( $version ) {
		$parts = explode( '.', $version );
		return isset( $parts[0] ) ? $parts[0] : '0';
	}

	/**
	 * ベータチャンネルが有効かどうか
	 *
	 * @return bool
	 */
	private function is_beta_channel_enabled() {
		return (bool) get_transient( 'sc_beta_channel' );
	}

	/**
	 * ソースディレクトリ名を修正
	 *
	 * @param string      $source ソースパス
	 * @param string      $remote_source リモートソース
	 * @param WP_Upgrader $upgrader アップグレーダー
	 * @param array       $hook_extra フック追加情報
	 * @return string|WP_Error
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;

		// このプラグインの更新でない場合はスキップ
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_basename ) {
			return $source;
		}

		// 正しいディレクトリ名
		$correct_dir = 'screw';

		// パストラバーサル対策
		$source_real = realpath( $source );
		if ( false === $source_real || false !== strpos( $source_real, "\0" ) ) {
			return new WP_Error( 'invalid_source', 'ソースディレクトリが無効です。' );
		}

		// 現在のディレクトリ名
		$source_basename = basename( $source );

		// 既に正しい名前の場合はそのまま返す
		if ( $correct_dir === $source_basename ) {
			return $source;
		}

		// 新しいパス
		$new_source = trailingslashit( $remote_source ) . $correct_dir;

		// リネーム
		if ( $wp_filesystem->move( $source, $new_source ) ) {
			return $new_source;
		}

		return new WP_Error( 'rename_failed', 'ディレクトリ名の変更に失敗しました。' );
	}
}
