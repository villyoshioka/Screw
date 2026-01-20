/**
 * Screw ローディング画面スクリプト（Vanilla JS版）
 */

(function() {
	'use strict';

	var ScrewLoader = {
		loader: null,
		progressbar: null,
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

			this.loader = document.getElementById('screw-loader-wrapper');
			this.progressbar = document.querySelector('.screw-progressbar');

			if (!this.loader) {
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
				this.loader.remove();
				return;
			}

			// プログレスバー更新
			if (this.progressbar) {
				// JavaScriptで制御する場合はCSSアニメーションを無効化
				this.progressbar.style.animation = 'none';
				this.updateProgressbar();
			}

			// ロード完了時（既にロード済みの場合も対応）
			if (document.readyState === 'complete') {
				this.onLoad();
			} else {
				window.addEventListener('load', this.onLoad.bind(this));
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
				self.loader.classList.add('loaded');

				// セッションストレージに保存
				if (self.displayFrequency === 'once') {
					sessionStorage.setItem('screw_shown', '1');
				}

				// アニメーション終了後に削除
				setTimeout(function() {
					self.loader.remove();
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
					self.progressbar.style.width = progress + '%';
				}
			}, 50);

			// window.loadで確実に100%にする
			window.addEventListener('load', function() {
				isComplete = true;
				clearInterval(interval);
				self.progressbar.style.width = '100%';
			});
		},

		registerLinks: function() {
			var self = this;

			document.addEventListener('click', function(e) {
				// クリックされた要素からaタグを探す
				var target = e.target;
				while (target && target.tagName !== 'A') {
					target = target.parentElement;
				}

				if (!target) {
					return;
				}

				var href = target.getAttribute('href');

				// hrefがない、または#のみの場合はスキップ
				if (!href || href === '#' || href.indexOf('#') === 0) {
					return;
				}

				// 外部リンク判定
				if (!self.isInternalLink(href)) {
					return;
				}

				// target="_blank"の場合はスキップ
				if (target.getAttribute('target') === '_blank') {
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
			var existingLoaders = document.querySelectorAll('#screw-loader-wrapper');
			existingLoaders.forEach(function(loader) {
				loader.remove();
			});

			// ローディング画面の元のHTMLを保存していない場合は何もしない
			if (!this.loader) {
				return;
			}

			// ローディング画面を再表示
			var newLoader = this.loader.cloneNode(true);
			newLoader.classList.remove('loaded');
			document.body.appendChild(newLoader);

			// 新しいローダーに対してload完了イベントを設定
			var self = this;
			var startTime = Date.now();

			var loadHandler = function() {
				var elapsedTime = Date.now() - startTime;
				var remainingTime = Math.max(0, self.minDisplayTime - elapsedTime);

				setTimeout(function() {
					newLoader.classList.add('loaded');
					setTimeout(function() {
						newLoader.remove();
					}, 1200);
				}, remainingTime);
			};

			window.removeEventListener('load', loadHandler);
			window.addEventListener('load', loadHandler);

			// 既にロード済みの場合は即座に非表示
			if (document.readyState === 'complete') {
				loadHandler();
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
})();
