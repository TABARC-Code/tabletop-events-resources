/**
 * Tabletop Events Calendar — Resources — organiser widget. Shows an
 * organiser's lending list plus a "list an item" form. The form is
 * always shown; an email that doesn't match the organiser on record
 * gets a clear rejection message from the REST endpoint rather than
 * trying to predict it client-side.
 */
(function () {
	'use strict';

	var REST = ( window.TRES_RESOURCES && window.TRES_RESOURCES.restUrl ) || '/wp-json/tres/v1';
	var ANCHOR_ID = ( window.TRES_RESOURCES && window.TRES_RESOURCES.anchorId ) || 0;

	var CATEGORY_LABELS = { terrain: 'Terrain', tables: 'Tables', dice_minis: 'Dice / Minis', other: 'Other' };

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-tres-resources]' ).forEach( init );
	} );

	function init( root ) {
		root.innerHTML = '<div class="tres-empty">Loading…</div>';

		fetch( REST + '/organiser/' + ANCHOR_ID )
			.then( function ( r ) { return r.json(); } )
			.then( function ( resources ) {
				root.innerHTML = listHtml( resources ) + formHtml();
				bind( root );
			} )
			.catch( function () {
				root.innerHTML = '<div class="tres-empty">Could not load the resource list.</div>';
			} );
	}

	function listHtml( resources ) {
		if ( ! Array.isArray( resources ) || ! resources.length ) {
			return '<p class="tres-empty">Nothing listed yet.</p>';
		}
		return '<div class="tres-list">' + resources.map( card ).join( '' ) + '</div>';
	}

	function card( r ) {
		return (
			'<div class="tres-card">' +
				'<div class="tres-card-top">' +
					'<span class="tres-badge">' + escapeHtml( CATEGORY_LABELS[ r.category ] || 'Other' ) + '</span>' +
					'<span class="tres-card-qty">×' + r.quantity + '</span>' +
				'</div>' +
				'<div class="tres-card-title">' + escapeHtml( r.title ) + '</div>' +
				( r.notes ? '<p class="tres-card-notes">' + escapeHtml( r.notes ) + '</p>' : '' ) +
			'</div>'
		);
	}

	function formHtml() {
		return (
			'<h3 class="tres-form-heading">List an item</h3>' +
			'<p class="tec-sf-help">Only works with the organiser email on record for this listing page.</p>' +
			'<form class="tec-sf-form tres-form" novalidate>' +
				'<div class="tec-sf-field" data-field="email"><label>Organiser Email</label><input type="email" name="email" required><div class="tec-sf-err">This field is required</div></div>' +
				'<div class="tec-sf-row">' +
					'<div class="tec-sf-field" data-field="title"><label>Item Name</label><input type="text" name="title" required><div class="tec-sf-err">This field is required</div></div>' +
					'<div class="tec-sf-field" data-field="category"><label>Category</label><select name="category">' +
						'<option value="terrain">Terrain</option><option value="tables">Tables</option><option value="dice_minis">Dice / Minis</option><option value="other">Other</option>' +
					'</select></div>' +
				'</div>' +
				'<div class="tec-sf-field" data-field="quantity"><label>Quantity Available</label><input type="number" name="quantity" min="1" value="1"></div>' +
				'<div class="tec-sf-field" data-field="notes"><label>Notes (condition, collection arrangements)</label><textarea name="notes" rows="3"></textarea></div>' +
				'<div class="tec-sf-honeypot"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>' +
				'<button type="submit" class="tec-sf-submit">List Item</button>' +
				'<div class="tec-sf-error-banner"></div>' +
				'<div class="tec-sf-success">Thanks — we\'ll review your listing shortly. Check your email for a link to edit it or take it down later.</div>' +
			'</form>'
		);
	}

	function bind( root ) {
		var form = root.querySelector( '.tres-form' );
		if ( ! form ) return;
		var errorBanner = root.querySelector( '.tec-sf-error-banner' );
		var success = root.querySelector( '.tec-sf-success' );

		form.addEventListener( 'submit', function ( evt ) {
			evt.preventDefault();
			errorBanner.classList.remove( 'visible' );

			var data = {
				organiser: ANCHOR_ID,
				email: val( form, 'email' ),
				title: val( form, 'title' ),
				category: val( form, 'category' ),
				quantity: val( form, 'quantity' ) || 1,
				notes: val( form, 'notes' ),
				website: val( form, 'website' ),
			};
			form.querySelectorAll( '.tec-sf-field' ).forEach( function ( el ) { el.classList.remove( 'invalid' ); } );
			var valid = true;
			if ( ! data.email ) valid = invalid( form, 'email' );
			if ( ! data.title ) valid = invalid( form, 'title' );
			if ( ! valid ) return;

			fetch( REST + '/submit', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( data ),
			} )
				.then( function ( r ) { return r.json().then( function ( body ) { return { ok: r.ok, body: body }; } ); } )
				.then( function ( result ) {
					if ( !result.ok ) throw new Error( ( result.body && result.body.message ) || 'Could not submit your listing.' );
					form.style.display = 'none';
					success.classList.add( 'visible' );
				} )
				.catch( function ( err ) {
					errorBanner.textContent = err.message;
					errorBanner.classList.add( 'visible' );
				} );
		} );
	}

	function invalid( form, fieldName ) {
		var el = form.querySelector( '[data-field="' + fieldName + '"]' );
		if ( el ) el.classList.add( 'invalid' );
		return false;
	}
	function val( form, name ) {
		var input = form.querySelector( '[name="' + name + '"]' );
		return input ? input.value.trim() : '';
	}
	function escapeHtml( str ) {
		var div = document.createElement( 'div' );
		div.textContent = String( str == null ? '' : str );
		return div.innerHTML;
	}
})();
