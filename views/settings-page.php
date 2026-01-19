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

<div class="wrap screw-admin-wrap">
	<h1>Screw 設定</h1>

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

	<form id="screw-settings-form">
		<!-- 基本設定 -->
		<div class="screw-accordion-section" data-section="basic">
			<button type="button" class="screw-accordion-header" aria-expanded="true" data-section="basic">
				<span class="screw-accordion-title">基本設定</span>
				<span class="screw-accordion-icon"></span>
			</button>
			<div class="screw-accordion-content" aria-hidden="false">
				<!-- ローディング画像 -->
				<div class="screw-form-group">
					<label>
						ローディング画像 <span class="required">*</span>
						<span class="screw-tooltip-wrapper">
							<span class="screw-tooltip-trigger">?</span>
							<span class="screw-tooltip-content">ページ読み込み中に表示するアニメーション画像やロゴを設定します。GIFアニメーションも使用できます。</span>
						</span>
					</label>
					<div>
						<input type="hidden" id="loading_image_id" name="loading_image_id" value="<?php echo esc_attr( $settings['loading_image_id'] ); ?>">
						<div class="screw-image-upload-area" data-target="loading_image_id">
							<?php if ( $loading_image_url ) : ?>
								<div class="screw-image-selected">
									<img src="<?php echo esc_url( $loading_image_url ); ?>" alt="">
									<div class="screw-image-buttons">
										<button type="button" class="button screw-remove-button" data-target="loading_image_id">削除</button>
									</div>
								</div>
							<?php else : ?>
								<div class="screw-image-placeholder">
									<div class="screw-image-placeholder-text">画像をアップロード、またはライブラリから選択してください。</div>
									<button type="button" class="button screw-media-button" data-target="loading_image_id">メディアライブラリ</button>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- 画像横幅 -->
				<div class="screw-form-group">
					<label for="loading_image_width">
						画像横幅（px）
						<span class="screw-tooltip-wrapper">
							<span class="screw-tooltip-trigger">?</span>
							<span class="screw-tooltip-content">ローディング画像の表示サイズを指定します。縦横比は自動的に維持されます。</span>
						</span>
					</label>
					<div>
						<input type="number" id="loading_image_width" name="loading_image_width" value="<?php echo esc_attr( $settings['loading_image_width'] ); ?>" min="1" step="1" class="small-text">
					</div>
				</div>

				<!-- アニメーションタイプ -->
				<div class="screw-form-group">
					<label>
						アニメーションタイプ
						<span class="screw-tooltip-wrapper">
							<span class="screw-tooltip-trigger">?</span>
							<span class="screw-tooltip-content">ワイプ: 画像が徐々に現れるアニメーション
