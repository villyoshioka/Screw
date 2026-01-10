/**
 * Screw ローディング画面スクリプト
 */

(function($) {
	'use strict';

	var ScrewLoader = {
		$loader: null,
		$progressbar: null,
		displayFrequency: 'every',
		siteUrl: '',
		animationType: 'wipe',
		hasShown: false,
		startTime: null,
		minDisplayTime: 2000, // 最低表示時間（ミリ秒）

		init: function() {
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

			// ロード完了時
			$(window).on('load', this.onLoad.bind(this));

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

				// デフォルト動作をキャンセル
				e.preventDefault();

				// ローディング画面を再表示
				self.showLoader();

				// ページ遷移
				setTimeout(function() {
					window.location.href = href;
				}, 100);
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
			// ローディング画面が既に表示されている場合は何もしない
			if ($('#screw-loader-wrapper').length) {
				return;
			}

			// ローディング画面をbodyに追加
			$('body').append(this.$loader.clone().removeClass('loaded'));
		}
	};

	$(document).ready(function() {
		ScrewLoader.init();
	});

})(jQuery);
