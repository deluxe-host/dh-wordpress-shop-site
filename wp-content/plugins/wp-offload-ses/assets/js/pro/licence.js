(function( $, _ ) {

	var adminUrl = ajaxurl.replace( '/admin-ajax.php', '' );
	var spinnerUrl = adminUrl + '/images/spinner';

	var savedSettings = null;
	var $body = $( 'body' );
	var $main = $( '.wposes-main' );
	var initSupportTab;

	if ( window.devicePixelRatio >= 2 ) {
		spinnerUrl += '-2x';
	}

	spinnerUrl += '.gif';

	wposes.spinnerUrl = spinnerUrl;

	/**
	 * Checks if the current page is part of the setup wizard.
	 *
	 * @return bool
	 */
	function isSetupWizard() {
		if ( window.location.href.indexOf( 'setup-wizard' ) > -1 ) {
			return true;
		}

		return false;
	}

	/**
	 * Licence Key API object
	 * @constructor
	 */
	var LicenceApi = function() {
		this.$key = $main.find( '.wposes-licence-input' );
		this.$spinner = $main.find( '[data-wposes-licence-spinner]' );
		this.$feedback = $main.find( '[data-wposes-licence-feedback]' );
	};

	/**
	 * Set the access keys using the values in the settings fields.
	 */
	LicenceApi.prototype.activate = function() {
		var licenceKey = $.trim( this.$key.val() );

		if ( '' === licenceKey ) {
			this.$feedback.addClass( 'notice-error' );
			this.$feedback.html( '<p>' + wposes.strings.enter_licence_key + '</p>' ).show();
			return;
		}

		return this.sendRequest( 'activate', {
			licence_key: licenceKey
		} )
			.done( function( response ) {
				if ( response.success && response.data ) {
					this.$key.val( response.data.masked_licence );
					this.$key.prop( 'disabled', 'disabled' );
				}
			}.bind( this ) )
			.fail( function() {
				this.$feedback.html( '<p>' + wposes.strings.register_licence_problem + '</p>' ).show();
			}.bind( this ) )
		;
	};

	/**
	 * Remove the access keys from the database and clear the fields.
	 */
	LicenceApi.prototype.remove = function() {
		return this.sendRequest( 'remove' )
			.done( function( response ) {
				if ( response.success ) {
					this.$key.val( '' );
				}
			}.bind( this ) )
		;
	};

	/**
	 * Check the licence key
	 */
	LicenceApi.prototype.check = function() {
		return this.sendRequest( 'check' )
			.fail( function( jqXHR ) {

				// Ignore incomplete requests, likely due to navigating away from the page
				if ( jqXHR.readyState < 4 ) {
					return;
				}

				alert( wposes.strings.licence_check_problem );
			} )
		;
	};

	/**
	 * Send the request to the server to update the licence key.
	 *
	 * @param {string} action The action to perform with the keys
	 * @param {undefined|Object} params Extra parameters to send with the request
	 *
	 * @returns {jqXHR}
	 */
	LicenceApi.prototype.sendRequest = function( action, params ) {
		var data = {
			action: 'wposes_' + action + '_licence',
			_ajax_nonce: wposes.nonces[ action + '_licence' ]
		};

		if ( _.isObject( params ) ) {
			data = _.extend( data, params );
		}

		if ( ! isSetupWizard() ) {
			this.$spinner.addClass( 'is-active' ).show();
		}

		return $.post( ajaxurl, data )
			.done( function( response ) {
				this.$feedback
					.toggleClass( 'notice-success', response.success )
					.toggleClass( 'notice-error', ! response.success );

				if ( response.data && response.data.message ) {
					this.$feedback.html( '<p>' + response.data.message + '</p>' ).show();
				}

				if ( response.success ) {
					if ( isSetupWizard() ) {
						window.location.hash = 'create-iam-user';
					} else {
						wposes.reloadUpdated();
					}
				}
			}.bind( this ) )
			.always( function() {
				this.$spinner.removeClass( 'is-active' ).hide();
			}.bind( this ) )
		;
	};

	/**
	 * Check the licence and return licence info from deliciousbrains.com
	 *
	 * @param licence
	 */
	function checkLicence( licence ) {
		var $support = $main.find( '.support-content' );
		var api = new LicenceApi();

		$( '.wposes-licence-notice' ).remove();

		api.check( {
			licence: licence
		} )
			.done( function( data ) {
				if ( ! _.isEmpty( data.dbrains_api_down ) ) {
					$support.html( data.dbrains_api_down + data.message );
				} else if ( _.isArray( data.htmlErrors ) && data.htmlErrors.length ) {
					$support.html( data.htmlErrors.join( '' ) );
				} else {
					$support.html( data.message );
				}

				if ( ! _.isEmpty( data.pro_error ) && 0 === $( '.wposes-license-notice' ).length ) {
					$( '.wposes-main #wposes-settings-sub-nav' ).after( data.pro_error );
				}
			} )
		;
	}

	/* Check the licence on the first load of the Support tab */
	initSupportTab = _.once( checkLicence );

	/**
	 * Convert form inputs to single level object
	 *
	 * @param {object} form
	 *
	 * @returns {object}
	 */
	function formInputsToObject( form ) {
		var formInputs = $( form ).serializeArray();
		var inputsObject = {};

		$.each( formInputs, function( index, input ) {
			inputsObject[ input.name ] = input.value;
		} );

		return inputsObject;
	}

	/**
	 * Edit the hash of the check licence URL so we reload to the correct tab
	 *
	 * @param hash
	 */
	function editcheckLicenseURL( hash ) {
		if ( 'support' !== hash && $( '.wposes-check-again' ).length ) {
			var checkLicenseURL = $( '.wposes-check-again' ).attr( 'href' );

			if ( wposes.tabs.defaultTab === hash ) {
				hash = '';
			}

			if ( '' !== hash ) {
				hash = '#' + hash;
			}

			var index = checkLicenseURL.indexOf( '#' );
			if ( 0 === index ) {
				index = checkLicenseURL.length;
			}

			checkLicenseURL = checkLicenseURL.substr( 0, index ) + hash;

			$( '.wposes-check-again' ).attr( 'href', checkLicenseURL );
		}
	}

	/**
	 * Get the hash of the URL
	 *
	 * @returns {string}
	 */
	function getURLHash() {
		var hash = '';
		if ( window.location.hash ) {
			hash = window.location.hash.substring( 1 );
		}

		hash = wposes.tabs.sanitizeHash( hash );

		return hash;
	}

	$( document ).on( 'wposes.tabRendered', function( event, hash ) {
		if ( 'support' === hash && '1' === wposes.strings.has_licence ) {
			initSupportTab();
		} else if ( 'licence' === hash ) {
			$( '#wposes_license_notice_no_licence' ).hide();
			$( '.wposes-licence-input' ).focus();
		} else if ( 'reports' === hash ) {
			$( '#wposes_license_notice_no_licence' ).hide();
		} else {
			$( '#wposes_license_notice_no_licence' ).show();
		}

		editcheckLicenseURL( hash );
	} );

	$main.on( 'click', '[data-wposes-licence-action]', function( event ) {
		var action = $( this ).data( 'wposesLicenceAction' );
		var api = new LicenceApi();

		event.preventDefault();

		if ( 'function' === typeof api[action] ) {
			api[action]();
		}
	} );

	$( document ).ready( function() {
		var hash = getURLHash();
		editcheckLicenseURL( hash );

		var $settingsForm = $( '#tab-' + wposes.tabs.defaultTab + ' .wposes-main-settings form' );

		savedSettings = formInputsToObject( $settingsForm );

		$body.on( 'click', '.reactivate-licence', function( e ) {
			e.preventDefault();

			var $processing = $( '<div/>', { id: 'processing-licence' } ).html( wposes.strings.attempting_to_activate_licence );
			$processing.append( '<img src="' + wposes.spinnerUrl + '" alt="" class="check-licence-ajax-spinner general-spinner" />' );
			$( '.wposes-invalid-licence' ).hide().after( $processing );

			$.ajax( {
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				cache: false,
				data: {
					action: 'wposes_reactivate_licence',
					nonce: wposes.nonces.reactivate_licence
				},
				error: function( jqXHR, textStatus, errorThrown ) {
					$processing.remove();
					$( '.wposes-invalid-licence' ).show().html( wposes.strings.activate_licence_problem );
					$( '.wposes-invalid-licence' ).append( '<br /><br />' + wposes.strings.status + ': ' + jqXHR.status + ' ' + jqXHR.statusText + '<br /><br />' + wposes.strings.response + '<br />' + jqXHR.responseText );
				},
				success: function( data ) {
					$processing.remove();

					if ( 'undefined' !== typeof data.wposes_error && 1 === data.wposes_error ) {
						$( '.wposes-invalid-licence' ).html( data.body ).show();
						return;
					}

					if ( 'undefined' !== typeof data.dbrains_api_down && 1 === data.dbrains_api_down ) {
						$( '.wposes-invalid-licence' ).html( wposes.strings.temporarily_activated_licence );
						$( '.wposes-invalid-licence' ).append( data.body ).show();
						return;
					}

					$( '.wposes-invalid-licence' ).empty().html( wposes.strings.licence_reactivated );
					$( '.wposes-invalid-licence' ).addClass( 'success notification-message success-notice' ).show();
					location.reload();
				}
			} );

		} );

		// Show support tab when 'support request' link clicked within compatibility notices
		$body.on( 'click', '.support-tab-link', function( e ) {
			wposes.tabs.toggle( 'support' );
		} );

		$( '#tab-start .wposes-wizard-next-btn' ).click( function( e ) {
			e.preventDefault();

			var licence_input = $( '.wposes-licence-input' );

			if ( ! licence_input.length || licence_input.prop( 'disabled' ) ) {
				window.location.hash = 'create-iam-user';
			} else {
				var api = new LicenceApi();
				api['activate']();
			}
		} );

	} );
})( jQuery, _ );