プログレスバー: 読み込み進捗を表示</span>
						</span>
					</label>
					<div>
						<label>
							<input type="radio" name="animation_type" value="wipe" <?php checked( $settings['animation_type'], 'wipe' ); ?>>
							ワイプ
						</label>
						<br>
						<label>
							<input type="radio" name="animation_type" value="progressbar" <?php checked( $settings['animation_type'], 'progressbar' ); ?>>
							プログレスバー
						</label>
					</div>
				</div>

				<!-- ワイプ方向 -->
				<div class="screw-form-group screw-wipe-option" style="<?php echo esc_attr( 'wipe' !== $settings['animation_type'] ? 'display:none;' : '' ); ?>">
					<label for="wipe_direction">
						ワイプ方向
						<span class="screw-tooltip-wrapper">
							<span class="screw-tooltip-trigger">?</span>
							<span class="screw-tooltip-content">画像が現れる方向を選択します。</span>
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
				<div class="screw-form-group screw-progressbar-option" style="<?php echo esc_attr( 'progressbar' !== $settings['animation_type'] ? 'display:none;' : '' ); ?>">
					<label for="progressbar_color">
						プログレスバーの色
						<span class="screw-tooltip-wrapper">
							<span class="screw-tooltip-trigger">?</span>
							<span class="screw-tooltip-content">読み込み進捗を示すバーの色を設定します。</span>
						</span>
					</label>
					<div>
						<input type="text" id="progressbar_color" name="progressbar_color" value="<?php echo esc_attr( $settings['progressbar_color'] ); ?>" class="screw-color-picker">
					</div>
				</div>

				<!-- 背景色 -->
				<div class="screw-form-group">
					<label for="bg_color">
						背景色
						<span class="screw-tooltip-wrapper">
							<span class="screw-tooltip-trigger">?</span>
							<span class="screw-tooltip-content">ローディング画面全体の背景色を設定します。</span>
						</span>
					</label>
					<div>
						<input type="text" id="bg_color" name="bg_color" value="<?php echo esc_attr( $settings['bg_color'] ); ?>" class="screw-color-picker">
					</div>
				</div>
			</div>
		</div>

		<!-- その他の設定 -->
		<div class="screw-accordion-section" data-section="other">
			<button type="button" class="screw-accordion-header" aria-expanded="false" data-section="other">
				<span class="screw-accordion-title">その他の設定</span>
				<span class="screw-accordion-icon"></span>
			</button>
			<div class="screw-accordion-content" aria-hidden="true">
				<!-- 背景画像 -->
				<div class="screw-form-group">
					<label>
						背景画像（オプション）
						<span class="screw-tooltip-wrapper">
							<span class="screw-tooltip-trigger">?</span>
							<span class="screw-tooltip-content">ローディング画面の背景に表示する画像を設定できます。画面全体に表示されます。</span>
						</span>
					</label>
					<div>
						<input type="hidden" id="bg_image_id" name="bg_image_id" value="<?php echo esc_attr( $settings['bg_image_id'] ); ?>">
						<div class="screw-image-upload-area" data-target="bg_image_id">
							<?php if ( $bg_image_url ) : ?>
								<div class="screw-image-selected">
									<img src="<?php echo esc_url( $bg_image_url ); ?>" alt="">
									<div class="screw-image-buttons">
										<button type="button" class="button screw-remove-button" data-target="bg_image_id">削除</button>
									</div>
								</div>
							<?php else : ?>
								<div class="screw-image-placeholder">
									<div class="screw-image-placeholder-text">画像をアップロード、またはライブラリから選択してください。</div>
									<button type="button" class="button screw-media-button" data-target="bg_image_id">メディアライブラリ</button>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- 背景画像ぼかし -->
				<div class="screw-form-group">
					<label>
						<input type="checkbox" id="bg_image_blur" name="bg_image_blur" value="1" <?php checked( ! empty( $settings['bg_image_blur'] ) ); ?> <?php disabled( empty( $settings['bg_image_id'] ) ); ?>>
						背景画像をぼかす
						<span class="screw-tooltip-wrapper">
							<span class="screw-tooltip-trigger">?</span>
							<span class="screw-tooltip-content">背景画像にぼかし効果を適用します。</span>
						</span>
					</label>
				</div>

				<!-- 表示頻度 -->
				<div class="screw-form-group">
					<label>
						表示頻度
						<span class="screw-tooltip-wrapper">
							<span class="screw-tooltip-trigger">?</span>
							<span class="screw-tooltip-content">初回のみ: サイト訪問時の最初の1回だけ表示
毎回表示: ページ移動のたびに表示</span>
						</span>
					</label>
					<div>
						<label>
							<input type="radio" name="display_frequency" value="once" <?php checked( $settings['display_frequency'], 'once' ); ?>>
							初回のみ
						</label>
						<br>
						<label>
							<input type="radio" name="display_frequency" value="every" <?php checked( $settings['display_frequency'], 'every' ); ?>>
							毎回表示
						</label>
					</div>
				</div>
			</div>
		</div>

		<div class="screw-form-actions">
			<button type="submit" class="button button-primary">設定を保存</button>
			<button type="button" id="screw-reset-button" class="button button-danger">設定をリセット</button>
			<button type="button" id="screw-export-settings" class="button">設定をエクスポート</button>
			<button type="button" id="screw-import-settings" class="button">設定をインポート</button>
			<input type="file" id="screw-import-file" accept=".json" style="display:none;">
			<button type="button" id="screw-preview-button" class="button">プレビュー</button>
		</div>
	</form>

	<div class="screw-version-info">
		Screw <a href="https://github.com/villyoshioka/Screw/releases/tag/v<?php echo esc_attr( SC_VERSION ); ?>" target="_blank" rel="noopener noreferrer">v<?php echo esc_html( SC_VERSION ); ?></a>
	</div>
</div>
