/**
 * Grabs the images from the content.
 *
 * @param $ jQuery.
 *
 * @return void
 *
 * @package Publish Kintone Data
 */

(function ( $ ) {
	console.log( 'hoge' );

	const app = new Vue(
		{
			el: '#block-relate-kintone-and-wp',
			data: {
				settingData: {
					domain: '',
					apps: [{
						appid: '',
						token: '',
						postType: '',
						postTitle: '',
						terms: [],
						customFiels: {},
					}]
				},
				kintoneFields: []
			},
			created: function () {

				const url = importKintoneVars.endpoint;
				const importKintoneHttpClient = axios.create(
					{
						headers: {
							'X-WP-Nonce': importKintoneVars.nonce
						}
					}
				);

				importKintoneHttpClient.get(
					url + 'import-kintone/v1/setting-kintone-data'
				).then(
					function ( response ) {
						console.log( response );
					}
				).catch(
					function ( error ) {
						console.log( '記事が取得できません。' );
					}
				);

			},
			methods: {
				get_kintone_fields: function ( index ) {

					const _this = this;

					const url = importKintoneVars.endpoint;

					const getKintoneFields = axios.create(
						{
							headers: {
								'X-WP-Nonce': importKintoneVars.nonce
							}
						}
					);

					getKintoneFields.get(
						url + 'import-kintone/v1/kintone/form/1p7cm.cybozu.com/LqLny9q9hzAngTjEzVQ97mFHBZBQStaISN4ObMKb/216',
					).then(
						function ( response ) {
							_this.kintoneFields = response.data.properties;
						}
					).catch(
						function ( error ) {
							console.log( error );
						}
					);

				},
				addRow: function ( index ) {
					console.log( index );
					this.settingData.splice( index, 0, {} );
				},
				deleteRow: function ( index ) {
					this.settingData.splice( index, 1 );
				}

			}

		}
	);

})( jQuery );
