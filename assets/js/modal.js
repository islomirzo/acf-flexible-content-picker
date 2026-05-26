/**
 * ACF Flexible Content Picker
 *
 * Intercepts the ACF "Add Row" button on flexible content fields and
 * replaces the default layout picker with a searchable modal.
 *
 * Depends on: AcfFcpPlugin (localised via wp_localize_script), AcfFcpPlugin.fields
 * (printed by assets.php in admin_footer), and ACF PRO >= 6.0.
 */
( function () {
	'use strict';

	if ( typeof acf === 'undefined' || typeof AcfFcpPlugin === 'undefined' ) {
		return;
	}

	const i18n = AcfFcpPlugin.i18n;

	document.addEventListener(
		'click',
		function ( e ) {
			const btn = e.target.closest(
				'[data-name="add-layout"], [data-event="add-layout"], .acf-fc-add-layout, .add-layout'
			);
			if ( ! btn ) return;

			const $fieldEl = jQuery( btn ).closest( '[data-type="flexible_content"]' );
			if ( ! $fieldEl.length ) return;

			const fieldKey  = $fieldEl.data( 'key' );
			const fieldData = getFieldData( fieldKey );
			if ( ! fieldData ) return;

			e.preventDefault();
			e.stopImmediatePropagation();

			Modal.open( fieldData, $fieldEl );
		},
		true
	);

	function getFieldData( key ) {
		const fields = AcfFcpPlugin.fields;
		if ( ! Array.isArray( fields ) ) return null;
		return fields.find( ( f ) => f.key === key ) || null;
	}

	function appendLayout( $fieldEl, layoutName ) {
		try {
			const field = acf.getField( $fieldEl );
			if ( field && typeof field.add === 'function' ) {
				field.add( { layout: layoutName } );
				return;
			}
		} catch ( err ) {}

		const $li = $fieldEl.find( '.acf-fc-popup li[data-layout="' + layoutName + '"]' );
		if ( $li.length ) {
			$li.trigger( 'click' );
		}
	}

	const Modal = ( function () {
		let overlay         = null;
		let selectedLayouts = [];
		let currentFieldEl  = null;
		let footer          = null;
		let reorderMode     = false;
		let sortable        = null;

		function open( fieldData, $fieldEl ) {
			if ( overlay ) close();

			selectedLayouts = [];
			currentFieldEl  = $fieldEl;
			footer          = null;
			reorderMode     = false;
			sortable        = null;

			overlay = build( fieldData );
			document.body.appendChild( overlay );
			overlay.querySelector( '.plugpanda-acf-fcp-plugin-modal__search' )?.focus();
		}

		function close() {
			if ( overlay ) {
				overlay.remove();
				overlay        = null;
				footer         = null;
				currentFieldEl = null;
			}
			document.removeEventListener( 'keydown', handleKeyDown );
		}

		function build( fieldData ) {
			const el    = createElement( 'div', { class: 'plugpanda-acf-fcp-plugin-overlay', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'plugpanda-acf-fcp-plugin-modal-title' } );
			const modal = createElement( 'div', { class: 'plugpanda-acf-fcp-plugin-modal' } );
			if ( AcfFcpPlugin.isWp7 ) {
				modal.classList.add( 'is-wp7' );
			}
			modal.appendChild( buildHeader( fieldData.label ) );

			const body = createElement( 'div', { class: 'plugpanda-acf-fcp-plugin-modal__body' } );
			const grid = createElement( 'div', { class: 'plugpanda-acf-fcp-plugin-layout-grid', role: 'list' } );
			body.appendChild( grid );
			modal.appendChild( body );
			footer = buildFooter();
			modal.appendChild( footer );
			el.appendChild( modal );

			renderCards( grid, fieldData.layouts, '' );

			modal.querySelector( '.plugpanda-acf-fcp-plugin-modal__search' )?.addEventListener( 'input', function () {
				renderCards( grid, fieldData.layouts, this.value );
				renumberBadges();
			} );

			el.addEventListener( 'click', function ( e ) {
				if ( e.target === el ) close();
			} );

			modal.querySelector( '.plugpanda-acf-fcp-plugin-modal__close' )?.addEventListener( 'click', close );
			document.addEventListener( 'keydown', handleKeyDown );

			return el;
		}

		function buildHeader( fieldLabel ) {
			const header = createElement( 'div', { class: 'plugpanda-acf-fcp-plugin-modal__header' } );

			const title = createElement( 'h2', { class: 'plugpanda-acf-fcp-plugin-modal__title', id: 'plugpanda-acf-fcp-plugin-modal-title' } );
			title.textContent = i18n.modal_title + ( fieldLabel ? ' — ' + fieldLabel : '' );

			const searchWrap  = createElement( 'div', { class: 'plugpanda-acf-fcp-plugin-modal__search-wrap' } );
			const search      = createElement( 'input', {
				type:         'search',
				class:        'plugpanda-acf-fcp-plugin-modal__search',
				placeholder:  i18n.search_placeholder,
				'aria-label': i18n.search_placeholder,
			} );
			const clearSearch = createElement( 'button', { type: 'button', class: 'plugpanda-acf-fcp-plugin-modal__search-clear', 'aria-label': 'Clear search' } );
			clearSearch.innerHTML = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
			clearSearch.addEventListener( 'click', function () {
				search.value = '';
				search.dispatchEvent( new Event( 'input' ) );
				search.focus();
			} );
			searchWrap.appendChild( search );
			searchWrap.appendChild( clearSearch );

			const closeBtn = createElement( 'button', { type: 'button', class: 'plugpanda-acf-fcp-plugin-modal__close', 'aria-label': i18n.close } );
			closeBtn.innerHTML = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';

			header.appendChild( title );
			header.appendChild( searchWrap );
			header.appendChild( closeBtn );

			return header;
		}

		function renderCards( grid, layouts, query ) {
			grid.innerHTML = '';

			const q        = ( query || '' ).toLowerCase().trim();
			const filtered = q
				? layouts.filter( ( l ) =>
					l.label.toLowerCase().includes( q ) ||
					l.description.toLowerCase().includes( q ) ||
					l.name.toLowerCase().includes( q )
				)
				: layouts;

			if ( filtered.length === 0 ) {
				const empty = createElement( 'p', { class: 'plugpanda-acf-fcp-plugin-modal__empty', role: 'listitem' } );
				empty.textContent = i18n.no_results;
				grid.appendChild( empty );
				return;
			}

			filtered.forEach( function ( layout ) {
				grid.appendChild( buildCard( layout, q, layouts.indexOf( layout ) ) );
			} );

			const modal = grid.closest( '.plugpanda-acf-fcp-plugin-modal' );
			const existingNotice = modal && modal.querySelector( '.plugpanda-acf-fcp-plugin-upgrade-notice' );
			if ( existingNotice ) existingNotice.remove();

			if ( ! AcfFcpPlugin.isPro ) {
				const notice = createElement( 'div', { class: 'plugpanda-acf-fcp-plugin-upgrade-notice' } );
				notice.innerHTML =
					'<span>' + i18n.pro_locked + '</span>' +
					'<a href="' + AcfFcpPlugin.settingsUrl + '" target="_blank" rel="noopener">' + i18n.upgrade + '</a>';
				const footerEl = modal && modal.querySelector( '.plugpanda-acf-fcp-plugin-modal__footer' );
				if ( footerEl ) {
					modal.insertBefore( notice, footerEl );
				}
			}
		}

		function highlight( text, query ) {
			const frag = document.createDocumentFragment();
			if ( ! query ) {
				frag.appendChild( document.createTextNode( text ) );
				return frag;
			}
			const escaped = query.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
			const parts   = text.split( new RegExp( '(' + escaped + ')', 'gi' ) );
			parts.forEach( function ( part, i ) {
				if ( ! part ) return;
				if ( i % 2 === 1 ) {
					const mark     = document.createElement( 'mark' );
					mark.className = 'plugpanda-acf-fcp-plugin-highlight';
					mark.textContent = part;
					frag.appendChild( mark );
				} else {
					frag.appendChild( document.createTextNode( part ) );
				}
			} );
			return frag;
		}

		function buildCard( layout, query, index ) {
			const card = createElement( 'button', {
				type:           'button',
				class:          'plugpanda-acf-fcp-plugin-card',
				'data-layout':  layout.name,
				'data-index':   index,
				'aria-pressed': 'false',
				role:           'listitem',
			} );

			const badge = createElement( 'span', { class: 'plugpanda-acf-fcp-plugin-card__badge', 'aria-hidden': 'true' } );
			card.appendChild( badge );

			if ( layout.thumbnail ) {
				const img = createElement( 'img', { src: layout.thumbnail, alt: '', class: 'plugpanda-acf-fcp-plugin-card__thumbnail', loading: 'lazy' } );
				card.appendChild( img );
			} else {
				const placeholder = createElement( 'div', { class: 'plugpanda-acf-fcp-plugin-card__thumbnail--placeholder' } );
				placeholder.innerHTML = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="3" width="18" height="18" rx="2" stroke-width="1.5" stroke="currentColor" fill="none"/><path d="M3 9h18M9 21V9" stroke="currentColor" stroke-width="1.5"/></svg>';
				card.appendChild( placeholder );
			}

			const body  = createElement( 'div', { class: 'plugpanda-acf-fcp-plugin-card__body' } );
			const label = createElement( 'span', { class: 'plugpanda-acf-fcp-plugin-card__label' } );
			label.appendChild( highlight( layout.label, query ) );
			body.appendChild( label );

			const desc = createElement( 'p', { class: 'plugpanda-acf-fcp-plugin-card__description' } );
			if ( layout.description ) {
				desc.appendChild( highlight( layout.description, query ) );
			} else {
				desc.textContent = 'No description';
				desc.classList.add( 'plugpanda-acf-fcp-plugin-card__description--empty' );
			}
			body.appendChild( desc );

			if ( layout.category ) {
				const cat     = createElement( 'span', { class: 'plugpanda-acf-fcp-plugin-card__category' } );
				cat.textContent = layout.category;
				body.appendChild( cat );
			}

			card.appendChild( body );

			if ( layout.locked ) {
				card.classList.add( 'is-locked' );
				card.setAttribute( 'aria-disabled', 'true' );
				const lockOverlay = createElement( 'div', { class: 'plugpanda-acf-fcp-plugin-card__lock-overlay' } );
				lockOverlay.innerHTML = '<span class="plugpanda-acf-fcp-plugin-card__lock-badge">PRO</span>';
				card.appendChild( lockOverlay );
			} else {
				card.addEventListener( 'click', function () {
					if ( reorderMode ) return;
					if ( selectedLayouts.length > 0 ) {
						toggleSelect( layout.name );
					} else {
						const $fe = currentFieldEl;
						close();
						appendLayout( $fe, layout.name );
					}
				} );

				badge.addEventListener( 'click', function ( e ) {
					e.stopPropagation();
					toggleSelect( layout.name );
				} );
			}

			return card;
		}

		function toggleSelect( name ) {
			const idx = selectedLayouts.indexOf( name );
			if ( idx === -1 ) {
				selectedLayouts.push( name );
			} else {
				selectedLayouts.splice( idx, 1 );
			}

			if ( reorderMode && selectedLayouts.length < 2 ) {
				exitReorderMode();
				return;
			}

			renumberBadges();
			updateFooter();
		}

		function renumberBadges() {
			if ( ! overlay ) return;
			overlay.querySelectorAll( '.plugpanda-acf-fcp-plugin-card' ).forEach( function ( card ) {
				const idx   = selectedLayouts.indexOf( card.dataset.layout );
				const badge = card.querySelector( '.plugpanda-acf-fcp-plugin-card__badge' );
				if ( idx === -1 ) {
					card.classList.remove( 'is-selected' );
					card.setAttribute( 'aria-pressed', 'false' );
					if ( badge ) badge.textContent = '';
				} else {
					card.classList.add( 'is-selected' );
					card.setAttribute( 'aria-pressed', 'true' );
					if ( badge ) badge.innerHTML = reorderMode
						? '<svg viewBox="0 0 10 2" xmlns="http://www.w3.org/2000/svg"><line x1="1" y1="1" x2="9" y2="1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
						: String( idx + 1 );
				}
			} );
		}

		function updateFooter() {
			if ( ! footer ) return;

			const n          = selectedLayouts.length;
			const addBtn     = footer.querySelector( '.plugpanda-acf-fcp-plugin-modal__footer-add' );
			const addSpan    = footer.querySelector( '.plugpanda-acf-fcp-plugin-modal__footer-add span' );
			const clearBtn   = footer.querySelector( '.plugpanda-acf-fcp-plugin-modal__footer-clear' );
			const reorderBtn = footer.querySelector( '.plugpanda-acf-fcp-plugin-modal__footer-reorder' );

			if ( addSpan ) addSpan.textContent = n > 0 ? 'Add ' + n + ' selected' : 'Add selected';
			if ( addBtn )   addBtn.disabled   = n === 0;
			if ( clearBtn ) clearBtn.disabled = n === 0;

			if ( reorderBtn && ! reorderMode ) {
				reorderBtn.disabled = n < 2;
			}
		}

		function buildFooter() {
			const el = createElement( 'div', { class: 'plugpanda-acf-fcp-plugin-modal__footer' } );

			const reorderBtn = createElement( 'button', { type: 'button', class: 'button plugpanda-acf-fcp-plugin-modal__footer-reorder' } );
			reorderBtn.innerHTML     = '<svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M13.5 2.5v3h-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M2.5 13.5v-3h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M13.5 5.5A5.5 5.5 0 0 0 4.5 3.2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M2.5 10.5a5.5 5.5 0 0 0 9 2.3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg><span>Reorder Selected</span>';
			reorderBtn.disabled = true;
			reorderBtn.addEventListener( 'click', function () {
				reorderMode ? exitReorderMode() : enterReorderMode();
			} );

			const addBtn = createElement( 'button', { type: 'button', class: 'button button-primary plugpanda-acf-fcp-plugin-modal__footer-add' } );
			addBtn.innerHTML = '<svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg><span>Add selected</span>';
			addBtn.disabled  = true;
			addBtn.addEventListener( 'click', function () {
				const toAdd = selectedLayouts.slice();
				const $fe   = currentFieldEl;
				close();
				toAdd.forEach( function ( name ) { appendLayout( $fe, name ); } );
			} );

			const clearBtn = createElement( 'button', { type: 'button', class: 'button plugpanda-acf-fcp-plugin-modal__footer-clear' } );
			clearBtn.innerHTML = '<svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" fill="currentColor"><path d="M153.654 18l52.57 134.734c1.698 3.994 4.05 5.83 7.243 6.977 3.2 1.15 7.36 1.2 11.058.17 3.698-1.03 6.71-3.146 7.996-4.915 1.288-1.77 1.634-2.564.505-5.24l-.046-.112L181.57 18h-27.916zm94.168 120.143l1.88 4.81-.09-.223c3.346 7.937 1.828 16.822-2.532 22.82-4.36 5.996-10.773 9.734-17.723 11.67-6.95 1.937-14.653 2.065-21.98-.57-7.327-2.634-14.155-8.447-17.742-16.923l-.05-.118-1.757-4.5c-31.31 19.804-42.47 42.026-35.367 68.89 1.24 4.681 3.422 12.364 5.964 22.13 74.37-5.274 139.945-23.872 199.808-51.6-10.297-13.867-22.5-25.83-38.232-34.53-20.505-11.34-47.652-20.157-72.178-21.857zm120.557 71.52c-61.497 28.81-129.173 48.378-205.575 54.196 2.03 8.683 4.08 18.28 5.95 28.495 89.592-10.084 163.043-26.22 217.755-48.767-5.743-11.72-11.593-23.19-18.13-33.924zm26.04 50.16c-57.093 23.772-131.99 40.087-222.73 50.322C180.697 371.423 179.614 446.752 128 480c16.27 0 31.892-.152 46.926-.45 17.84-25.554 31.27-66.222 32.08-86.146 8.27 16.793 3.297 59.32-5.36 85.434 2.735-.093 5.435-.193 8.127-.297 11.824-12.397 11.724-28.632 14.72-47.284 3.324 14.92 7 32.967 9.505 46.156 11.273-.616 22.152-1.34 32.606-2.183 16.38-20.358 21.65-49.604 18.63-85.48 4.226 29.1 9.116 62.138 11.873 82.55 9.662-1.083 18.925-2.29 27.807-3.614 5.04-18.787-4.1-48.444-2.072-69.54 11.123 43.113 22.247 55.45 33.37 64.043 5.42-1.115 10.655-2.293 15.733-3.526-4.7-13.95 1.573-22.497 1.18-39.986 5.647 18.99 14.625 26.958 24.428 32.816 6.506-2.1 12.66-4.336 18.492-6.697-10.538-6.57-10.113-26.374-12.38-42.926 5.954 21.703 14.413 32.418 24.083 37.816 29.124-13.8 48.69-31.534 60.398-53.657-9.078-3.82-18.674-13.002-28.068-20.092 13.214 7.477 23.684 10.614 32.37 10.93 1.323-3.206 2.514-6.49 3.552-9.868-56.326-19.528-80.07-64.018-101.58-108.178z"/></svg><span>Clear selected</span>';
			clearBtn.disabled  = true;
			clearBtn.addEventListener( 'click', function () {
				selectedLayouts = [];
				if ( reorderMode ) exitReorderMode();
				renumberBadges();
				updateFooter();
			} );

			const right = createElement( 'div', { class: 'plugpanda-acf-fcp-plugin-modal__footer-actions' } );
			right.appendChild( reorderBtn );
			right.appendChild( addBtn );

			el.appendChild( clearBtn );
			el.appendChild( right );
			return el;
		}

		function enterReorderMode() {
			if ( reorderMode || ! overlay ) return;
			const grid = overlay.querySelector( '.plugpanda-acf-fcp-plugin-layout-grid' );
			if ( ! grid ) return;

			reorderMode = true;
			grid.classList.add( 'is-reordering' );

			selectedLayouts.forEach( function ( name ) {
				const card = grid.querySelector( '.plugpanda-acf-fcp-plugin-card[data-layout="' + name + '"]' );
				if ( card ) grid.appendChild( card );
			} );

			const reorderBtn = footer && footer.querySelector( '.plugpanda-acf-fcp-plugin-modal__footer-reorder' );
			if ( reorderBtn ) {
				const span = reorderBtn.querySelector( 'span' );
				if ( span ) span.textContent = 'Done';
				reorderBtn.classList.add( 'button-primary' );
			}

			sortable = jQuery( grid ).sortable( {
				items:               '.plugpanda-acf-fcp-plugin-card.is-selected',
				cancel:              '.plugpanda-acf-fcp-plugin-card__badge',
				tolerance:           'pointer',
				cursor:              'grabbing',
				placeholder:         'plugpanda-acf-fcp-plugin-card--placeholder',
				forcePlaceholderSize: true,
				start: function ( e, ui ) {
					ui.placeholder.height( ui.item.outerHeight() );
				},
				update: function () {
					const order = [];
					grid.querySelectorAll( '.plugpanda-acf-fcp-plugin-card.is-selected' ).forEach( function ( c ) {
						order.push( c.dataset.layout );
					} );
					selectedLayouts = order;
				},
			} );

			renumberBadges();
		}

		function exitReorderMode() {
			if ( ! reorderMode || ! overlay ) return;
			const grid = overlay.querySelector( '.plugpanda-acf-fcp-plugin-layout-grid' );

			if ( sortable ) {
				sortable.sortable( 'destroy' );
				sortable = null;
			}

			if ( grid ) {
				grid.classList.remove( 'is-reordering' );
				Array.from( grid.querySelectorAll( '.plugpanda-acf-fcp-plugin-card' ) )
					.sort( ( a, b ) => +a.dataset.index - +b.dataset.index )
					.forEach( card => grid.appendChild( card ) );
			}

			reorderMode = false;

			const reorderBtn = footer && footer.querySelector( '.plugpanda-acf-fcp-plugin-modal__footer-reorder' );
			if ( reorderBtn ) {
				const span = reorderBtn.querySelector( 'span' );
				if ( span ) span.textContent = 'Reorder Selected';
				reorderBtn.classList.remove( 'button-primary' );
			}

			renumberBadges();
			updateFooter();
		}

		function handleKeyDown( e ) {
			if ( e.key === 'Escape' ) {
				if ( reorderMode ) { exitReorderMode(); }
				else if ( selectedLayouts.length ) { selectedLayouts = []; renumberBadges(); updateFooter(); }
				else { close(); }
				return;
			}
			if ( e.key !== 'Tab' || ! overlay ) return;

			const nodes = Array.from(
				overlay.querySelectorAll( 'button, input, [tabindex]:not([tabindex="-1"])' )
			).filter( ( n ) => ! n.disabled && n.offsetParent !== null );
			if ( ! nodes.length ) return;

			const first = nodes[ 0 ];
			const last  = nodes[ nodes.length - 1 ];

			if ( e.shiftKey && document.activeElement === first ) {
				e.preventDefault(); last.focus();
			} else if ( ! e.shiftKey && document.activeElement === last ) {
				e.preventDefault(); first.focus();
			}
		}

		function createElement( tag, attrs ) {
			const el = document.createElement( tag );
			Object.entries( attrs || {} ).forEach( ( [ k, v ] ) => el.setAttribute( k, v ) );
			return el;
		}

		return { open, close };
	} )();

	/* ------------------------------------------------------------------
	   Layout preview toggle
	   ------------------------------------------------------------------ */

	document.addEventListener( 'click', function ( e ) {
		const toggle = e.target.closest( '.plugpanda-acf-fcp-plugin-layout-preview__toggle' );
		if ( ! toggle ) return;
		const banner = toggle.closest( '.plugpanda-acf-fcp-plugin-layout-preview' );
		if ( ! banner ) return;
		const img = banner.querySelector( '.plugpanda-acf-fcp-plugin-layout-preview__img' );
		if ( ! img ) return;
		const collapsed = banner.classList.toggle( 'is-collapsed' );
		img.style.display = collapsed ? 'none' : '';
	} );

	/* ------------------------------------------------------------------
	   Layout preview banner inside expanded layout rows
	   ------------------------------------------------------------------ */

	function buildThumbnailMap() {
		const map    = {};
		const fields = AcfFcpPlugin.fields;
		if ( ! Array.isArray( fields ) ) return map;
		fields.forEach( function ( field ) {
			map[ field.key ] = {};
			( field.layouts || [] ).forEach( function ( layout ) {
				if ( layout.thumbnail ) map[ field.key ][ layout.name ] = layout.thumbnail;
			} );
		} );
		return map;
	}

	function injectLayoutPreview( layoutRow, fieldKey, map ) {
		if ( ! map[ fieldKey ] ) return;
		const thumbUrl = map[ fieldKey ][ layoutRow.dataset.layout ];
		if ( ! thumbUrl ) return;

		const fieldsEl = layoutRow.querySelector( '.acf-fields' );
		if ( ! fieldsEl || fieldsEl.querySelector( '.plugpanda-acf-fcp-plugin-layout-preview' ) ) return;

		const banner     = document.createElement( 'div' );
		banner.className = 'plugpanda-acf-fcp-plugin-layout-preview is-collapsed';

		const toggle     = document.createElement( 'button' );
		toggle.type      = 'button';
		toggle.className = 'plugpanda-acf-fcp-plugin-layout-preview__toggle';
		toggle.innerHTML =
			'<span>Layout Preview</span>' +
			'<svg class="plugpanda-acf-fcp-plugin-layout-preview__chevron" width="12" height="12" viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
				'<path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>' +
			'</svg>';

		const img         = document.createElement( 'img' );
		img.src           = thumbUrl;
		img.alt           = '';
		img.loading       = 'lazy';
		img.className     = 'plugpanda-acf-fcp-plugin-layout-preview__img';
		img.style.display = 'none';

		banner.appendChild( toggle );
		banner.appendChild( img );
		fieldsEl.insertBefore( banner, fieldsEl.firstChild );
	}

	function injectAllPreviews( map ) {
		document.querySelectorAll( '[data-type="flexible_content"]' ).forEach( function ( fieldEl ) {
			const fieldKey = fieldEl.dataset.key;
			if ( ! map[ fieldKey ] ) return;
			fieldEl.querySelectorAll( '[data-layout]' ).forEach( function ( row ) {
				injectLayoutPreview( row, fieldKey, map );
			} );
		} );
	}

	if ( typeof acf !== 'undefined' && typeof acf.addAction === 'function' ) {
		let thumbnailMap = null;

		acf.addAction( 'ready', function () {
			thumbnailMap = buildThumbnailMap();
			injectAllPreviews( thumbnailMap );
		} );

		acf.addAction( 'append', function ( $el ) {
			const map       = thumbnailMap || buildThumbnailMap();
			const el        = $el && $el[ 0 ] ? $el[ 0 ] : $el;
			const layoutRow = el.dataset && el.dataset.layout ? el : el.querySelector( '[data-layout]' );
			if ( ! layoutRow ) return;
			const fieldEl   = layoutRow.closest( '[data-type="flexible_content"]' );
			if ( ! fieldEl ) return;
			injectLayoutPreview( layoutRow, fieldEl.dataset.key, map );
		} );
	}

} )();
