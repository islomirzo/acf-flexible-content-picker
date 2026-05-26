( function ( $ ) {
	'use strict';

	function showNotice( message, type ) {
		var $n = $( '#plugpanda-acf-fcp-license-notice' );
		$n.removeClass( 'plugpanda-acf-fcp-settings__notice--success plugpanda-acf-fcp-settings__notice--error' )
			.addClass( 'plugpanda-acf-fcp-settings__notice--' + type )
			.text( message )
			.show();
	}

	function setProState( maskedKey ) {
		// Plan badge + text
		$( '.plugpanda-acf-fcp-settings__badge' )
			.removeClass( 'plugpanda-acf-fcp-settings__badge--free' )
			.addClass( 'plugpanda-acf-fcp-settings__badge--pro' )
			.text( 'PRO' );
		$( '.plugpanda-acf-fcp-settings__plan p' )
			.text( 'You have an active Pro license. All features are unlocked.' );

		// Input: show masked key, make readonly
		$( '#plugpanda-acf-fcp-license-key' ).val( maskedKey ).prop( 'readonly', true );

		// Swap activate → deactivate button
		$( '#plugpanda-acf-fcp-activate' )
			.attr( 'id', 'plugpanda-acf-fcp-deactivate' )
			.removeClass( 'button-primary' )
			.addClass( 'plugpanda-acf-fcp-settings__btn-deactivate' )
			.prop( 'disabled', false )
			.text( 'Deactivate' );

		// Hide purchase link
		$( '.plugpanda-acf-fcp-settings__card-body .description' ).hide();
	}

	function setFreeState() {
		// Plan badge + text
		$( '.plugpanda-acf-fcp-settings__badge' )
			.removeClass( 'plugpanda-acf-fcp-settings__badge--pro' )
			.addClass( 'plugpanda-acf-fcp-settings__badge--free' )
			.text( 'FREE' );
		$( '.plugpanda-acf-fcp-settings__plan p' )
			.text( 'You are on the free plan (up to ' + AcfFcpSettings.freeLimit + ' layouts per field). Upgrade to Pro for unlimited layouts.' );

		// Input: clear, make editable
		$( '#plugpanda-acf-fcp-license-key' ).val( '' ).prop( 'readonly', false );

		// Swap deactivate → activate button
		$( '#plugpanda-acf-fcp-deactivate' )
			.attr( 'id', 'plugpanda-acf-fcp-activate' )
			.removeClass( 'plugpanda-acf-fcp-settings__btn-deactivate' )
			.addClass( 'button-primary' )
			.prop( 'disabled', false )
			.text( 'Activate License' );

		// Show purchase link
		$( '.plugpanda-acf-fcp-settings__card-body .description' ).show();
	}

	/* Activate */
	$( document ).on( 'click', '#plugpanda-acf-fcp-activate', function () {
		var $btn = $( this );
		var key  = $( '#plugpanda-acf-fcp-license-key' ).val().trim();

		$btn.prop( 'disabled', true ).text( AcfFcpSettings.i18n.activating );

		$.post( AcfFcpSettings.ajaxUrl, {
			action:      'plugpanda_acf_fcp_activate_license',
			nonce:       AcfFcpSettings.nonce,
			license_key: key,
		} )
		.done( function ( res ) {
			if ( res.success ) {
				setProState( res.data.masked_key );
				showNotice( res.data.message, 'success' );
			} else {
				showNotice( res.data.message, 'error' );
				$btn.prop( 'disabled', false ).text( 'Activate License' );
			}
		} )
		.fail( function () {
			showNotice( 'Request failed. Please try again.', 'error' );
			$btn.prop( 'disabled', false ).text( 'Activate License' );
		} );
	} );

	/* Deactivate */
	$( document ).on( 'click', '#plugpanda-acf-fcp-deactivate', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true ).text( AcfFcpSettings.i18n.deactivating );

		$.post( AcfFcpSettings.ajaxUrl, {
			action: 'plugpanda_acf_fcp_deactivate_license',
			nonce:  AcfFcpSettings.nonce,
		} )
		.done( function ( res ) {
			if ( res.success ) {
				setFreeState();
				showNotice( res.data.message, 'success' );
			} else {
				$btn.prop( 'disabled', false ).text( 'Deactivate' );
			}
		} )
		.fail( function () {
			showNotice( 'Request failed. Please try again.', 'error' );
			$btn.prop( 'disabled', false ).text( 'Deactivate' );
		} );
	} );

	/* Check for updates */
	function showUpdateNotice( message, type ) {
		$( '#plugpanda-acf-fcp-update-notice' )
			.removeClass( 'plugpanda-acf-fcp-settings__notice--success plugpanda-acf-fcp-settings__notice--info plugpanda-acf-fcp-settings__notice--error' )
			.addClass( 'plugpanda-acf-fcp-settings__notice--' + type )
			.text( message )
			.show();
	}

	$( document ).on( 'click', '#plugpanda-acf-fcp-check-update', function () {
		var $btn = $( this );
		var orig = $btn.text();

		$btn.prop( 'disabled', true ).text( AcfFcpSettings.i18n.checking );

		$.post( AcfFcpSettings.ajaxUrl, {
			action: 'plugpanda_acf_fcp_check_update',
			nonce:  AcfFcpSettings.nonce,
		} )
		.done( function ( res ) {
			$btn.prop( 'disabled', false ).text( orig );

			if ( ! res.success ) {
				showUpdateNotice( ( res.data && res.data.message ) || 'Request failed.', 'error' );
				return;
			}

			if ( res.data.update_available ) {
				showUpdateNotice( res.data.message, 'info' );
				$( '#plugpanda-acf-fcp-update-now' ).show();
			} else {
				showUpdateNotice( res.data.message, 'success' );
				$( '#plugpanda-acf-fcp-update-now' ).hide();
			}
		} )
		.fail( function () {
			$btn.prop( 'disabled', false ).text( orig );
			showUpdateNotice( 'Request failed. Please try again.', 'error' );
		} );
	} );

} )( jQuery );
