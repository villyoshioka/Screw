<?php
/**
 * 設定ページビュー
 *
 * @package Screw
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap nau-admin-wrap">
	<h1>Screw 設定</h1>

	<?php $this->check_carry_pod_compatibility(); ?>

	<?php if ( $is_beta_enabled ) : ?>
	<div class="notice notice-info">
		<p><strong>ベータモード</strong> - プレリリース版のアップデートが有効です。無効にするには <code>&sc_beta=off</code> を追加してください。</p>
	</div>
	<?php endif; ?>

	<?php if ( $beta_message === 'need_password' ) : ?>
	<div class="notice notice-warning">
		<p><strong>ベータモード認証</strong></p>
		<form method="post" style="margin: 10px 0;">
			<?php wp_nonce_field( 'sc_beta_auth', 'sc_beta_nonce' ); ?>
			<input type="password" name="sc_beta_password" placeholder="パスワードを入力" style="width: 200px;" />
			<input type="submit" class="button" value="認証" />
		</form>
	</div>
	<?php elseif ( $beta_message === 'rate_limit' ) : ?>
	<div class="notice notice-error">
		<p>ログイン試行回数が超過しました。10分後に再試行してください。</p>
	</div>
	<?php elseif ( $beta_message === 'wrong_password' ) : ?>
	<div class="notice notice-error">
		<p>パスワードが正しくありません。</p>
	</div>
	<div class="notice notice-warning">
		<p><strong>ベータモード認証</strong></p>
		<form method="post" style="margin: 10px 0;">
			<?php wp_nonce_field( 'sc_beta_auth', 'sc_beta_nonce' ); ?>
			<input type="password" name="sc_beta_password" placeholder="パスワードを入力" style="width: 200px;" />
			<input type="submit" class="button" value="認証" />
		</form>
	</div>
	<?php elseif ( $beta_message === 'activated' ) : ?>
	<div class="notice notice-success">
		<p>ベータモードを有効化しました。</p>
	</div>
	<?php endif; ?>

	<div id="screw-message-container"></div>

	<form id="screw-settings-form" class="nau-settings-form">
		<?php wp_nonce_field( 'screw_settings_form', 'screw_settings_nonce' ); ?>
		<!-- 基本設定 -->
		<div class="nau-accordion-section" data-section="basic">
			<button type="button" class="nau-accordion-header" aria-expanded="true" data-section="basic">
				<span class="nau-accordion-title">基本設定</span>
				<span class="nau-accordion-icon"></span>
			</button>
			<div class="nau-accordion-content" aria-hidden="false">
				<!-- ローディング画像 -->
				<div class="nau-form-group">
					<label>
						ローディング画像 <span class="required">*</span>
						<span class="nau-tooltip-wrapper">
							<span class="nau-tooltip-trigger" tabindex="0" role="button" aria-label="詳細を表示" aria-expanded="false">?</span>
							<span class="nau-tooltip-content" role="tooltip">読み込み中に表示する画像です。GIFアニメーションにも対応しています。</span>
						</span>
					</label>
					<div>
						<input type="hidden" id="loading_image_id" name="loading_image_id" value="<?php echo esc_attr( $settings['loading_image_id'] ); ?>">
						<div class="nau-image-upload-area" data-target="loading_image_id">
							<?php if ( $loading_image_url ) : ?>
								<div class="nau-image-selected">
									<img src="<?php echo esc_url( $loading_image_url ); ?>" alt="">
									<div class="nau-image-buttons">
										<button type="button" class="button nau-remove-button" data-target="loading_image_id">削除</button>
									</div>
								</div>
							<?php else : ?>
								<div class="nau-image-placeholder">
									<div class="nau-image-placeholder-text">画像をアップロード、またはライブラリから選択してください。</div>
									<button type="button" class="button nau-media-button" data-target="loading_image_id">メディアライブラリ</button>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- 画像横幅 -->
				<div class="nau-form-group">
					<label for="loading_image_width">
						画像横幅（px）
						<span class="nau-tooltip-wrapper">
							<span class="nau-tooltip-trigger" tabindex="0" role="button" aria-label="詳細を表示" aria-expanded="false">?</span>
							<span class="nau-tooltip-content" role="tooltip">表示サイズをpxで指定します。縦横比は自動で維持されます。</span>
						</span>
					</label>
					<div>
						<input type="number" id="loading_image_width" name="loading_image_width" value="<?php echo esc_attr( $settings['loading_image_width'] ); ?>" min="1" step="1" class="small-text">
					</div>
				</div>

				<!-- アニメーションタイプ -->
				<div class="nau-form-group">
					<label for="animation_type">
						アニメーションタイプ
						<span class="nau-tooltip-wrapper">
							<span class="nau-tooltip-trigger" tabindex="0" role="button" aria-label="詳細を表示" aria-expanded="false">?</span>
							<span class="nau-tooltip-content" role="tooltip">ワイプ: 画像が徐々に現れます<br>プログレスバー: 読み込みの進捗を表示します<br>スピナー: 回転リングを表示します<br>アニメーションなし: 画像のみ表示します</span>
						</span>
					</label>
					<div>
						<select id="animation_type" name="animation_type">
							<option value="none" <?php selected( $settings['animation_type'], 'none' ); ?>>アニメーションなし</option>
							<option value="wipe" <?php selected( $settings['animation_type'], 'wipe' ); ?>>ワイプ</option>
							<option value="progressbar" <?php selected( $settings['animation_type'], 'progressbar' ); ?>>プログレスバー</option>
							<option value="spinner" <?php selected( $settings['animation_type'], 'spinner' ); ?>>スピナー</option>
						</select>
					</div>
				</div>

				<!-- ワイプ方向 -->
				<div class="nau-form-group screw-wipe-option" style="<?php echo esc_attr( 'wipe' !== $settings['animation_type'] ? 'display:none;' : '' ); ?>">
					<label for="wipe_direction">
						ワイプ方向
						<span class="nau-tooltip-wrapper">
							<span class="nau-tooltip-trigger" tabindex="0" role="button" aria-label="詳細を表示" aria-expanded="false">?</span>
							<span class="nau-tooltip-content" role="tooltip">ワイプアニメーションの方向を指定します。</span>
						</span>
					</label>
					<div>
						<select id="wipe_direction" name="wipe_direction">
							<option value="bottom-top" <?php selected( $settings['wipe_direction'], 'bottom-top' ); ?>>下から上</option>
							<option value="top-bottom" <?php selected( $settings['wipe_direction'], 'top-bottom' ); ?>>上から下</option>
							<option value="left-right" <?php selected( $settings['wipe_direction'], 'left-right' ); ?>>左から右</option>
							<option value="right-left" <?php selected( $settings['wipe_direction'], 'right-left' ); ?>>右から左</option>
						</select>
					</div>
				</div>

				<!-- プログレスバーの色 -->
				<div class="nau-form-group screw-progressbar-option" style="<?php echo esc_attr( 'progressbar' !== $settings['animation_type'] ? 'display:none;' : '' ); ?>">
					<label>
						プログレスバーの色
						<span class="nau-tooltip-wrapper">
							<span class="nau-tooltip-trigger" tabindex="0" role="button" aria-label="詳細を表示" aria-expanded="false">?</span>
							<span class="nau-tooltip-content" role="tooltip">プログレスバーの色を指定します。</span>
						</span>
					</label>
					<div>
						<input type="hidden" id="progressbar_color" name="progressbar_color" value="<?php echo esc_attr( $settings['progressbar_color'] ); ?>">
						<div class="nau-color-panel" data-target="progressbar_color" data-default="#000000">
							<div class="nau-color-custom-input">
								<div class="nau-color-preview" style="background-color: <?php echo esc_attr( $settings['progressbar_color'] ?: 'transparent' ); ?>"></div>
								<input type="text" class="nau-color-hex-input" maxlength="7" value="<?php echo esc_attr( $settings['progressbar_color'] ); ?>">
								<span class="nau-color-native-wrap">
									<input type="color" class="nau-color-native" value="<?php echo esc_attr( $settings['progressbar_color'] ?: '#000000' ); ?>">
								</span>
							</div>
							<button type="button" class="nau-color-clear">クリア</button>
						</div>
					</div>
				</div>

				<!-- スピナーの色 -->
				<div class="nau-form-group screw-spinner-option" style="<?php echo esc_attr( 'spinner' !== $settings['animation_type'] ? 'display:none;' : '' ); ?>">
					<label>
						スピナーの色
						<span class="nau-tooltip-wrapper">
							<span class="nau-tooltip-trigger" tabindex="0" role="button" aria-label="詳細を表示" aria-expanded="false">?</span>
							<span class="nau-tooltip-content" role="tooltip">回転リングの色を指定します。</span>
						</span>
					</label>
					<div>
						<input type="hidden" id="spinner_color" name="spinner_color" value="<?php echo esc_attr( $settings['spinner_color'] ); ?>">
						<div class="nau-color-panel" data-target="spinner_color" data-default="#000000">
							<div class="nau-color-custom-input">
								<div class="nau-color-preview" style="background-color: <?php echo esc_attr( $settings['spinner_color'] ?: 'transparent' ); ?>"></div>
								<input type="text" class="nau-color-hex-input" maxlength="7" value="<?php echo esc_attr( $settings['spinner_color'] ); ?>">
								<span class="nau-color-native-wrap">
									<input type="color" class="nau-color-native" value="<?php echo esc_attr( $settings['spinner_color'] ?: '#000000' ); ?>">
								</span>
							</div>
							<button type="button" class="nau-color-clear">クリア</button>
						</div>
					</div>
				</div>

				<!-- 背景色 -->
				<div class="nau-form-group">
					<label>
						背景色
						<span class="nau-tooltip-wrapper">
							<span class="nau-tooltip-trigger" tabindex="0" role="button" aria-label="詳細を表示" aria-expanded="false">?</span>
							<span class="nau-tooltip-content" role="tooltip">ローディング画面の背景色です。</span>
						</span>
					</label>
					<div>
						<input type="hidden" id="bg_color" name="bg_color" value="<?php echo esc_attr( $settings['bg_color'] ); ?>">
						<div class="nau-color-panel" data-target="bg_color" data-default="#ffffff">
							<div class="nau-color-custom-input">
								<div class="nau-color-preview" style="background-color: <?php echo esc_attr( $settings['bg_color'] ?: 'transparent' ); ?>"></div>
								<input type="text" class="nau-color-hex-input" maxlength="7" value="<?php echo esc_attr( $settings['bg_color'] ); ?>">
								<span class="nau-color-native-wrap">
									<input type="color" class="nau-color-native" value="<?php echo esc_attr( $settings['bg_color'] ?: '#000000' ); ?>">
								</span>
							</div>
							<button type="button" class="nau-color-clear">クリア</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- その他の設定 -->
		<div class="nau-accordion-section" data-section="other">
			<button type="button" class="nau-accordion-header" aria-expanded="false" data-section="other">
				<span class="nau-accordion-title">その他の設定</span>
				<span class="nau-accordion-icon"></span>
			</button>
			<div class="nau-accordion-content" aria-hidden="true">
				<!-- 背景画像 -->
				<div class="nau-form-group">
					<label>
						背景画像（オプション）
						<span class="nau-tooltip-wrapper">
							<span class="nau-tooltip-trigger" tabindex="0" role="button" aria-label="詳細を表示" aria-expanded="false">?</span>
							<span class="nau-tooltip-content" role="tooltip">背景に画像を表示できます。画面全体に広がります。</span>
						</span>
					</label>
					<div>
						<input type="hidden" id="bg_image_id" name="bg_image_id" value="<?php echo esc_attr( $settings['bg_image_id'] ); ?>">
						<div class="nau-image-upload-area" data-target="bg_image_id">
							<?php if ( $bg_image_url ) : ?>
								<div class="nau-image-selected">
									<img src="<?php echo esc_url( $bg_image_url ); ?>" alt="">
									<div class="nau-image-buttons">
										<button type="button" class="button nau-remove-button" data-target="bg_image_id">削除</button>
									</div>
								</div>
							<?php else : ?>
								<div class="nau-image-placeholder">
									<div class="nau-image-placeholder-text">画像をアップロード、またはライブラリから選択してください。</div>
									<button type="button" class="button nau-media-button" data-target="bg_image_id">メディアライブラリ</button>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- 背景画像ぼかし -->
				<div class="nau-form-group">
					<label>
						<input type="checkbox" id="bg_image_blur" name="bg_image_blur" value="1" <?php checked( ! empty( $settings['bg_image_blur'] ) ); ?> <?php disabled( empty( $settings['bg_image_id'] ) ); ?>>
						背景画像をぼかす
						<span class="nau-tooltip-wrapper">
							<span class="nau-tooltip-trigger" tabindex="0" role="button" aria-label="詳細を表示" aria-expanded="false">?</span>
							<span class="nau-tooltip-content" role="tooltip">背景画像にぼかしをかけます。</span>
						</span>
					</label>
				</div>

				<!-- 長時間ローダーテキスト -->
				<div class="nau-form-group">
					<label>
						<input type="checkbox" id="slow_load_text_enabled" name="slow_load_text_enabled" value="1" <?php checked( ! empty( $settings['slow_load_text_enabled'] ) ); ?>>
						長時間表示時にテキストを表示
						<span class="nau-tooltip-wrapper">
							<span class="nau-tooltip-trigger" tabindex="0" role="button" aria-label="詳細を表示" aria-expanded="false">?</span>
							<span class="nau-tooltip-content" role="tooltip">ローダーが5秒以上表示された場合にメッセージを表示します。</span>
						</span>
					</label>
				</div>
				<div class="nau-subsection screw-slow-load-options" style="<?php echo empty( $settings['slow_load_text_enabled'] ) ? 'display:none;' : ''; ?>">
					<div class="nau-form-group">
						<label for="slow_load_text">
							テキスト内容
						</label>
						<div>
							<input type="text" id="slow_load_text" name="slow_load_text" value="<?php echo esc_attr( $settings['slow_load_text'] ); ?>" class="regular-text">
							<p class="description">最大60文字</p>
						</div>
					</div>
					<div class="nau-form-group">
						<label>
							テキストの色
						</label>
						<div>
							<input type="hidden" id="slow_load_text_color" name="slow_load_text_color" value="<?php echo esc_attr( $settings['slow_load_text_color'] ); ?>">
							<div class="nau-color-panel" data-target="slow_load_text_color" data-default="#000000">
								<div class="nau-color-custom-input">
									<div class="nau-color-preview" style="background-color: <?php echo esc_attr( $settings['slow_load_text_color'] ?: 'transparent' ); ?>"></div>
									<input type="text" class="nau-color-hex-input" maxlength="7" value="<?php echo esc_attr( $settings['slow_load_text_color'] ); ?>">
									<span class="nau-color-native-wrap">
										<input type="color" class="nau-color-native" value="<?php echo esc_attr( $settings['slow_load_text_color'] ?: '#000000' ); ?>">
									</span>
								</div>
								<button type="button" class="nau-color-clear">クリア</button>
							</div>
						</div>
					</div>

				</div>
			</div>
		</div>

		<div class="nau-form-actions">
			<button type="submit" class="button button-primary" <?php echo ! empty( $cp_is_running ) ? 'disabled' : ''; ?>>設定を保存</button>
			<button type="button" id="screw-reset-button" class="button button-danger" <?php echo ! empty( $cp_is_running ) ? 'disabled' : ''; ?>>設定をリセット</button>
			<button type="button" id="screw-export-settings" class="button">設定をエクスポート</button>
			<button type="button" id="screw-import-settings" class="button" <?php echo ! empty( $cp_is_running ) ? 'disabled' : ''; ?>>設定をインポート</button>
			<input type="file" id="screw-import-file" accept=".json" style="display:none;">
			<button type="button" id="screw-preview-button" class="button">プレビュー</button>
		</div>
	</form>

	<div class="nau-version-info">
		Screw <a href="https://github.com/villyoshioka/Screw/releases/tag/v<?php echo esc_attr( SC_VERSION ); ?>" target="_blank" rel="noopener noreferrer">v<?php echo esc_html( SC_VERSION ); ?></a>
	</div>
</div>
