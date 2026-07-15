( function () {
	'use strict';

	if ( typeof ASL_Data === 'undefined' ) {
		return;
	}

	var recaptchaReadyCallbacks = [];

	window.aslRecaptchaOnload = function () {
		recaptchaReadyCallbacks.forEach( function ( cb ) { cb(); } );
		recaptchaReadyCallbacks = [];
	};

	function onRecaptchaReady( cb ) {
		if ( window.grecaptcha && window.grecaptcha.render ) {
			cb();
		} else {
			recaptchaReadyCallbacks.push( cb );
		}
	}

	function toEnglishDigits( str ) {
		var persian = [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ];
		var arabic  = [ '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' ];
		for ( var i = 0; i < 10; i++ ) {
			str = str.split( persian[ i ] ).join( i ).split( arabic[ i ] ).join( i );
		}
		return str;
	}

	function isValidMobile( mobile ) {
		return /^09\d{9}$/.test( mobile );
	}

	function post( action, data ) {
		var body = new URLSearchParams( Object.assign( { action: action }, data ) );
		return fetch( ASL_Data.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} ).then( function ( res ) { return res.json(); } );
	}

	document.querySelectorAll( '.asl-card' ).forEach( initCard );

	function initCard( card ) {
		initTabs( card );
		card.querySelectorAll( '.asl-form' ).forEach( function ( form ) {
			initMobileNormalization( form );
			initRecaptchaHolder( card, form );
		} );
		card.querySelectorAll( '[data-form="login-otp"], [data-form="register-otp"]' ).forEach( function ( form ) {
			initOtpForm( card, form );
		} );
		var passwordForm = card.querySelector( '[data-form="login-password"]' );
		if ( passwordForm ) {
			initPasswordForm( card, passwordForm );
		}
		var switchBtn = card.querySelector( '[data-switch-method]' );
		if ( switchBtn ) {
			switchBtn.addEventListener( 'click', function () {
				toggleMethod( card );
			} );
		}
	}

	function initTabs( card ) {
		var tabs = card.querySelector( '.asl-tabs' );
		var buttons = card.querySelectorAll( '.asl-tab, .asl-link-tab' );

		buttons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				activatePanel( card, btn.getAttribute( 'data-target' ) );
			} );
		} );

		function activatePanel( card, target ) {
			card.querySelectorAll( '.asl-panel' ).forEach( function ( panel ) {
				panel.classList.toggle( 'is-active', panel.getAttribute( 'data-panel' ) === target );
			} );
			card.querySelectorAll( '.asl-tab' ).forEach( function ( tab ) {
				var active = tab.getAttribute( 'data-target' ) === target;
				tab.classList.toggle( 'is-active', active );
				tab.setAttribute( 'aria-selected', active ? 'true' : 'false' );
			} );
			if ( tabs ) {
				tabs.setAttribute( 'data-active', target );
			}
			clearAlert( card );
		}
	}

	function initMobileNormalization( form ) {
		var input = form.querySelector( 'input[name="mobile"]' );
		if ( ! input ) {
			return;
		}
		input.addEventListener( 'input', function () {
			var normalized = toEnglishDigits( input.value ).replace( /[^0-9]/g, '' );
			if ( normalized !== input.value ) {
				input.value = normalized;
			}
		} );
	}

	function initRecaptchaHolder( card, form ) {
		var formType = form.getAttribute( 'data-form' ) === 'login-password' ? 'password_login' : 'send_otp';
		var holder = form.querySelector( '[data-form-recaptcha="' + formType + '"]' );
		if ( ! holder || ! ASL_Data.recaptcha.enabled ) {
			return;
		}
		var applies = 'send_otp' === formType ? ASL_Data.recaptcha.onSendOtp : ASL_Data.recaptcha.onPasswordLogin;
		if ( ! applies ) {
			return;
		}

		holder.hidden = false;

		if ( 'v2' === ASL_Data.recaptcha.version ) {
			onRecaptchaReady( function () {
				if ( holder.dataset.widgetId === undefined ) {
					var widgetId = window.grecaptcha.render( holder, { sitekey: ASL_Data.recaptcha.siteKey } );
					holder.dataset.widgetId = String( widgetId );
				}
			} );
		}
	}

	function getRecaptchaToken( form, formType ) {
		if ( ! ASL_Data.recaptcha.enabled ) {
			return Promise.resolve( '' );
		}
		var applies = 'send_otp' === formType ? ASL_Data.recaptcha.onSendOtp : ASL_Data.recaptcha.onPasswordLogin;
		if ( ! applies ) {
			return Promise.resolve( '' );
		}

		if ( 'v3' === ASL_Data.recaptcha.version ) {
			return new Promise( function ( resolve ) {
				window.grecaptcha.ready( function () {
					window.grecaptcha.execute( ASL_Data.recaptcha.siteKey, { action: formType } ).then( resolve );
				} );
			} );
		}

		var holder = form.querySelector( '[data-form-recaptcha="' + formType + '"]' );
		var widgetId = holder && holder.dataset.widgetId !== undefined ? parseInt( holder.dataset.widgetId, 10 ) : 0;
		try {
			return Promise.resolve( window.grecaptcha.getResponse( widgetId ) );
		} catch ( e ) {
			return Promise.resolve( '' );
		}
	}

	function resetRecaptcha( form, formType ) {
		if ( ! ASL_Data.recaptcha.enabled || 'v2' !== ASL_Data.recaptcha.version ) {
			return;
		}
		var holder = form.querySelector( '[data-form-recaptcha="' + formType + '"]' );
		if ( holder && holder.dataset.widgetId !== undefined && window.grecaptcha ) {
			window.grecaptcha.reset( parseInt( holder.dataset.widgetId, 10 ) );
		}
	}

	function toggleMethod( card ) {
		var otpForm = card.querySelector( '[data-form="login-otp"]' );
		var passwordForm = card.querySelector( '[data-form="login-password"]' );
		if ( ! otpForm || ! passwordForm ) {
			return;
		}
		var showingOtp = ! otpForm.hasAttribute( 'hidden' );
		otpForm.hidden = showingOtp;
		passwordForm.hidden = ! showingOtp;
		card.querySelectorAll( '.asl-show-on-otp' ).forEach( function ( el ) { el.hidden = ! showingOtp; } );
		card.querySelectorAll( '.asl-show-on-password' ).forEach( function ( el ) { el.hidden = showingOtp; } );
		clearAlert( card );
	}

	function showAlert( card, message, type ) {
		var alertBox = card.querySelector( '.asl-alert' );
		if ( ! alertBox ) {
			return;
		}
		alertBox.textContent = message;
		alertBox.hidden = false;
		alertBox.className = 'asl-alert is-' + ( type || 'error' );
	}

	function clearAlert( card ) {
		var alertBox = card.querySelector( '.asl-alert' );
		if ( alertBox ) {
			alertBox.hidden = true;
		}
	}

	function setLoading( button, loading ) {
		if ( ! button ) {
			return;
		}
		button.disabled = loading;
		if ( loading ) {
			button.dataset.originalText = button.textContent;
			button.textContent = '...';
		} else if ( button.dataset.originalText ) {
			button.textContent = button.dataset.originalText;
		}
	}

	function startCountdown( button, seconds, resendLabel ) {
		var remaining = seconds;
		button.disabled = true;
		var tick = function () {
			if ( remaining <= 0 ) {
				button.disabled = false;
				button.textContent = resendLabel;
				return;
			}
			button.textContent = remaining + 'ث';
			remaining--;
			setTimeout( tick, 1000 );
		};
		tick();
	}

	function initOtpForm( card, form ) {
		var isRegister = form.getAttribute( 'data-form' ) === 'register-otp';
		var mode = isRegister ? 'register' : 'login';
		var mobileInput = form.querySelector( 'input[name="mobile"]' );
		var nameInput = form.querySelector( 'input[name="name"]' );
		var codeRow = form.querySelector( '.asl-otp-row' );
		var codeInput = form.querySelector( 'input[name="code"]' );
		var resendBtn = form.querySelector( '.asl-resend-btn' );
		var submitBtn = form.querySelector( 'button[type="submit"]' );
		var resendLabel = resendBtn ? resendBtn.textContent : '';

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			clearAlert( card );

			if ( 'request' === submitBtn.dataset.step ) {
				requestCode();
			} else {
				verifyCode();
			}
		} );

		if ( resendBtn ) {
			resendBtn.addEventListener( 'click', function () {
				clearAlert( card );
				requestCode( true );
			} );
		}

		function requestCode( isResend ) {
			var mobile = toEnglishDigits( mobileInput.value.trim() );
			if ( ! isValidMobile( mobile ) ) {
				showAlert( card, ASL_Data.messages.invalidMobile, 'error' );
				return;
			}

			setLoading( isResend ? resendBtn : submitBtn, true );

			getRecaptchaToken( form, 'send_otp' ).then( function ( token ) {
				var nonce = form.querySelector( 'input[name="_asl_nonce"]' ).value;
				return post( 'asl_send_otp', {
					nonce: nonce,
					mobile: mobile,
					mode: mode,
					name: nameInput ? nameInput.value : '',
					recaptcha_token: token,
				} );
			} ).then( function ( res ) {
				setLoading( isResend ? resendBtn : submitBtn, false );
				resetRecaptcha( form, 'send_otp' );

				if ( ! res.success ) {
					showAlert( card, res.data && res.data.message ? res.data.message : '' );
					return;
				}

				mobileInput.readOnly = true;
				codeRow.hidden = false;
				submitBtn.dataset.step = 'verify';
				submitBtn.textContent = ( form.dataset.verifyLabel || submitBtn.textContent );
				codeInput.focus();

				var wait = res.data && res.data.resend_wait ? res.data.resend_wait : 60;
				startCountdown( resendBtn, wait, resendLabel );

				showAlert( card, res.data && res.data.message ? res.data.message : '', 'success' );
			} ).catch( function () {
				setLoading( isResend ? resendBtn : submitBtn, false );
				showAlert( card, ASL_Data.messages.genericError );
			} );
		}

		function verifyCode() {
			var mobile = toEnglishDigits( mobileInput.value.trim() );
			var code = codeInput.value.trim();

			setLoading( submitBtn, true );

			var nonce = form.querySelector( 'input[name="_asl_nonce"]' ).value;
			post( 'asl_verify_otp', { nonce: nonce, mobile: mobile, code: code } ).then( function ( res ) {
				if ( ! res.success ) {
					setLoading( submitBtn, false );
					showAlert( card, res.data && res.data.message ? res.data.message : '' );
					return;
				}
				showAlert( card, res.data && res.data.message ? res.data.message : '', 'success' );
				window.location.href = ( res.data && res.data.redirect ) ? res.data.redirect : window.location.href;
			} ).catch( function () {
				setLoading( submitBtn, false );
				showAlert( card, ASL_Data.messages.genericError );
			} );
		}
	}

	function initPasswordForm( card, form ) {
		var submitBtn = form.querySelector( 'button[type="submit"]' );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			clearAlert( card );

			var mobile = toEnglishDigits( form.querySelector( 'input[name="mobile"]' ).value.trim() );
			var password = form.querySelector( 'input[name="password"]' ).value;

			setLoading( submitBtn, true );

			getRecaptchaToken( form, 'password_login' ).then( function ( token ) {
				var nonce = form.querySelector( 'input[name="_asl_nonce"]' ).value;
				return post( 'asl_password_login', {
					nonce: nonce,
					mobile: mobile,
					password: password,
					recaptcha_token: token,
				} );
			} ).then( function ( res ) {
				resetRecaptcha( form, 'password_login' );

				if ( ! res.success ) {
					setLoading( submitBtn, false );
					showAlert( card, res.data && res.data.message ? res.data.message : '' );
					return;
				}

				showAlert( card, res.data && res.data.message ? res.data.message : '', 'success' );
				window.location.href = ( res.data && res.data.redirect ) ? res.data.redirect : window.location.href;
			} ).catch( function () {
				setLoading( submitBtn, false );
				showAlert( card, ASL_Data.messages.genericError );
			} );
		} );
	}
} )();
