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
			$('.nau-confirm-dialog').remove();

			// ダイアログHTMLを作成（DOM APIでXSS対策）
			var $dialog = $('<div>').addClass('nau-confirm-dialog');
			var $overlay = $('<div>').addClass('nau-confirm-overlay');
			var $box = $('<div>').addClass('nau-confirm-box');
			var $title = $('<h3>').text('確認');
			var $message = $('<p>').text(message);
			var $buttons = $('<div>').addClass('nau-confirm-buttons');
			var $yesBtn = $('<button>').addClass('button button-primary nau-confirm-yes').text('はい');
			var $noBtn = $('<button>').addClass('button nau-confirm-no').text('いいえ');

			$buttons.append($yesBtn).append($noBtn);
			$box.append($title).append($message).append($buttons);
			$dialog.append($overlay).append($box);

			$('body').append($dialog);

			$dialog.find('.nau-confirm-yes').on('click', function() {
				$dialog.remove();
				if (typeof onConfirm === 'function') {
					onConfirm();
				}
			});

			$dialog.find('.nau-confirm-no, .nau-confirm-overlay').on('click', function() {
				$dialog.remove();
				if (typeof onCancel === 'function') {
					onCancel();
				}
			});
		},

		init: function() {
			this.initColorPanels();

			$('#animation_type').on('change', function() {
				ScrewAdmin.toggleAnimationType();
			});
			this.toggleAnimationType();

			$('#slow_load_text_enabled').on('change', function() {
				$('.screw-slow-load-options').toggle($(this).is(':checked'));
			});

			$(document).on('click', '.nau-media-button', this.openMediaUploader);
			$(document).on('click', '.nau-remove-button', this.removeImage);
			this.initDragDrop();

			$('#screw-settings-form').on('keydown', 'input:not([type="submit"]):not([type="button"]), select', function(e) {
				if (e.key === 'Enter') {
					e.preventDefault();
				}
			});

			$('#screw-settings-form').on('submit', function(e) {
				ScrewAdmin.saveSettings(e);
			});
			$('#screw-reset-button').on('click', function(e) {
				ScrewAdmin.resetSettings(e);
			});
			$('#screw-preview-button').on('click', function(e) {
				ScrewAdmin.preview(e);
			});
			$('#screw-export-settings').on('click', function(e) {
				ScrewAdmin.exportSettings(e);
			});
			$('#screw-import-settings').on('click', function() {
				$('#screw-import-file').click();
			});
			$('#screw-import-file').on('change', function(e) {
				ScrewAdmin.importSettings(e);
			});

			this.initAccordion();
			this.initTooltip();
			this.updateBgBlurState();
		},

		initAccordion: function() {
			var accordions = document.querySelectorAll('.nau-accordion-section');

			accordions.forEach(function(accordion) {
				var header = accordion.querySelector('.nau-accordion-header');
				var content = accordion.querySelector('.nau-accordion-content');
				var sectionId = accordion.dataset.section;

				if (!header || !content || !sectionId) return;

				var savedState = ScrewAdmin.getAccordionState(sectionId);
				var isExpanded = savedState !== null ? savedState : ScrewAdmin.getDefaultState(sectionId);

				content.classList.add('nau-no-transition');
				ScrewAdmin.setAccordionState(header, content, sectionId, isExpanded, true);

				requestAnimationFrame(function() {
					content.classList.remove('nau-no-transition');
				});

				header.addEventListener('click', function() {
					var currentState = header.getAttribute('aria-expanded') === 'true';
					var newState = !currentState;

					ScrewAdmin.setAccordionState(header, content, sectionId, newState, false);
				});

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
				if (isExpanded) {
					$content.show();
				} else {
					$content.hide();
				}
			} else {
				if (isExpanded) {
					$content.slideDown(120);
				} else {
					$content.slideUp(120);
				}
				ScrewAdmin.saveAccordionState(sectionId, isExpanded);
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
				// noop
			}
		},

		saveAllAccordionStates: function() {
			try {
				var states = {};
				$('.nau-accordion-header').each(function() {
					var sectionId = $(this).closest('.nau-accordion-section').data('section') || $(this).data('section');
					var isExpanded = $(this).attr('aria-expanded') === 'true';
					states[sectionId] = isExpanded;
				});
				localStorage.setItem('screw_accordion_states', JSON.stringify(states));
			} catch (e) {
				// noop
			}
		},

		initTooltip: function() {
			$(document).off('click.screwTooltip keydown.screwTooltip');
			$(document).off('click.screwTooltipOutside');

			$(document).on('click.screwTooltip', '.nau-tooltip-trigger', function(e) {
				e.preventDefault();
				e.stopPropagation();

				var $trigger = $(this);
				var $wrapper = $trigger.closest('.nau-tooltip-wrapper');
				var $tooltip = $wrapper.find('.nau-tooltip-content');
				var isActive = $trigger.hasClass('active');

				$('.nau-tooltip-trigger').removeClass('active');
				$('.nau-tooltip-wrapper').removeClass('show');
				$('.nau-tooltip-trigger').attr('aria-expanded', 'false');

				if (!isActive) {
					$trigger.addClass('active');
					$wrapper.addClass('show');
					$trigger.attr('aria-expanded', 'true');
				}
			});

			$(document).on('keydown.screwTooltip', '.nau-tooltip-trigger', function(e) {
				var $trigger = $(this);
				var $wrapper = $trigger.closest('.nau-tooltip-wrapper');
				var $tooltip = $wrapper.find('.nau-tooltip-content');

				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					$trigger.trigger('click');
				} else if (e.key === 'Escape') {
					e.preventDefault();
					$trigger.removeClass('active');
					$wrapper.removeClass('show');
					$trigger.attr('aria-expanded', 'false');
				}
			});

			$(document).on('click.screwTooltipOutside', function(e) {
				if (!$(e.target).closest('.nau-tooltip-wrapper').length) {
					$('.nau-tooltip-trigger').removeClass('active');
					$('.nau-tooltip-wrapper').removeClass('show');
					$('.nau-tooltip-trigger').attr('aria-expanded', 'false');
				}
			});
		},

		toggleAnimationType: function() {
			var type = $('#animation_type').val();

			$('.screw-wipe-option').toggle(type === 'wipe');
			$('.screw-progressbar-option').toggle(type === 'progressbar');
			$('.screw-spinner-option').toggle(type === 'spinner');
		},

		updateBgBlurState: function() {
			var hasBgImage = $('#bg_image_id').val() !== '' && $('#bg_image_id').val() !== '0';
			$('#bg_image_blur').prop('disabled', !hasBgImage);
			if (!hasBgImage) {
				$('#bg_image_blur').prop('checked', false);
			}
			$('#bg_image_blur').closest('label').find('.nau-tooltip-trigger').toggleClass('disabled', !hasBgImage);
		},

		initDragDrop: function() {
			$('.nau-image-upload-area').each(function() {
				var $uploadArea = $(this);
				var uploadArea = $uploadArea[0];

				uploadArea.addEventListener('dragover', function(e) {
					e.preventDefault();
					e.stopPropagation();
					$uploadArea.addClass('nau-drag-over');
				});

				uploadArea.addEventListener('dragleave', function(e) {
					e.preventDefault();
					e.stopPropagation();
					$uploadArea.removeClass('nau-drag-over');
				});

				uploadArea.addEventListener('drop', function(e) {
					e.preventDefault();
					e.stopPropagation();
					$uploadArea.removeClass('nau-drag-over');

					var files = e.dataTransfer.files;
					if (files.length === 0) return;

					var file = files[0];

					if (!file.type.match('image.*')) {
						alert('画像ファイルのみアップロード可能です。');
						return;
					}

					if (file.size > 10 * 1024 * 1024) {
						alert('ファイルサイズは10MB以下にしてください。');
						return;
					}

					$uploadArea.addClass('nau-uploading');

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
							$uploadArea.removeClass('nau-uploading');

							if (response.success && response.data && response.data.id) {
								var targetId = $uploadArea.data('target');
								var $input = $('#' + targetId);
								var imageUrl = response.data.url;

								$input.val(response.data.id);

								// 画像選択済みHTMLに置き換え（DOM APIでXSS対策）
								var $selected = $('<div>').addClass('nau-image-selected');
								var $img = $('<img>').attr({src: imageUrl, alt: ''});
								var $buttons = $('<div>').addClass('nau-image-buttons');
								var $removeBtn = $('<button>')
									.attr({type: 'button', 'data-target': targetId})
									.addClass('button nau-remove-button')
									.text('削除');

								$buttons.append($removeBtn);
								$selected.append($img).append($buttons);
								$uploadArea.empty().append($selected);

								if (targetId === 'bg_image_id') {
									$('#bg_image_blur').prop('disabled', false);
									$('#bg_image_blur').closest('label').find('.nau-tooltip-trigger').removeClass('disabled');
								}
							} else {
								alert('アップロードに失敗しました。');
							}
						},
						error: function() {
							$uploadArea.removeClass('nau-uploading');
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
			var $uploadArea = $('.nau-image-upload-area[data-target="' + targetId + '"]');

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

				// 画像選択済みHTMLに置き換え（DOM APIでXSS対策）
				var $selected = $('<div>').addClass('nau-image-selected');
				var $img = $('<img>').attr({src: attachment.url, alt: ''});
				var $buttons = $('<div>').addClass('nau-image-buttons');
				var $removeBtn = $('<button>')
					.attr({type: 'button', 'data-target': targetId})
					.addClass('button nau-remove-button')
					.text('削除');

				$buttons.append($removeBtn);
				$selected.append($img).append($buttons);
				$uploadArea.empty().append($selected);

				if (targetId === 'bg_image_id') {
					$('#bg_image_blur').prop('disabled', false);
					$('#bg_image_blur').closest('label').find('.nau-tooltip-trigger').removeClass('disabled');
				}
			});

			mediaUploader.open();
		},

		removeImage: function(e) {
			e.preventDefault();

			var $button = $(this);
			var targetId = $button.data('target');
			var $input = $('#' + targetId);
			var $uploadArea = $('.nau-image-upload-area[data-target="' + targetId + '"]');

			$input.val('');

			// プレースホルダーHTMLに戻す（DOM APIでXSS対策）
			var $placeholder = $('<div>').addClass('nau-image-placeholder');
			var $text = $('<div>').addClass('nau-image-placeholder-text')
				.text('画像をドラッグ＆ドロップ、アップロード、またはライブラリから選択してください。');
			var $mediaBtn = $('<button>')
				.attr({type: 'button', 'data-target': targetId})
				.addClass('button nau-media-button')
				.text('メディアライブラリ');

			$placeholder.append($text).append($mediaBtn);
			$uploadArea.empty().append($placeholder);

			if (targetId === 'bg_image_id') {
				$('#bg_image_blur').prop('disabled', true).prop('checked', false);
				$('#bg_image_blur').closest('label').find('.nau-tooltip-trigger').addClass('disabled');
			}
		},

		saveSettings: function(e) {
			e.preventDefault();

			var $form = $(e.target);
			var formData = $form.serializeArray();
			var settings = {};

			settings['animation_type'] = 'wipe';
			settings['wipe_direction'] = 'bottom-top';

			$.each(formData, function(index, field) {
				settings[field.name] = field.value;
			});

			var slowText = settings['slow_load_text'] || '';
			if (slowText.length > 60) {
				ScrewAdmin.showMessage('長時間ローダーテキストは60文字以内で入力してください。', 'error');
				return;
			}

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
				$.ajax({
					url: screwAdmin.ajaxUrl,
					type: 'POST',
					data: {
						action: 'sc_reset_settings',
						nonce: screwAdmin.nonce
					},
					success: function(response) {
						if (response.success) {
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

			var settings = {};
			var formData = $('#screw-settings-form').serializeArray();

			$.each(formData, function(index, field) {
				settings[field.name] = field.value;
			});

			settings['bg_image_blur'] = $('#bg_image_blur').is(':checked') ? '1' : '0';

			// Ajaxで設定をサーバーに送信してtransient keyを取得（セキュリティ対策）
			$.ajax({
				url: screwAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'sc_store_preview_settings',
					nonce: screwAdmin.nonce,
					settings: settings
				},
				success: function(response) {
					if (response.success && response.data && response.data.key) {
						var previewUrl = window.location.origin + '/?screw_preview=1&key=' + encodeURIComponent(response.data.key);

						// 同じタブを再利用（既に開いていれば更新、なければ新規タブ）
						window.open(previewUrl, 'screw_preview');
					} else {
						alert('プレビューの準備に失敗しました。');
					}
				},
				error: function() {
					alert('通信エラーが発生しました。');
				}
			});
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

		if (!file.name.match(/\.json$/i)) {
			alert('JSONファイルのみインポート可能です。');
			$(e.target).val('');
			return;
		}

		if (file.size > 1 * 1024 * 1024) {
			alert('ファイルサイズは1MB以下にしてください。');
			$(e.target).val('');
			return;
		}

		var reader = new FileReader();
		reader.onload = function(event) {
			var data = event.target.result;

			try {
				JSON.parse(data);
			} catch (e) {
				alert('無効なJSONファイルです。');
				return;
			}

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

		$(e.target).val('');
	},

		initColorPanels: function() {
			$(document).on('click', '.nau-color-preview', function() {
				$(this).closest('.nau-color-custom-input').find('.nau-color-native')[0].click();
			});

			$(document).on('input', '.nau-color-native', function() {
				var $native = $(this);
				var val = $native.val();
				var $panel = $native.closest('.nau-color-panel');
				var targetId = $panel.data('target');

				$('#' + targetId).val(val);
				$panel.find('.nau-color-hex-input').val(val);
				$panel.find('.nau-color-preview').css('background-color', val);
			});

			$(document).on('input', '.nau-color-hex-input', function() {
				var $input = $(this);
				var val = $input.val();
				var $panel = $input.closest('.nau-color-panel');
				var targetId = $panel.data('target');

				if (val && val.charAt(0) !== '#') {
					val = '#' + val;
					$input.val(val);
				}

				if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
					$panel.find('.nau-color-preview').css('background-color', val);
					$panel.find('.nau-color-native').val(val);
					$('#' + targetId).val(val);
				}
			});

			$(document).on('click', '.nau-color-clear', function(e) {
				e.preventDefault();
				var $panel = $(this).closest('.nau-color-panel');
				var targetId = $panel.data('target');
				var defaultVal = $panel.data('default') || '#000000';

				$('#' + targetId).val(defaultVal);
				$panel.find('.nau-color-hex-input').val(defaultVal);
				$panel.find('.nau-color-preview').css('background-color', defaultVal);
				$panel.find('.nau-color-native').val(defaultVal);
			});
		},

		showMessage: function(message, type) {
			var $container = $('#screw-message-container');
			var className = type === 'success' ? 'notice-success' : 'notice-error';

			// DOM APIでXSS対策
			var $notice = $('<div>').addClass('notice ' + className + ' is-dismissible');
			var $p = $('<p>').text(message);
			$notice.append($p);

			$container.empty().append($notice);

			setTimeout(function() {
				$container.find('.notice').fadeOut(function() {
					$(this).remove();
				});
			}, 5000);
		}
	};

	$(document).ready(function() {
		ScrewAdmin.init();

		if (screwAdmin.cpIsRunning) {
			var cpPollInterval = setInterval(function() {
				$.ajax({
					url: screwAdmin.ajaxUrl,
					type: 'POST',
					data: { action: 'cp_is_running' },
					success: function(response) {
						if (response.success && !response.data.is_running) {
							$('.nau-form-actions button[type="submit"], #screw-reset-button, #screw-import-settings').prop('disabled', false);
							clearInterval(cpPollInterval);
						}
					}
				});
			}, 5000);
		}
	});

})(jQuery);
