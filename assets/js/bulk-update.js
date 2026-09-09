/**
 * 一括更新のチャンク実行.
 *
 * 1リクエストで少しずつ進め、応答に入っている実行状態（run_id）を
 * 次のリクエストへ渡していく。途中で止めても記事は壊れないので、
 * 「再開」で同じ run_id の続きから実行できる.
 *
 * @package import-kintone
 */

( function ( $ ) {
	'use strict';

	var settings = window.kintoneToWpBulkUpdate || {};
	var i18n = settings.i18n || {};

	$( function () {
		var $panel = $( '#kintone-to-wp-bulk-update' );

		if ( ! $panel.length ) {
			return;
		}

		var $bar = $panel.find( '[data-role="bar"]' );
		var $status = $panel.find( '[data-role="status"]' );
		var $stop = $panel.find( '[data-role="stop"]' );
		var $retry = $panel.find( '[data-role="retry"]' );

		var running = false;
		var runId = '';

		/*
		 * 中止 → 再開のように実行を切り替えると、切り替え前に投げたリクエストの
		 * 応答が後から返ってくる。そのまま処理すると step() の連鎖が二重になり、
		 * 古い進捗で表示を上書きしてしまう。世代を振って、古い応答は捨てる.
		 */
		var generation = 0;

		function setProgress( response ) {
			var percent;

			if ( response.completed ) {
				percent = 100;
			} else if ( 'sweep' === response.phase ) {
				// 総数が分からない段階なので、ほぼ完了として見せる.
				percent = 95;
			} else if ( response.total ) {
				percent = Math.min( 90, Math.round( ( response.processed / response.total ) * 90 ) );
			} else {
				percent = 5;
			}

			$bar.css( 'width', percent + '%' );
		}

		function halt( message, resumable ) {
			++generation;
			running = false;
			$status.text( message );
			$stop.hide();
			$retry.prop( 'hidden', ! resumable );
		}

		function step() {
			if ( ! running ) {
				return;
			}

			var myGeneration = generation;

			$.post( settings.ajaxUrl, {
				action: 'kintone_to_wp_bulk_update_chunk',
				nonce: settings.nonce,
				run_id: runId
			} ).done( function ( response ) {
				// 中止した直後に、飛行中だったリクエストの応答が返ってくることがある。
				// run_id だけ拾って（再開に使う）、表示は触らない.
				if ( response && response.success && response.data && response.data.run_id ) {
					runId = response.data.run_id;
				}

				if ( ! running || myGeneration !== generation ) {
					return;
				}

				if ( ! response || ! response.success ) {
					var message = ( response && response.data && response.data.message ) || i18n.unknownError;

					if ( response && response.data && response.data.run_id ) {
						runId = response.data.run_id;
						halt( message, true );
					} else {
						halt( message, false );
					}
					return;
				}

				var data = response.data;
				setProgress( data );

				if ( data.completed ) {
					++generation;
					running = false;
					$status.text( data.message );
					$stop.hide();
					$retry.prop( 'hidden', true );
					return;
				}

				$status.text( 'sweep' === data.phase ? i18n.sweeping : data.message );
				step();
			} ).fail( function () {
				if ( myGeneration !== generation ) {
					return;
				}
				halt( i18n.networkError, '' !== runId );
			} );
		}

		function start() {
			++generation;
			running = true;
			$stop.show();
			$retry.prop( 'hidden', true );
			$status.text( i18n.starting );
			step();
		}

		$stop.on( 'click', function () {
			halt( i18n.stopped, true );
		} );

		$retry.on( 'click', function () {
			start();
		} );

		start();
	} );
} )( jQuery );
