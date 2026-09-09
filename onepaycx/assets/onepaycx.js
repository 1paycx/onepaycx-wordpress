/* global window, document, fetch */
( function () {
	'use strict';

	function createPayment( url, payload ) {
		return fetch( url, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( payload ),
		} ).then( function ( r ) {
			return r.json();
		} );
	}

	function t( key, fallback ) {
		var cfg = window.OnePayCx || {};
		return ( cfg.i18n && cfg.i18n[ key ] ) ? cfg.i18n[ key ] : fallback;
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.onepaycx-btn' );
		if ( ! btn ) {
			return;
		}
		e.preventDefault();

		var wrap = btn.closest( '.onepaycx-pay' );
		if ( ! wrap ) {
			return;
		}
		var msg = wrap.querySelector( '.onepaycx-msg' );
		var open = wrap.getAttribute( 'data-open' ) === '1';
		var direct = wrap.getAttribute( 'data-direct' ) === '1';
		var amount = null;
		var email = '';

		if ( open ) {
			var input = wrap.querySelector( '.onepaycx-amount' );
			amount = input ? parseFloat( input.value ) : 0;
			if ( ! amount || amount <= 0 ) {
				if ( input ) {
					input.focus();
				}
				if ( msg ) {
					msg.textContent = t( 'amount', 'Enter an amount.' );
				}
				return;
			}
		}

		if ( direct ) {
			var emailInput = wrap.querySelector( '.onepaycx-email' );
			email = emailInput ? emailInput.value.trim() : '';
			if ( ! /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test( email ) ) {
				if ( emailInput ) {
					emailInput.focus();
				}
				if ( msg ) {
					msg.textContent = t( 'email', 'Enter your email for the receipt.' );
				}
				return;
			}
		}

		if ( msg ) {
			msg.textContent = '';
		}
		btn.disabled = true;

		var cfg = window.OnePayCx || {};
		createPayment( cfg.checkoutUrl, {
			token: wrap.getAttribute( 'data-token' ),
			amount: amount,
			email: email,
			return: window.location.href,
		} )
			.then( function ( res ) {
				if ( res && res.url ) {
					window.location = res.url;
				} else {
					btn.disabled = false;
					if ( msg ) {
						msg.textContent = ( res && res.error ) ? res.error : t( 'failed', 'Payment could not be started.' );
					}
				}
			} )
			.catch( function () {
				btn.disabled = false;
				if ( msg ) {
					msg.textContent = t( 'network', 'Network error — please try again.' );
				}
			} );
	} );
} )();
