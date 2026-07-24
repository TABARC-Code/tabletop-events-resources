/**
 * Self-service "manage your resource listing" page — edit details or
 * take the listing down, using the token from the confirmation email.
 * No account needed. Mirrors the Carpool plugin's manage-listing
 * pattern.
 */
(function () {
	'use strict';

	var REST = ( window.TRES_MANAGE && window.TRES_MANAGE.restUrl ) || '/wp-json/tres/v1';
	var POST_ID = ( window.TRES_MANAGE && window.TRES_MANAGE.postId ) || 0;
	var TOKEN = ( window.TRES_MANAGE && window.TRES_MANAGE.token ) || '';

	document.addEventListener( 'DOMContentLoaded', function () {
		var root = document.querySelector( '[data-tres-manage]' );
		if ( !root ) return;

		if ( !POST_ID || !TOKEN ) {
			root.innerHTML = '<p>This link is missing its post or token — please use the link from your email.</p>';
			return;
		}

		root.innerHTML = '<p>Loading…</p>';

		fetch( REST + '/manage/' + POST_ID + '?token=' + encodeURIComponent( TOKEN ) )
			.then( function ( r ) { return r.json().then( function ( body ) { return { ok: r.ok, body: body }; } ); } )
			.then( function ( result ) {
				if ( !result.ok ) {
					root.innerHTML = '<p>' + ( ( result.body && result.body.message ) || 'This link is invalid or has expired.' ) + '</p>';
					return;
				}
				render( root, result.body );
			} )
			.catch( function () { root.innerHTML = '<p>Something went wrong loading your listing.</p>'; } );
	} );

	function render( root, resource ) {
		root.innerHTML =
			'<p style="color:#666;">Status: <strong>' + escapeHtml( resource.status ) + '</strong></p>' +
			'<form class="tres-mf-form" novalidate>' +
				field( 'text', 'title', 'Item Name', resource.title ) +
				numberField( 'quantity', 'Quantity Available', resource.quantity ) +
				'<div class="tec-sf-field"><label>Notes</label><textarea name="notes" rows="3">' + escapeHtml( resource.notes || '' ) + '</textarea></div>' +
				'<button type="submit" class="tec-sf-submit">Save Changes</button>' +
				'<div class="tec-sf-error-banner"></div>' +
				'<div class="tec-sf-success">Saved!</div>' +
			'</form>' +
			'<hr style="margin:28px 0;border:none;border-top:1px solid #ddd;">' +
			'<p><button type="button" class="tres-mf-remove" style="background:#C0392B;color:#fff;border:none;padding:10px 20px;border-radius:4px;cursor:pointer;">Take This Listing Down</button></p>' +
			'<div class="tres-mf-msg"></div>';

		var form = root.querySelector( '.tres-mf-form' );
		var errorBanner = root.querySelector( '.tec-sf-error-banner' );
		var success = root.querySelector( '.tec-sf-success' );

		form.addEventListener( 'submit', function ( evt ) {
			evt.preventDefault();
			errorBanner.classList.remove( 'visible' );
			success.classList.remove( 'visible' );

			var data = {
				token: TOKEN,
				title: val( form, 'title' ),
				quantity: val( form, 'quantity' ),
				notes: val( form, 'notes' ),
			};

			fetch( REST + '/manage/' + POST_ID, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( data ),
			} )
				.then( function ( r ) { return r.json().then( function ( body ) { return { ok: r.ok, body: body }; } ); } )
				.then( function ( result ) {
					if ( !result.ok ) throw new Error( ( result.body && result.body.message ) || 'Could not save.' );
					success.classList.add( 'visible' );
				} )
				.catch( function ( err ) {
					errorBanner.textContent = err.message;
					errorBanner.classList.add( 'visible' );
				} );
		} );

		root.querySelector( '.tres-mf-remove' ).addEventListener( 'click', function () {
			if ( !window.confirm( 'Take this listing down?' ) ) return;
			fetch( REST + '/manage/' + POST_ID + '/remove', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( { token: TOKEN } ),
			} )
				.then( function ( r ) { return r.json(); } )
				.then( function ( body ) { root.querySelector( '.tres-mf-msg' ).textContent = body.message || 'Done.'; } )
				.catch( function () { root.querySelector( '.tres-mf-msg' ).textContent = 'Something went wrong.'; } );
		} );
	}

	function field( type, name, label, value ) {
		return (
			'<div class="tec-sf-field">' +
				'<label>' + label + '</label>' +
				'<input type="' + type + '" name="' + name + '" value="' + escapeAttr( value || '' ) + '">' +
			'</div>'
		);
	}
	function numberField( name, label, value ) {
		return (
			'<div class="tec-sf-field">' +
				'<label>' + label + '</label>' +
				'<input type="number" min="1" name="' + name + '" value="' + escapeAttr( value || 1 ) + '">' +
			'</div>'
		);
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
	function escapeAttr( str ) {
		return String( str == null ? '' : str ).replace( /"/g, '&quot;' );
	}
})();
