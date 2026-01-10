/**
 * Screw ローディング画面スクリプト
 */

(function() {
	'use strict';

	// jQueryが読み込まれるまで待機
	function waitForjQuery(callback) {
		if (typeof window.jQuery !== 'undefined') {
			callback(window.jQuery);
		} else {
			setTimeout(function() {
				waitForjQuery(callback);
			}, 50);
		}
	}

	waitForjQuery(function($) {
		var ScrewLoader = {
			$loader: null,
			$progressbar: null,
			displayFrequency: 'every',
			siteUrl: '',
			animationType: 'wipe',
			hasShown: false,
			startTime: null,
			minDisplayTime: 2000,
			initialized: false,

			init: function() {
				// 既に初期化済みの場合はスキップ
				if (this.initialized) {
					return;
				}
				this.initialized = true;

				this.$loader = $('#screw-loader-wrapper');
				this.$progressbar = $('.screw-progressbar');

				if (!this.$loader.length) {
					return;
				}

				// 開始時刻を記録
				this.startTime = Date.now();

				this.displayFrequency = screwSettings.displayFrequency || 'every';
				this.siteUrl = screwSettings.siteUrl || '';
				this.animationType = screwSettings.animationType || 'wipe';

				// アニメーションタイプに応じて最低表示時間を設定
				this.minDisplayTime = this.animationType === 'progressbar' ? 1000 : 2000;

				// 表示頻度チェック
				if (this.displayFrequency === 'once' && sessionStorage.getItem('screw_shown') === '1') {
					this.$loader.remove();
					return;
				}

				// プログレスバー更新
				if (this.$progressbar.length) {
					// JavaScriptで制御する場合はCSSアニメーションを無効化
					this.$progressbar.css('animation', 'none');
					this.updateProgressbar();
				}

				// ロード完了時（既にロード済みの場合も対応）
				if (document.readyState === 'complete') {
					this.onLoad();
				} else {
					$(window).on('load', this.onLoad.bind(this));
				}

				// ページ遷移時のリンクイベント
				this.registerLinks();
			},

			onLoad: function() {
				var self = this;
				var elapsedTime = Date.now() - this.startTime;
				var remainingTime = Math.max(0, this.minDisplayTime - elapsedTime);

				// 最低表示時間を満たすまで待機
				setTimeout(function() {
					// ロード完了クラスを追加
					self.$loader.addClass('loaded');

					// セッションストレージに保存
					if (self.displayFrequency === 'once') {
						sessionStorage.setItem('screw_shown', '1');
					}

					// アニメーション終了後に削除
					setTimeout(function() {
						self.$loader.remove();
					}, 1200);
				}, remainingTime);
			},

			updateProgressbar: function() {
				var progress = 0;
				var self = this;
				var startTime = Date.now();
				var isComplete = false;

				var interval = setInterval(function() {
					// 完了フラグが立っていない場合のみ進捗を更新
					if (!isComplete) {
						var elapsedTime = Date.now() - startTime;
						var timeProgress = Math.min((elapsedTime / self.minDisplayTime) * 90, 90);
						progress = timeProgress;
						self.$progressbar.css('width', progress + '%');
					}
				}, 50);

				// window.loadで確実に100%にする
				$(window).on('load', function() {
					isComplete = true;
					clearInterval(interval);
					self.$progressbar.css('width', '100%');
				});
			},

			registerLinks: function() {
				var self = this;

				$(document).on('click', 'a', function(e) {
					var href = $(this).attr('href');

					// hrefがない、または#のみの場合はスキップ
					if (!href || href === '#' || href.indexOf('#') === 0) {
						return;
					}

					// 外部リンク判定
					if (!self.isInternalLink(href)) {
						return;
					}

					// target="_blank"の場合はスキップ
					if ($(this).attr('target') === '_blank') {
						return;
					}

					// WordPress管理画面へのリンクはスキップ
					if (href.indexOf('/wp-admin/') !== -1 || href.indexOf('/wp-login.php') !== -1) {
						return;
					}

					// ローディング表示が「毎回」でない場合はスキップ
					if (self.displayFrequency !== 'every') {
						return;
					}

					// デフォルト動作をキャンセルしない（ページ遷移を通常通り実行）
					// 新しいページで自動的にローディング画面が表示される
				});
			},

			isInternalLink: function(href) {
				// 絶対URLの場合
				if (href.indexOf('http') === 0) {
					return href.indexOf(this.siteUrl) === 0;
				}

				// 相対URLは内部リンクとみなす
				return true;
			},

			showLoader: function() {
				// 既存のローディング画面を全て削除
				$('#screw-loader-wrapper').remove();

				// ローディング画面の元のHTMLを保存していない場合は何もしない
				if (!this.$loader || !this.$loader.length) {
					return;
				}

				// ローディング画面を再表示
				var $newLoader = this.$loader.clone().removeClass('loaded');
				$('body').append($newLoader);

				// 新しいローダーに対してload完了イベントを設定
				var self = this;
				var startTime = Date.now();

				$(window).off('load.screwReload').on('load.screwReload', function() {
					var elapsedTime = Date.now() - startTime;
					var remainingTime = Math.max(0, self.minDisplayTime - elapsedTime);

					setTimeout(function() {
						$newLoader.addClass('loaded');
						setTimeout(function() {
							$newLoader.remove();
						}, 1200);
					}, remainingTime);
				});

				// 既にロード済みの場合は即座に非表示
				if (document.readyState === 'complete') {
					$(window).trigger('load.screwReload');
				}
			}
		};

		// DOMContentLoaded後すぐに初期化
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', function() {
				ScrewLoader.init();
			});
		} else {
			ScrewLoader.init();
		}
	});
})();
