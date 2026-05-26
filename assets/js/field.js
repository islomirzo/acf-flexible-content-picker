( function ( $ ) {
	'use strict';


	/* Show / hide the whole panel with the Section Picker toggle.
	   Only the panel slides — the dropdown's expanded state is untouched. */
	$( document ).on(
		'change',
		'.acf-field[data-name="plugpanda_acf_fcp_plugin_enabled"] input[type="checkbox"]',
		function () {
			var $panel = $( this ).closest( '.acf-fields' ).find( '.js-plugpanda-acf-fcp-plugin' );
			$( this ).is( ':checked' ) ? $panel.slideDown( 200 ) : $panel.slideUp( 200 );
		}
	);

	/* Dropdown header toggle */
	$( document ).on( 'click', '.js-plugpanda-acf-fcp-plugin__toggle', function () {
		var $header  = $( this );
		var $body    = $header.next( '.plugpanda-acf-fcp-plugin__body' );
		var expanded = $header.attr( 'aria-expanded' ) === 'true';

		$header.attr( 'aria-expanded', expanded ? 'false' : 'true' );
		expanded ? $body.slideUp( 200 ) : $body.slideDown( 200 );
	} );

	/* WP media picker */
	$( document ).on( 'click', '.plugpanda-acf-fcp-plugin__media-pick', function ( e ) {
		e.preventDefault();

		var inputName = $( this ).data( 'input-name' );
		var $input    = $( '[name="' + CSS.escape( inputName ) + '"]' );
		var $preview  = $( this ).closest( '.plugpanda-acf-fcp-plugin__field--thumb' ).find( '.plugpanda-acf-fcp-plugin__thumb-preview' );

		if ( typeof wp === 'undefined' || ! wp.media ) { return; }

		var frame = wp.media( {
			title:    'Select Image',
			multiple: false,
			library:  { type: 'image' },
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			var url = ( attachment.sizes && attachment.sizes.large )
				? attachment.sizes.large.url
				: attachment.url;

			$input.val( url ).trigger( 'change' );
			$preview.attr( 'src', url ).show();
		} );

		frame.open();
	} );

	/* Live thumbnail preview */
	$( document ).on( 'input change blur', '.plugpanda-acf-fcp-plugin__thumb-url', function () {
		var url      = $.trim( $( this ).val() );
		var $preview = $( this ).closest( '.plugpanda-acf-fcp-plugin__field--thumb' ).find( '.plugpanda-acf-fcp-plugin__thumb-preview' );

		if ( ! url ) {
			$preview.hide();
			return;
		}

		$preview.off( 'error' ).on( 'error', function () {
			$( this ).hide();
		} ).attr( 'src', url ).show();
	} );

	/* Autosave layout meta */
	var namePattern = /^plugpanda_acf_fcp_plugin_layout_meta\[([^\]]+)\]\[([^\]]+)\]\[([^\]]+)\]$/;

	function debounce( fn, delay ) {
		var timer;
		return function () {
			var ctx = this, args = arguments;
			clearTimeout( timer );
			timer = setTimeout( function () { fn.apply( ctx, args ); }, delay );
		};
	}

	$( document ).on(
		'input change',
		'.js-plugpanda-acf-fcp-plugin input, .js-plugpanda-acf-fcp-plugin textarea',
		debounce( function () {
			if ( typeof AcfFcpPluginFS === 'undefined' || ! AcfFcpPluginFS.ajaxUrl ) { return; }

			var name  = $( this ).attr( 'name' );
			var match = name && name.match( namePattern );
			if ( ! match ) { return; }

			$.post( AcfFcpPluginFS.ajaxUrl, {
				action:      'plugpanda_acf_fcp_plugin_autosave_meta',
				nonce:       AcfFcpPluginFS.nonce,
				field_key:   match[ 1 ],
				layout_name: match[ 2 ],
				field_type:  match[ 3 ],
				value:       $( this ).val(),
			} );
		}, 400 )
	);

	/* Real-time layout sync */
	var UPLOAD_LABEL = ( AcfFcpPluginFS && AcfFcpPluginFS.i18n && AcfFcpPluginFS.i18n.upload ) || 'UPLOAD';

	function esc( str ) {
		return $( '<span>' ).text( String( str ) ).html();
	}

	function getFieldKey( $panel ) {
		var key = $panel.data( 'field-key' );
		if ( key ) { return String( key ); }
		var n = $panel.find( 'input[name^="plugpanda_acf_fcp_plugin_layout_meta["]' ).first().attr( 'name' );
		if ( ! n ) { return ''; }
		var m = n.match( /^plugpanda_acf_fcp_plugin_layout_meta\[([^\]]+)\]/ );
		return m ? m[ 1 ] : '';
	}

	function getLayoutContainer( $panel ) {
		return $panel.closest( '.plugpanda-acf-fcp-plugin-wrapper' ).parent();
	}

	function buildCard( fieldKey, layoutName, layoutLabel, layoutKey ) {
		var fk = fieldKey;
		var ln = layoutName || layoutKey;
		var lb = layoutLabel || 'Layout';
		return (
			'<div class="plugpanda-acf-fcp-plugin__row" data-layout-key="' + esc( layoutKey ) + '" data-layout-name="' + esc( ln ) + '">' +
				'<div class="plugpanda-acf-fcp-plugin__row-head"><span class="plugpanda-acf-fcp-plugin__row-label">' + esc( lb ) + '</span></div>' +
				'<div class="plugpanda-acf-fcp-plugin__row-fields">' +
					'<div class="plugpanda-acf-fcp-plugin__field">' +
						'<input type="text" name="plugpanda_acf_fcp_plugin_layout_meta[' + fk + '][' + ln + '][title]" value="" placeholder="' + esc( lb ) + '" class="widefat">' +
					'</div>' +
					'<div class="plugpanda-acf-fcp-plugin__field">' +
						'<input type="text" name="plugpanda_acf_fcp_plugin_layout_meta[' + fk + '][' + ln + '][description]" value="" placeholder="Short Description" class="widefat">' +
					'</div>' +
					'<div class="plugpanda-acf-fcp-plugin__field plugpanda-acf-fcp-plugin__field--thumb">' +
						'<div class="plugpanda-acf-fcp-plugin__thumb-box"><img src="" class="plugpanda-acf-fcp-plugin__thumb-preview" alt="" style="display:none;"></div>' +
						'<div class="plugpanda-acf-fcp-plugin__thumb-controls">' +
							'<input type="text" name="plugpanda_acf_fcp_plugin_layout_meta[' + fk + '][' + ln + '][thumbnail]" value="" placeholder="URL" class="widefat plugpanda-acf-fcp-plugin__thumb-url">' +
							'<div class="plugpanda-acf-fcp-plugin__thumb-or">OR</div>' +
							'<button type="button" class="button plugpanda-acf-fcp-plugin__media-pick" data-input-name="plugpanda_acf_fcp_plugin_layout_meta[' + fk + '][' + ln + '][thumbnail]">' + esc( UPLOAD_LABEL ) + '</button>' +
						'</div>' +
					'</div>' +
				'</div>' +
			'</div>'
		);
	}

	function getLayoutLabel( $l ) {
		return $l.attr( 'data-layout-label' )
			|| $l.find( '.layout-label' ).val()
			|| $l.find( '[data-name="layout_label"] input' ).val()
			|| 'Layout';
	}

	function initCards( $panel ) {
		var fieldKey  = getFieldKey( $panel );
		if ( ! fieldKey ) { return; }
		var $cardList = $panel.find( '.acf-input' );
		var created   = false;

		getLayoutContainer( $panel ).children( '.acf-field-setting-fc_layout' ).each( function () {
			var $l    = $( this );
			var key   = String( $l.data( 'id' ) || '' );
			var lname = $l.attr( 'data-layout-name' ) || key;
			if ( ! key ) { return; }

			var $card = $cardList.find( '.plugpanda-acf-fcp-plugin__row' ).filter( function () {
				return $( this ).find( 'input[name*="[' + lname + ']["]' ).length > 0;
			} );

			if ( $card.length ) {
				$card.attr( 'data-layout-key', key ).attr( 'data-layout-name', lname );
			} else {
				var label = getLayoutLabel( $l );
				$cardList.append( buildCard( fieldKey, lname, label, key ) );
				created = true;
			}
		} );

		if ( created ) {
			$cardList.find( 'p.description' ).remove();
		}
	}

	function observeLayouts( $panel ) {
		var fieldKey = getFieldKey( $panel );
		var $container = getLayoutContainer( $panel );
		if ( ! fieldKey || ! $container.length ) { return; }

		var $cardList = $panel.find( '.acf-input' );

		var observer = new MutationObserver( function ( mutations ) {
			var added   = {};
			var removed = {};

			mutations.forEach( function ( mutation ) {
				Array.from( mutation.addedNodes ).forEach( function ( node ) {
					if ( node.nodeType !== 1 ) { return; }
					var $node = $( node );
					if ( ! $node.hasClass( 'acf-field-setting-fc_layout' ) ) { return; }
					var key = String( $node.data( 'id' ) || '' );
					if ( key ) { added[ key ] = $node; }
				} );

				Array.from( mutation.removedNodes ).forEach( function ( node ) {
					if ( node.nodeType !== 1 ) { return; }
					var $node = $( node );
					if ( ! $node.hasClass( 'acf-field-setting-fc_layout' ) ) { return; }
					var key = String( $node.data( 'id' ) || '' );
					if ( key ) { removed[ key ] = $node; }
				} );
			} );

			// True deletions — key removed but not re-added
			$.each( removed, function ( key ) {
				if ( ! added[ key ] ) {
					$cardList.find( '[data-layout-key="' + key + '"]' ).remove();
				}
			} );

			// True additions — key added but not previously present (not a reorder)
			$.each( added, function ( key, $node ) {
				if ( ! removed[ key ] && ! $cardList.find( '[data-layout-key="' + key + '"]' ).length ) {
					var lname = $node.attr( 'data-layout-name' ) || key;
					var label = getLayoutLabel( $node );
					$cardList.find( 'p.description' ).remove();
					$cardList.append( buildCard( fieldKey, lname, label, key ) );
				}
			} );

			// Re-sync card order to match current layout DOM order (handles reorders)
			var ordered  = [];
			$container.children( '.acf-field-setting-fc_layout' ).each( function () {
				var key = String( $( this ).data( 'id' ) || '' );
				if ( ! key ) { return; }
				var $card = $cardList.find( '[data-layout-key="' + key + '"]' );
				if ( $card.length ) { ordered.push( $card[ 0 ] ); }
			} );
			$.each( ordered, function ( i, el ) { $cardList.append( el ); } );
		} );

		observer.observe( $container[ 0 ], { childList: true } );
	}

	function initPanel( $panel ) {
		if ( $panel.data( 'plugpanda-acf-fcp-ready' ) ) { return; }
		$panel.data( 'plugpanda-acf-fcp-ready', true );
		initCards( $panel );
		observeLayouts( $panel );
	}

	$( function () {
		$( '.js-plugpanda-acf-fcp-plugin' ).each( function () {
			initPanel( $( this ) );
		} );

		new MutationObserver( function ( mutations ) {
			mutations.forEach( function ( mutation ) {
				Array.from( mutation.addedNodes ).forEach( function ( node ) {
					if ( node.nodeType !== 1 ) { return; }
					var $node = $( node );
					var $panels = $node.hasClass( 'js-plugpanda-acf-fcp-plugin' ) ? $node : $node.find( '.js-plugpanda-acf-fcp-plugin' );
					$panels.each( function () { initPanel( $( this ) ); } );
				} );
			} );
		} ).observe( document.body, { childList: true, subtree: true } );
	} );

	$( document ).on( 'input blur', '.layout-label', function () {
		var $l    = $( this ).closest( '.acf-field-setting-fc_layout' );
		var key   = $l.data( 'id' ) || '';
		if ( ! key ) { return; }
		var $panel = $l.siblings( '.plugpanda-acf-fcp-plugin-wrapper' ).find( '.js-plugpanda-acf-fcp-plugin' );
		if ( ! $panel.length ) { return; }
		var label = getLayoutLabel( $l ) || $( this ).val() || 'Layout';
		var $card = $panel.find( '[data-layout-key="' + key + '"]' );
		$card.find( '.plugpanda-acf-fcp-plugin__row-label' ).text( label );
		$card.find( 'input[name$="[title]"]' ).attr( 'placeholder', label );
	} );

	/* Sync toggle label with field label in real time */
	$( document ).on( 'input', 'input.field-label', function () {
		var $wrapper = $( this ).closest( '.acf-field-object' ).find( '.plugpanda-acf-fcp-plugin-wrapper' );
		if ( ! $wrapper.length ) { return; }
		var label = $( this ).val() || 'this field';
		$wrapper.find( '.acf-field[data-name="plugpanda_acf_fcp_plugin_enabled"] .acf-label label' )
			.text( 'Enable for "' + label + '"' );
	} );

	$( document ).on( 'blur', '.layout-name', function () {
		var $l    = $( this ).closest( '.acf-field-setting-fc_layout' );
		var key   = $l.data( 'id' ) || '';
		if ( ! key ) { return; }
		var $panel = $l.siblings( '.plugpanda-acf-fcp-plugin-wrapper' ).find( '.js-plugpanda-acf-fcp-plugin' );
		if ( ! $panel.length ) { return; }
		var newName = $l.attr( 'data-layout-name' ) || $( this ).val();
		if ( ! newName ) { return; }
		var $card   = $panel.find( '[data-layout-key="' + key + '"]' );
		if ( ! $card.length ) { return; }
		var oldName = $card.attr( 'data-layout-name' ) || '';
		if ( oldName === newName ) { return; }
		$card.find( 'input, button' ).each( function () {
			var $el = $( this );
			var n   = $el.attr( 'name' );
			if ( n ) { $el.attr( 'name', n.replace( '[' + oldName + '][', '[' + newName + '][' ) ); }
			var d = $el.attr( 'data-input-name' );
			if ( d ) { $el.attr( 'data-input-name', d.replace( '[' + oldName + '][', '[' + newName + '][' ) ); }
		} );
		$card.attr( 'data-layout-name', newName );
	} );

} )( jQuery );
