/**
 * Dernek Yazılımı forms: send the answers to this site, which passes them on
 * to the association's portal, then show the portal's page in a frame.
 */
( function () {
	'use strict';

	var settings = window.dernekyazilimiForms || {};
	var i18n = settings.i18n || {};

	function post( path, data ) {
		return fetch( settings.restUrl + path, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
			credentials: 'omit',
			body: JSON.stringify( data ),
		} ).then( function ( response ) {
			return response.json().catch( function () {
				return {};
			} ).then( function ( body ) {
				return { ok: response.ok, body: body || {} };
			} );
		} );
	}

	function values( form ) {
		var data = {};
		Array.prototype.forEach.call( form.elements, function ( element ) {
			if ( ! element.name || element.disabled ) {
				return;
			}
			if ( ( element.type === 'checkbox' || element.type === 'radio' ) && ! element.checked ) {
				return;
			}
			var nested = element.name.match( /^(\w+)\[(\w+)\]$/ );
			if ( nested ) {
				data[ nested[ 1 ] ] = data[ nested[ 1 ] ] || {};
				data[ nested[ 1 ] ][ nested[ 2 ] ] = element.value;
			} else {
				data[ element.name ] = element.value;
			}
		} );
		return data;
	}

	function setup( root ) {
		var form = root.querySelector( '.dy-form' );
		var message = root.querySelector( '.dy-message' );
		var submit = root.querySelector( '.dy-submit' );
		var frameBox = root.querySelector( '.dy-frame' );
		var frame = frameBox ? frameBox.querySelector( 'iframe' ) : null;
		var kind = root.getAttribute( 'data-dy-kind' );
		var verifiedPhone = null;

		if ( ! form ) {
			return;
		}

		function say( text, good ) {
			message.textContent = text || '';
			message.hidden = ! text;
			message.classList.toggle( 'dy-good', !! good );
		}

		function clearErrors() {
			say( '' );
			Array.prototype.forEach.call( root.querySelectorAll( '.dy-error' ), function ( node ) {
				node.remove();
			} );
			Array.prototype.forEach.call( root.querySelectorAll( '.dy-invalid' ), function ( node ) {
				node.classList.remove( 'dy-invalid' );
			} );
		}

		function fieldError( name, text ) {
			var field = root.querySelector( '[data-dy-field="' + name + '"]' );
			if ( ! field ) {
				return false;
			}
			var note = document.createElement( 'p' );
			note.className = 'dy-error';
			note.textContent = text;
			field.appendChild( note );
			field.classList.add( 'dy-invalid' );
			return true;
		}

		function showErrors( body ) {
			var shown = false;
			var first = null;
			Object.keys( body.errors || {} ).forEach( function ( name ) {
				if ( fieldError( name, body.errors[ name ][ 0 ] ) ) {
					shown = true;
					first = first || root.querySelector( '[data-dy-field="' + name + '"]' );
				}
			} );
			if ( ! shown || ! body.errors ) {
				say( body.message || i18n.failed );
			}
			if ( first ) {
				first.scrollIntoView( { block: 'center', behavior: 'smooth' } );
			}
		}

		function validate() {
			var valid = true;
			Array.prototype.forEach.call( form.querySelectorAll( '[required]' ), function ( element ) {
				if ( element.checkValidity() ) {
					return;
				}
				var field = element.closest( '[data-dy-field]' );
				if ( field && ! field.querySelector( '.dy-error' ) ) {
					fieldError( field.getAttribute( 'data-dy-field' ), element.validationMessage || i18n.required );
				}
				if ( valid ) {
					element.focus();
				}
				valid = false;
			} );
			return valid;
		}

		function busy( button, on ) {
			if ( on ) {
				button.dataset.dyLabel = button.textContent;
				button.textContent = i18n.sending;
			} else if ( button.dataset.dyLabel ) {
				button.textContent = button.dataset.dyLabel;
			}
			button.disabled = on;
		}

		var modal = root.querySelector( '.dy-modal:not(.dy-doc-modal)' );
		var docModal = root.querySelector( '.dy-doc-modal' );

		// Agreement text: the portal's page opens in a dialog over the page.
		if ( docModal && typeof docModal.showModal === 'function' ) {
			var docFrame = docModal.querySelector( 'iframe' );
			Array.prototype.forEach.call( root.querySelectorAll( '.dy-doc-link' ), function ( link ) {
				link.addEventListener( 'click', function ( event ) {
					if ( event.metaKey || event.ctrlKey || event.shiftKey ) {
						return;
					}
					event.preventDefault();
					docFrame.src = link.href;
					document.documentElement.classList.add( 'dy-modal-open' );
					docModal.showModal();
				} );
			} );
			docModal.querySelector( '.dy-modal-close' ).addEventListener( 'click', function () {
				docModal.close();
			} );
			docModal.addEventListener( 'click', function ( event ) {
				if ( event.target === docModal ) {
					docModal.close();
				}
			} );
			docModal.addEventListener( 'close', function () {
				document.documentElement.classList.remove( 'dy-modal-open' );
				docFrame.src = 'about:blank';
			} );
		}
		var framePlace = frameBox ? document.createComment( 'dy-frame' ) : null;
		var resultUrl = '';
		var reachedPortal = false;

		function showFrame( url, note ) {
			var noteBox = frameBox.querySelector( '.dy-note' );
			noteBox.textContent = note || '';
			noteBox.hidden = ! note;
			frameBox.querySelector( '.dy-fallback a' ).href = url;
			frame.style.height = '';
			frame.src = url;
			form.hidden = true;
			frameBox.hidden = false;
			root.scrollIntoView( { block: 'start', behavior: 'smooth' } );
		}

		function showForm() {
			frame.src = 'about:blank';
			frameBox.hidden = true;
			form.hidden = false;
		}

		// Card payment: the provider's page opens in a dialog over the page.
		function showPayment( url, result ) {
			if ( ! modal || typeof modal.showModal !== 'function' ) {
				showFrame( url );
				return;
			}
			resultUrl = result || '';
			reachedPortal = false;
			frameBox.parentNode.insertBefore( framePlace, frameBox );
			modal.querySelector( '.dy-modal-body' ).appendChild( frameBox );
			frameBox.querySelector( '.dy-fallback a' ).href = url;
			frame.style.height = '';
			frame.src = url;
			frameBox.hidden = false;
			document.documentElement.classList.add( 'dy-modal-open' );
			modal.showModal();
		}

		function closePayment() {
			if ( ! reachedPortal && ! window.confirm( i18n.closePayment ) ) {
				return;
			}
			modal.close();
		}

		if ( modal ) {
			modal.querySelector( '.dy-modal-close' ).addEventListener( 'click', closePayment );
			// Escape asks first, like the close button.
			modal.addEventListener( 'cancel', function ( event ) {
				event.preventDefault();
				closePayment();
			} );
			modal.addEventListener( 'close', function () {
				document.documentElement.classList.remove( 'dy-modal-open' );
				framePlace.parentNode.replaceChild( frameBox, framePlace );
				// After the payment the result stays on the page; a payment
				// left unfinished brings the form back.
				if ( reachedPortal && resultUrl ) {
					showFrame( resultUrl );
				} else {
					showForm();
				}
			} );
		}

		// The portal's pages report their height and may ask for the form again.
		window.addEventListener( 'message', function ( event ) {
			if ( ! frame || event.source !== frame.contentWindow || event.origin !== settings.portalOrigin || ! event.data ) {
				return;
			}
			if ( event.data.type === 'dernekyazilimi:height' && event.data.height > 0 ) {
				reachedPortal = true;
				frame.style.height = Math.min( Math.ceil( event.data.height ) + 8, 20000 ) + 'px';
			}
			if ( event.data.type === 'dernekyazilimi:restart' ) {
				reachedPortal = false;
				if ( modal && modal.open ) {
					modal.close();
				} else {
					showForm();
				}
			}
		} );

		// Suggested amounts.
		Array.prototype.forEach.call( root.querySelectorAll( '[data-dy-amount]' ), function ( button ) {
			button.addEventListener( 'click', function () {
				form.elements.amount.value = button.getAttribute( 'data-dy-amount' );
			} );
		} );

		// Phone verification.
		var phone = form.elements.phone_number;
		var sendCode = root.querySelector( '.dy-send-code' );
		var verifyCode = root.querySelector( '.dy-verify-code' );
		var codeBox = root.querySelector( '.dy-code' );
		var phoneState = root.querySelector( '.dy-phone-state' );

		if ( phone && sendCode ) {
			phone.addEventListener( 'input', function () {
				if ( verifiedPhone !== null && phone.value !== verifiedPhone ) {
					verifiedPhone = null;
					phoneState.classList.remove( 'dy-good' );
					phoneState.textContent = i18n.verifyFirst;
					sendCode.hidden = false;
				}
			} );

			sendCode.addEventListener( 'click', function () {
				clearErrors();
				if ( ! phone.value.trim() ) {
					fieldError( 'phone_number', i18n.required );
					phone.focus();
					return;
				}
				busy( sendCode, true );
				post( 'phone/send', { phone_number: phone.value, website: form.elements.website.value } ).then( function ( result ) {
					busy( sendCode, false );
					if ( ! result.ok ) {
						fieldError( 'phone_number', ( result.body.errors && result.body.errors.phone_number && result.body.errors.phone_number[ 0 ] ) || result.body.message || i18n.failed );
						return;
					}
					sendCode.textContent = i18n.sendAgain;
					phoneState.textContent = i18n.codeSent;
					codeBox.hidden = false;
					form.elements.code.focus();
				} ).catch( function () {
					busy( sendCode, false );
					fieldError( 'phone_number', i18n.failed );
				} );
			} );

			verifyCode.addEventListener( 'click', function () {
				clearErrors();
				var code = form.elements.code.value.replace( /\D/g, '' );
				if ( code.length !== 6 ) {
					fieldError( 'code', i18n.codeNeeded );
					return;
				}
				busy( verifyCode, true );
				post( 'phone/verify', { phone_number: phone.value, code: code, website: form.elements.website.value } ).then( function ( result ) {
					busy( verifyCode, false );
					if ( ! result.ok ) {
						fieldError( 'code', result.body.message || i18n.failed );
						return;
					}
					verifiedPhone = phone.value;
					codeBox.hidden = true;
					sendCode.hidden = true;
					phoneState.textContent = i18n.verified;
					phoneState.classList.add( 'dy-good' );
				} ).catch( function () {
					busy( verifyCode, false );
					fieldError( 'code', i18n.failed );
				} );
			} );
		}

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			clearErrors();
			if ( ! validate() ) {
				return;
			}
			if ( phone && sendCode && verifiedPhone !== phone.value ) {
				fieldError( 'phone_number', i18n.verifyFirst );
				phone.scrollIntoView( { block: 'center', behavior: 'smooth' } );
				return;
			}

			var data = values( form );
			var path = 'donations';
			if ( root.getAttribute( 'data-dy-form' ) === 'account' ) {
				path = 'accounts';
				data.form = kind;
				delete data.code;
			}

			busy( submit, true );
			post( path, data ).then( function ( result ) {
				busy( submit, false );
				if ( ! result.ok ) {
					showErrors( result.body );
					return;
				}
				if ( result.body.frame_url && result.body.method === 'card' ) {
					showPayment( result.body.frame_url, result.body.result_url );
				} else if ( result.body.frame_url ) {
					showFrame( result.body.frame_url );
				} else if ( result.body.continue_url ) {
					showFrame( result.body.continue_url, result.body.created === false ? i18n.existingNote : '' );
				} else {
					form.hidden = true;
					root.querySelector( '.dy-done' ).hidden = false;
					root.scrollIntoView( { block: 'start', behavior: 'smooth' } );
				}
			} ).catch( function () {
				busy( submit, false );
				say( i18n.failed );
			} );
		} );
	}

	function start() {
		Array.prototype.forEach.call( document.querySelectorAll( '.dernekyazilimi[data-dy-form]' ), function ( root ) {
			if ( ! root.dataset.dyReady ) {
				root.dataset.dyReady = '1';
				setup( root );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
