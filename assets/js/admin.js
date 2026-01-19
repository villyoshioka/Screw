/**
 * Screw 管理画面スクリプト
 */

(function($) {
	'use strict';

	var ScrewAdmin = {
		/**
		 * 確認ダイアログを表示
		 */
		showConfirm: function(message, onConfirm, onCancel) {
			// 既存の確認ダイアログを削除
			$('.screw-confirm-dialog').remove();

			// ダイアログHTMLを作成
			var $dialog = $('<div class="screw-confirm-dialog">' +
				'<div class="screw-confirm-overlay"></div>' +
				'<div class="screw-confirm-box">' +
				'<h3>確認</h3>' +
				'<p>' + message + '</p>' +
				'<div class="screw-confirm-buttons">' +
				'<button class="button button-primary screw-confirm-yes">はい</button>' +
				'<button class="button screw-confirm-no">いいえ</button>' +
				'</div>' +
				'</div>' +
				'</div>');

			// ダイアログを追加
			$('body').append($dialog);

			// はいボタンのイベント
			$dialog.find('.screw-confirm-yes').on('click', function() {
				$dialog.remove();
				if (typeof onConfirm === 'function') {
					onConfirm();
				}
			});

			// いいえボタンのイベント
			$dialog.find('.screw-confirm-no, .screw-confirm-overlay').on('click', function() {
				$dialog.remove();
				if (typeof onCancel === 'function') {
					onCancel();
				}
			});
		},

		init: function() {
			// カラーピッカー
			$('.screw-color-picker').wpColorPicker();

			// アニメーションタイプの切り替え
			$('input[name="animation_type"]').on('change', function() {
				ScrewAdmin.toggleAnimationType();
			});
			this.toggleAnimationType();

			// メディアアップローダー（イベント委譲で動的に追加されるボタンにも対応）
			$(document).on('click', '.screw-media-button', this.openMediaUploader);
			$(document).on('click', '.screw-remove-button', this.removeImage);

			// ドラッグ&ドロップ
			this.initDragDrop();

			// フォーム送信
			$('#screw-settings-form').on('submit', function(e) {
				ScrewAdmin.saveSettings(e);
			});

			// リセットボタン
			$('#screw-reset-button').on('click', function(e) {
				ScrewAdmin.resetSettings(e);
			});

			// プレビューボタン
			$('#screw-preview-button').on('click', function(e) {
				ScrewAdmin.preview(e);
			});

			// エクスポートボタン
			$('#screw-export-settings').on('click', function(e) {
				ScrewAdmin.exportSettings(e);
			});

			// インポートボタン
			$('#screw-import-settings').on('click', function() {
				$('#screw-import-file').click();
			});

			// インポートファイル選択
			$('#screw-import-file').on('change', function(e) {
				ScrewAdmin.importSettings(e);
			});

			// アコーディオン
			this.initAccordion();

			// ツールチップ
			this.initTooltip();

			// 背景画像ぼかしチェックボックスの制御
			this.updateBgBlurState();
		},

		initAccordion: function() {
			var accordions = document.querySelectorAll('.screw-accordion-section');

			accordions.forEach(function(accordion) {
				var header = accordion.querySelector('.screw-accordion-header');
				var content = accordion.querySelector('.screw-accordion-content');
				var sectionId = accordion.dataset.section;

				if (!header || !content || !sectionId) return;

				// LocalStorageから状態を取得
				var savedState = ScrewAdmin.getAccordionState(sectionId);
				var isExpanded = savedState !== null ? savedState : ScrewAdmin.getDefaultState(sectionId);

				// 初期状態を設定（アニメーションなし）
				ScrewAdmin.setAccordionState(header, content, sectionId, isExpanded, true);

				// クリックイベント
				header.addEventListener('click', function() {
					var currentState = header.getAttribute('aria-expanded') === 'true';
					var newState = !currentState;

					ScrewAdmin.setAccordionState(header, content, sectionId, newState, false);
				});

				// キーボード操作（Enter/Space）
				header.addEventListener('keydown', function(e) {
					if (e.key === 'Enter' || e.key === ' ') {
						e.preventDefault();
						header.click();
					}
				});
			});
		},

		setAccordionState: function(header, content, sectionId, isExpanded, noTransition) {
			var $header = $(header);
			var $content = $(content);

			$header.attr('aria-expanded', isExpanded);
			$content.attr('aria-hidden', !isExpanded);

			if (noTransition) {
				// 初期表示時: aria-hidden属性のみ設定（CSSで制御）
				// display の直接操作は行わない
			} else {
				// ユーザー操作時: jQueryのslideアニメーション
				if (isExpanded) {
					$content.slideDown(200);
				} else {
					$content.slideUp(200);
				}
				// LocalStorageへの保存はフォーム保存時のみ行う
			}
		},

		getDefaultState: function(sectionId) {
			var defaultExpanded = ['basic'];
			return defaultExpanded.includes(sectionId);
		},

		getAccordionState: function(sectionId) {
			try {
				var states = localStorage.getItem('screw_accordion_states');
				if (!states) return null;

				var parsed = JSON.parse(states);
				return parsed[sectionId] !== undefined ? parsed[sectionId] : null;
			} catch (e) {
				console.error('LocalStorage読み込みエラー:', e);
				return null;
			}
		},

		saveAccordionState: function(sectionId, isExpanded) {
			try {
				var states = {};
				var existing = localStorage.getItem('screw_accordion_states');

				if (existing) {
					states = JSON.parse(existing);
				}

				states[sectionId] = isExpanded;
				localStorage.setItem('screw_accordion_states', JSON.stringify(states));
			} catch (e) {
				console.error('LocalStorage保存エラー:', e);
			}
		},

		saveAllAccordionStates: function() {
			try {
				var states = {};
				$('.screw-accordion-header').each(function() {
					var sectionId = $(this).closest('.screw-accordion-section').data('section') || $(this).data('section');
					var isExpanded = $(this).attr('aria-expanded') === 'true';
					states[sectionId] = isExpanded;
				});
				localStorage.setItem('screw_accordion_states', JSON.stringify(states));
			} catch (e) {
				console.error('LocalStorage一括保存エラー:', e);
			}
		},

		initTooltip: function() {
			// ツールチップトリガーのクリック
			$(document).on('click', '.screw-tooltip-trigger', function(e) {
				e.preventDefault();
				e.stopPropagation();

				var $wrapper = $(this).closest('.screw-tooltip-wrapper');
				var isShown = $wrapper.hasClass('show');

				// 他のツールチップを閉じる
				$('.screw-tooltip-wrapper').removeClass('show');

				// 現在のツールチップをトグル
				if (!isShown) {
					$wrapper.addClass('show');
				}
			});

			// ドキュメントクリックでツールチップを閉じる
			$(document).on('click', function(e) {
				if (!$(e.target).closest('.screw-tooltip-wrapper').length) {
					$('.screw-tooltip-wrapper').removeClass('show');
				}
			});
		},

		toggleAnimationType: function() {
			var type = $('input[name="animation_type"]:checked').val();

			if (type === 'wipe') {
				$('.screw-wipe-option').show();
				$('.screw-progressbar-option').hide();
			} else {
				$('.screw-wipe-option').hide();
				$('.screw-progressbar-option').show();
			}
		},

		updateBgBlurState: function() {
			var hasBgImage = $('#bg_image_id').val() !== '' && $('#bg_image_id').val() !== '0';
			$('#bg_image_blur').prop('disabled', !hasBgImage);
			if (!hasBgImage) {
				$('#bg_image_blur').prop('checked', false);
			}
		},

		initDragDrop: function() {
			$('.screw-image-upload-area').each(function() {
				var $uploadArea = $(this);
				var uploadArea = $uploadArea[0];

				// ドラッグオーバー
				uploadArea.addEventListener('dragover', function(e) {
					e.preventDefault();
					e.stopPropagation();
					$uploadArea.addClass('screw-drag-over');
				});

				// ドラッグリーブ
				uploadArea.addEventListener('dragleave', function(e) {
					e.preventDefault();
					e.stopPropagation();
					$uploadArea.removeClass('screw-drag-over');
				});

				// ドロップ
				uploadArea.addEventListener('drop', function(e) {
					e.preventDefault();
					e.stopPropagation();
					$uploadArea.removeClass('screw-drag-over');

					var files = e.dataTransfer.files;
					if (files.length === 0) return;

					var file = files[0];

					// 画像ファイルのみ許可
					if (!file.type.match('image.*')) {
						alert('画像ファイルのみアップロード可能です。');
						return;
					}

					// ファイルサイズ制限 (10MB)
					if (file.size > 10 * 1024 * 1024) {
						alert('ファイルサイズは10MB以下にしてください。');
						return;
					}

					// ローディング表示
					$uploadArea.addClass('screw-uploading');

					// WordPressメディアライブラリにアップロード
					var formData = new FormData();
					formData.append('action', 'sc_upload_image');
					formData.append('file', file);
					formData.append('nonce', screwAdmin.nonce);

					$.ajax({
						url: screwAdmin.ajaxUrl,
						type: 'POST',
						data: formData,
						processData: false,
						contentType: false,
						success: function(response) {
							$uploadArea.removeClass('screw-uploading');

							if (response.success && response.data && response.data.id) {
								var targetId = $uploadArea.data('target');
								var $input = $('#' + targetId);
								var imageUrl = response.data.url;

								$input.val(response.data.id);

								// 画像選択済みHTMLに置き換え
								var html = '<div class="screw-image-selected">' +
									'<img src="' + imageUrl + '" alt="">' +
									'<div class="screw-image-buttons">' +
									'<button type="button" class="button screw-remove-button" data-target="' + targetId + '">削除</button>' +
									'</div>' +
									'</div>';

								$uploadArea.html(html);

								// 背景画像の場合、ぼかしチェックボックスを有効化
								if (targetId === 'bg_image_id') {
									$('#bg_image_blur').prop('disabled', false);
								}
							} else {
								alert('アップロードに失敗しました。');
							}
						},
						error: function() {
							$uploadArea.removeClass('screw-uploading');
							alert('アップロードエラーが発生しました。');
						}
					});
				});
			});
		},

		openMediaUploader: function(e) {
			e.preventDefault();

			var $button = $(this);
			var targetId = $button.data('target');
			var $input = $('#' + targetId);
			var $uploadArea = $('.screw-image-upload-area[data-target="' + targetId + '"]');

			var mediaUploader = wp.media({
				title: '画像を選択',
				button: {
					text: '選択'
				},
				multiple: false
			});

			mediaUploader.on('select', function() {
				var attachment = mediaUploader.state().get('selection').first().toJSON();

				$input.val(attachment.id);

				// 画像選択済みHTMLに置き換え
				var html = '<div class="screw-image-selected">' +
					'<img src="' + attachment.url + '" alt="">' +
					'<div class="screw-image-buttons">' +
					'<button type="button" class="button screw-remove-button" data-target="' + targetId + '">削除</button>' +
					'</div>' +
					'</div>';

				$uploadArea.html(html);

				// 背景画像の場合、ぼかしチェックボックスを有効化
				if (targetId === 'bg_image_id') {
					$('#bg_image_blur').prop('disabled', false);
				}
			});

			mediaUploader.open();
		},

		removeImage: function(e) {
			e.preventDefault();

			var $button = $(this);
			var targetId = $button.data('target');
			var $input = $('#' + targetId);
			var $uploadArea = $('.screw-image-upload-area[data-target="' + targetId + '"]');

			$input.val('');

			// プレースホルダーHTMLに戻す
			var html = '<div class="screw-image-placeholder">' +
				'<div class="screw-image-placeholder-text">画像をドラッグ＆ドロップ、アップロード、またはライブラリから選択してください。</div>' +
				'<button type="button" class="button screw-media-button" data-target="' + targetId + '">メディアライブラリ</button>' +
				'</div>';

			$uploadArea.html(html);

			// 背景画像の場合、ぼかしチェックボックスを無効化
			if (targetId === 'bg_image_id') {
				$('#bg_image_blur').prop('disabled', true).prop('checked', false);
			}
		},

		saveSettings: function(e) {
			e.preventDefault();

			var $form = $(e.target);
			var formData = $form.serializeArray();
			var settings = {};

			// デフォルト値を設定（ラジオボタン等が未送信の場合に備える）
			settings['animation_type'] = 'wipe';
			settings['wipe_direction'] = 'bottom-top';
			settings['display_frequency'] = 'every';

			$.each(formData, function(index, field) {
				settings[field.name] = field.value;
			});

			$.ajax({
				url: screwAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'sc_save_settings',
					nonce: screwAdmin.nonce,
					settings: settings
				},
				success: function(response) {
					if (response.success) {
						// アコーディオンの状態をLocalStorageに保存
						ScrewAdmin.saveAllAccordionStates();
						ScrewAdmin.showMessage(response.data.message, 'success');
					} else {
						ScrewAdmin.showMessage(response.data.message, 'error');
					}
				},
				error: function() {
					ScrewAdmin.showMessage('通信エラーが発生しました。', 'error');
				}
			});
		},

		resetSettings: function(e) {
			e.preventDefault();

			ScrewAdmin.showConfirm('設定をリセットしてもよろしいですか？', function() {
				// はいの場合
				$.ajax({
					url: screwAdmin.ajaxUrl,
					type: 'POST',
					data: {
						action: 'sc_reset_settings',
						nonce: screwAdmin.nonce
					},
					success: function(response) {
						if (response.success) {
							// リセット時にアコーディオンの状態もリセット
							localStorage.removeItem('screw_accordion_states');
							ScrewAdmin.showMessage(response.data.message, 'success');
							setTimeout(function() {
								location.reload();
							}, 1000);
						} else {
							ScrewAdmin.showMessage(response.data.message, 'error');
						}
					},
					error: function() {
						ScrewAdmin.showMessage('通信エラーが発生しました。', 'error');
					}
				});
			});
		},

		preview: function(e) {
			e.preventDefault();

			// 現在の設定値を取得
			var settings = {};
			var formData = $('#screw-settings-form').serializeArray();

			$.each(formData, function(index, field) {
				settings[field.name] = field.value;
			});

			// チェックボックスの状態を明示的に取得
			settings['bg_image_blur'] = $('#bg_image_blur').is(':checked') ? '1' : '0';

			// 設定値をbase64エンコード
			var settingsJson = JSON.stringify(settings);
			var settingsBase64 = btoa(unescape(encodeURIComponent(settingsJson)));

			// プレビューURLを生成
			var previewUrl = window.location.origin + '/?screw_preview=1&nonce=' +
				encodeURIComponent(screwAdmin.previewNonce) +
				'&settings=' + encodeURIComponent(settingsBase64);

			// 同じタブを再利用（既に開いていれば更新、なければ新規タブ）
			window.open(previewUrl, 'screw_preview');
		},

		exportSettings: function(e) {
			e.preventDefault();

			$.ajax({
				url: screwAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'sc_export_settings',
					nonce: screwAdmin.nonce
				},
				success: function(response) {
					if (response.success) {
						var blob = new Blob([response.data.data], { type: 'application/json' });
						var url = URL.createObjectURL(blob);
						var a = document.createElement('a');
						a.href = url;
						a.download = 'screw-settings.json';
						document.body.appendChild(a);
						a.click();
						document.body.removeChild(a);
						URL.revokeObjectURL(url);
					} else {
						alert(response.data.message);
					}
				},
				error: function() {
					alert('エラーが発生しました。');
				}
			});
		},

		importSettings: function(e) {
			var file = e.target.files[0];
			if (!file) {
				return;
			}

			var reader = new FileReader();
			reader.onload = function(event) {
				var data = event.target.result;

				if (!confirm('設定をインポートしますか？現在の設定は上書きされます。')) {
					return;
				}

				$.ajax({
					url: screwAdmin.ajaxUrl,
					type: 'POST',
					data: {
						action: 'sc_import_settings',
						nonce: screwAdmin.nonce,
						data: data
					},
					success: function(response) {
						if (response.success) {
							alert(response.data.message);
							location.reload();
						} else {
							alert(response.data.message);
						}
					},
					error: function() {
						alert('エラーが発生しました。');
					}
				});
			};
			reader.readAsText(file);

			// ファイル選択をリセット
			$(e.target).val('');
		},

		showMessage: function(message, type) {
			var $container = $('#screw-message-container');
			var className = type === 'success' ? 'notice-success' : 'notice-error';

			var html = '<div class="notice ' + className + ' is-dismissible"><p>' + message + '</p></div>';

			$container.html(html);

			// 3秒後に自動的に消す
			setTimeout(function() {
				$container.find('.notice').fadeOut(function() {
					$(this).remove();
				});
			}, 3000);
		}
	};

	$(document).ready(function() {
		ScrewAdmin.init();
	});

})(jQuery);
