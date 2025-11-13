(function( $ ) {
	'use strict';

	function toBool( value ) {
		return value === true || value === 'true' || value === 1 || value === '1';
	}

function setStatus( $card, message, variant ) {
		var $status = $card.find( '[data-codfaa-status]' );
		var $statusCard = $card.find( '[data-codfaa-status-card]' );

		if ( ! $status.length || ! $statusCard.length ) {
			return;
		}

		var normalizedVariant = ( variant || 'info' ).toLowerCase();
		if ( ! /^[a-z0-9_-]+$/.test( normalizedVariant ) ) {
			normalizedVariant = 'info';
		}
		var baseClass = 'codfaa-bid-stat codfaa-bid-stat--state codfaa-bid-stat--' + normalizedVariant;
		$statusCard.attr( 'class', baseClass );

		if ( message !== undefined ) {
			$status.text( message );
			if ( message ) {
				$statusCard.show();
			} else {
				$statusCard.hide();
			}
		}
}

function escapeHtml( value ) {
	return $( '<div>' ).text( value || '' ).html();
}

function toPlainText( value ) {
	return $( '<div>' ).html( value || '' ).text();
}

function getCurrentUserId() {
	return parseInt( CodfaaAuctionRegistration.currentUserId, 10 ) || 0;
}

function encodeSummaryValue( value ) {
	if ( ! value ) {
		return '';
	}

	try {
		return window.btoa( encodeURIComponent( value ).replace( /%([0-9A-F]{2})/g, function( match, hex ) {
			return String.fromCharCode( parseInt( hex, 16 ) );
		} ) );
	} catch ( error ) {
		try {
			return window.btoa( value );
		} catch ( e ) {
			return '';
		}
	}
}

function decodeSummaryValue( encoded ) {
	if ( ! encoded ) {
		return '';
	}

	try {
		var percentEncoded = window.atob( encoded ).split( '' ).map( function( char ) {
			return '%' + ( '00' + char.charCodeAt( 0 ).toString( 16 ) ).slice( -2 );
		} ).join( '' );
		return decodeURIComponent( percentEncoded );
	} catch ( error ) {
		try {
			return window.atob( encoded );
		} catch ( e ) {
			return '';
		}
	}
}

function getStoredClaimSummary( $card ) {
	var $summary = $card.find( '[data-codfaa-winner-summary]' );

	if ( ! $summary.length ) {
		return '';
	}

	var encoded = $summary.attr( 'data-claim-summary' ) || '';
	return decodeSummaryValue( encoded );
}

function storeClaimSummaryAttr( $card, summary ) {
	var $summary = $card.find( '[data-codfaa-winner-summary]' );

	if ( ! $summary.length ) {
		return;
	}

	if ( ! summary ) {
		$summary.removeAttr( 'data-claim-summary' );
		return;
	}

	var encoded = encodeSummaryValue( summary );

	if ( encoded ) {
		$summary.attr( 'data-claim-summary', encoded );
	}
}

function showClaimSummaryFallback( $card, state ) {
	if ( state.claimSummary ) {
		updateWinnerSummary( $card, state.claimSummary );
		return;
	}

	var stored = getStoredClaimSummary( $card );

	if ( stored ) {
		state.claimSummary = stored;
		updateWinnerSummary( $card, stored );
	}
}

function isCurrentUserLastBidder( state ) {
	if ( ! state ) {
		return false;
	}

	var currentId = getCurrentUserId();
	var lastBid = parseInt( state.lastBidUser, 10 ) || 0;

	return !! ( currentId && lastBid && currentId === lastBid );
}

function updateBidSummary( $card, payload ) {
		if ( ! payload ) {
			return;
		}

		var bidCount = payload.userBidCount;
		var totalDisplay = payload.userTotalDisplay;
		var totalMinor = payload.userTotalMinor;

		if ( bidCount === undefined && payload.bidCount !== undefined ) {
			bidCount = payload.bidCount;
		}

		if ( totalDisplay === undefined && payload.totalDisplay !== undefined ) {
			totalDisplay = payload.totalDisplay;
		}

		if ( totalMinor === undefined && payload.totalMinor !== undefined ) {
			totalMinor = payload.totalMinor;
		}

		var $count = $card.find( '[data-codfaa-bid-count]' );

		if ( $count.length && bidCount !== undefined ) {
			$count.text( bidCount );
		}

		var $total = $card.find( '[data-codfaa-bid-total]' );

		if ( $total.length ) {
			if ( totalDisplay !== undefined ) {
				$total.html( totalDisplay );
			}

			if ( totalMinor !== undefined ) {
				$total.attr( 'data-minor', totalMinor );
			}
		}
	}

function updateParticipants( $card, payload ) {
		if ( ! payload ) {
			return;
		}

		var participants = payload.participants;
		var required = payload.required;
		var percent = payload.progressPercent;
		var label = payload.participantLabel;

		if ( participants !== undefined ) {
			$card.attr( 'data-participants', participants );
		}

		if ( required !== undefined ) {
			$card.attr( 'data-required', required );
		}

		if ( percent === undefined ) {
			participants = participants !== undefined ? participants : parseInt( $card.attr( 'data-participants' ), 10 ) || 0;
			required = required !== undefined ? required : parseInt( $card.attr( 'data-required' ), 10 ) || 0;
			percent = required > 0 ? Math.min( 100, ( participants / required ) * 100 ) : ( participants > 0 ? 100 : 0 );
		}

		var $bar = $card.find( '[data-codfaa-progress-bar]' );

		if ( $bar.length ) {
			$bar.css( 'width', Math.max( 0, Math.min( 100, percent ) ) + '%' );
		}

		var $label = $card.find( '[data-codfaa-progress-label]' );

		if ( $label.length && label ) {
			$label.text( label );
		}
	}

function toggleTimerVisibility( $card, shouldShow ) {
	var $timerWrapper = $card.find( '[data-codfaa-timer-wrapper]' );
	var $timerPending = $card.find( '[data-codfaa-timer-pending]' );

	if ( $timerWrapper.length ) {
		$timerWrapper.toggle( shouldShow );
	}

	if ( $timerPending.length ) {
		$timerPending.toggle( ! shouldShow );
	}
}

function updatePreliveTimer( $card, seconds, baseline ) {
	var $timer = $card.find( '[data-codfaa-prelive-timer]' );

	if ( $timer.length ) {
		$timer.text( formatSeconds( seconds ) );
	}

	var $bar = $card.find( '[data-codfaa-timer-progress]' );

	if ( $bar.length ) {
		var state = $card.data( 'codfaaState' ) || {};
		var total = baseline !== undefined ? baseline : ( state.preliveBaseline || parseInt( $card.data( 'prelivetotal' ), 10 ) || parseInt( $card.data( 'prelive' ), 10 ) || 0 );
		var percent = total > 0 ? ( seconds / total ) * 100 : 0;
		percent = Math.max( 0, Math.min( 100, percent ) );
		$bar.css( 'width', percent + '%' );
	}
}

function updateWinnerPill( $card, label ) {
	var $pill = $card.find( '[data-codfaa-winner-pill]' );

	if ( ! $pill.length ) {
		return;
	}

	if ( ! label ) {
		label = CodfaaAuctionRegistration.winnerUnknown || 'Winner: —';
	}

	if ( label && label.indexOf( 'Winner' ) === -1 && CodfaaAuctionRegistration.winnerLabel ) {
		label = CodfaaAuctionRegistration.winnerLabel.replace( '%s', label );
	}

	$pill.text( label );
}

function updateLockBanner( $card, state ) {
	var $lock = $card.find( '[data-codfaa-lock]' );
	var $bidCard = $card.find( '.codfaa-bid-card' );

	if ( ! $lock.length || ! $bidCard.length ) {
		return;
	}

	var shouldShow = ! state.ended && state.phase !== 'live';
	$bidCard.toggleClass( 'is-locked', shouldShow );
	$lock.attr( 'aria-hidden', shouldShow ? 'false' : 'true' );

	var $message = $lock.find( '[data-codfaa-lock-message]' );
	var $countdown = $lock.find( '[data-codfaa-lock-countdown]' );
	var $timer = $lock.find( '[data-codfaa-lock-timer]' );

	if ( ! shouldShow ) {
		if ( $countdown.length ) {
			$countdown.hide();
		}
		return;
	}

	var showCountdown = state.ready && state.preliveRemaining > 0;
	var message = CodfaaAuctionRegistration.lockedCopy || '';

	if ( showCountdown && CodfaaAuctionRegistration.readyLockCopy ) {
		message = CodfaaAuctionRegistration.readyLockCopy;
	} else if ( state.userRegistered && CodfaaAuctionRegistration.registeredLockCopy ) {
		message = CodfaaAuctionRegistration.registeredLockCopy;
	}

	if ( $message.length ) {
		$message.text( message );
	} else {
		$lock.text( message );
	}

	if ( $countdown.length ) {
		$countdown.toggle( showCountdown );
		if ( showCountdown && $timer.length ) {
			$timer.text( formatSeconds( state.preliveRemaining ) );
		}
	}
}

function togglePreliveVisibility( $card, state ) {
	var $wrap = $card.find( '[data-codfaa-prelive-wrapper]' );

	if ( ! $wrap.length ) {
		return;
	}

	var shouldShow = !! state.preliveRemaining && state.phase !== 'live' && ! state.ended;
	$wrap.toggle( shouldShow );
}

function setStageFlags( $stage, flags ) {
	if ( ! $stage.length ) {
		return;
	}

	var map = {
		active: 'is-active',
		complete: 'is-complete',
		locked: 'is-locked'
	};

	Object.keys( map ).forEach( function( key ) {
		var className = map[ key ];
		var shouldHave = flags && flags[ key ];
		$stage.toggleClass( className, !! shouldHave );
	} );
}

function syncStageStates( $card, state ) {
	var $registration = $card.find( '[data-codfaa-stage="registration"]' );
	var $countdown = $card.find( '[data-codfaa-stage="countdown"]' );
	var $live = $card.find( '[data-codfaa-stage="live"]' );
	var $ended = $card.find( '[data-codfaa-stage="ended"]' );

	setStageFlags( $registration, {
		active: ! state.userRegistered && ! state.ended,
		complete: state.userRegistered || state.ended,
		locked: false
	} );

	var countdownComplete = state.phase === 'live' || state.ended;
	setStageFlags( $countdown, {
		active: ! countdownComplete && state.ready && state.userRegistered && ! state.ended,
		complete: countdownComplete,
		locked: ! countdownComplete && ( ! state.userRegistered || state.ended )
	} );

	var liveActive = state.phase === 'live' && ! state.ended;
	setStageFlags( $live, {
		active: liveActive,
		complete: state.ended,
		locked: ! liveActive && ! state.ended
	} );

	setStageFlags( $ended, {
		active: state.ended,
		complete: state.ended,
		locked: ! state.ended
	} );
}

function toggleRegisterCard( $card, state ) {
	var $successNote = $card.find( '[data-codfaa-register-success]' );

	if ( $successNote.length ) {
		$successNote.toggle( !! state.userRegistered );
	}

	syncStageStates( $card, state );
}

function startEndPolling( $card, state ) {
	if ( state.endPollInterval || ! state.auctionId ) {
		return;
	}

	state.endPollInterval = window.setInterval( function() {
		if ( state.ended ) {
			clearEndPolling( state );
			return;
		}

		fetchStatus( $card, state );
	}, 1000 );
}

function clearEndPolling( state ) {
	if ( state.endPollInterval ) {
		window.clearInterval( state.endPollInterval );
		state.endPollInterval = null;
	}
}

function getProductModal( modalId ) {
	if ( ! modalId ) {
		return $();
	}

	return $( '[data-codfaa-modal=\"' + modalId + '\"]' );
}

function openProductModal( modalId ) {
	var $modal = getProductModal( modalId );

	if ( !$modal.length ) {
		return;
	}

	$modal.addClass( 'is-open' ).attr( 'aria-hidden', 'false' );
}

function closeProductModal( $modal ) {
	if ( !$modal || !$modal.length ) {
		return;
	}

	$modal.removeClass( 'is-open' ).attr( 'aria-hidden', 'true' );
}

function openTermsModal( $modal ) {
	if ( ! $modal.length ) {
		return;
	}

	$modal.addClass( 'is-open' ).attr( 'aria-hidden', 'false' );
}

function closeTermsModal( $modal ) {
	if ( ! $modal.length ) {
		return;
	}

	$modal.removeClass( 'is-open' ).attr( 'aria-hidden', 'true' );
}

	function formatSeconds( seconds ) {
		seconds = Math.max( 0, parseInt( seconds, 10 ) || 0 );
		var minutes = Math.floor( seconds / 60 );
		var remainder = seconds % 60;

		return ( minutes < 10 ? '0' + minutes : minutes ) + ':' + ( remainder < 10 ? '0' + remainder : remainder );
	}

		function updateTimerDisplay( $card, seconds ) {
		var $timer = $card.find( '[data-codfaa-timer]' );

		if ( $timer.length ) {
			$timer.text( formatSeconds( seconds ) );
		}

		$card.attr( 'data-remaining', seconds );

		var state = $card.data( 'codfaaState' ) || {};
		var $progress = $card.find( '[data-codfaa-live-progress]' );

		if ( $progress.length && state.timerSeconds > 0 ) {
			var percent = Math.max( 0, Math.min( 100, ( seconds / state.timerSeconds ) * 100 ) );
			$progress.css( 'width', percent + '%' );
		}

		var isCritical = state.timerSeconds > 0 && seconds > 0 && seconds <= Math.min( 5, state.timerSeconds );
		var $progressWrap = $card.find( '.codfaa-progress--live' );
		if ( $progressWrap.length ) {
			$progressWrap.toggleClass( 'is-critical', isCritical );
		}

		var $timerLabel = $card.find( '.codfaa-bid-card__timer-label' );
		if ( $timerLabel.length ) {
			$timerLabel.toggleClass( 'is-critical', isCritical );
		}

	}


	function toggleBidButton( $card, canBid ) {
		var $button = $card.find( '.codfaa-place-bid' );

		if ( ! $button.length ) {
			return;
		}

		if ( canBid ) {
			$button.addClass( 'is-active' ).prop( 'disabled', false ).attr( 'aria-disabled', 'false' );
		} else {
			$button.removeClass( 'is-active' ).prop( 'disabled', true ).attr( 'aria-disabled', 'true' );
		}
	}

function setBidVisibility( $card, state ) {
	var $button = $card.find( '.codfaa-place-bid' );

	if ( ! $button.length ) {
		return;
	}

	var shouldShow = ! state.ended;
	$button.toggle( shouldShow );
}

	function updateRegistrationUi( $card, state ) {
		var $registerButton = $card.find( '.codfaa-register' );
		var $pendingNotice = $card.find( '[data-codfaa-registration-pending]' );
		var $consent = $card.find( '[data-codfaa-consent]' );
		var consentRequired = $consent.length > 0;
		var consentChecked = ! consentRequired || $consent.is( ':checked' );

		if ( consentRequired ) {
			if ( state.userRegistered || state.registrationPending ) {
				$consent.prop( 'checked', true ).prop( 'disabled', true );
				consentChecked = true;
			} else {
				$consent.prop( 'disabled', false );
			}
		}

		if ( $registerButton.length ) {
			if ( state.userRegistered || state.registrationPending ) {
				$registerButton.hide().attr( 'aria-hidden', 'true' ).prop( 'disabled', true ).attr( 'aria-disabled', 'true' );
			} else {
				var shouldDisable = consentRequired && ! consentChecked;
				$registerButton.show().attr( 'aria-hidden', 'false' ).prop( 'disabled', shouldDisable ).attr( 'aria-disabled', shouldDisable ? 'true' : 'false' );
			}
		}

		if ( $pendingNotice.length ) {
			if ( state.registrationPending && ! state.userRegistered ) {
				$pendingNotice.show();
			} else {
				$pendingNotice.hide();
			}
		}

		$card.attr( 'data-registration-pending', state.registrationPending ? 1 : 0 );
		$card.attr( 'data-user-registered', state.userRegistered ? 1 : 0 );

		var $consentHint = $card.find( '[data-codfaa-consent-hint]' );
		if ( $consentHint.length ) {
			if ( state.userRegistered || state.registrationPending || ! consentRequired ) {
				$consentHint.hide();
			} else {
				$consentHint.toggle( ! consentChecked );
			}
		}

	toggleRegisterCard( $card, state );
	}

function updateWinnerSummary( $card, payload ) {
	var $summary = $card.find( '[data-codfaa-winner-summary]' );

	if ( ! $summary.length ) {
		return;
	}

	var summary = '';
	var variant = '';

	if ( payload === undefined || payload === null ) {
		$summary.removeClass( 'is-win is-lost' ).empty().hide();
		return;
	}

	if ( typeof payload === 'object' ) {
		if ( payload.summary !== undefined ) {
			summary = payload.summary;
		} else if ( payload.winnerSummary !== undefined ) {
			summary = payload.winnerSummary;
		}
		variant = payload.variant || payload.status || '';
	} else {
		summary = payload;
	}

	$summary.removeClass( 'is-win is-lost' );

	if ( ! summary ) {
		$summary.empty().hide();
		return;
	}

	if ( variant ) {
		$summary.addClass( 'is-' + variant );
	}

	$summary.html( summary ).show();

}


function updateRecentBidders( $card, bidders ) {
	var $list = $card.find( '[data-codfaa-recent-bidders]' );

	if ( ! $list.length || bidders === undefined ) {
		return;
	}

	if ( ! Array.isArray( bidders ) || ! bidders.length ) {
		$list.html( '<li class=\"codfaa-recent__empty\">' + escapeHtml( CodfaaAuctionRegistration.noBidsLabel ) + '</li>' );
		return;
	}

	var items = bidders.map( function( bidder ) {
		var name = escapeHtml( bidder.name || '' );
		var timestamp = bidder.timestamp ? escapeHtml( bidder.timestamp ) : '';
		var timestampRaw = bidder.timestampRaw ? ' datetime=\"' + escapeHtml( bidder.timestampRaw ) + '\"' : '';
		var amount = bidder.totalDisplay ? escapeHtml( toPlainText( bidder.totalDisplay ) ) : '';
		var meta = '';

		if ( timestamp || amount ) {
			meta = '<span class=\"codfaa-recent__meta\">';
			if ( timestamp ) {
				meta += '<time' + timestampRaw + '>' + timestamp + '</time>';
			}
			if ( amount ) {
				meta += '<span class=\"codfaa-recent__amount\">' + amount + '</span>';
			}
			meta += '</span>';
		}

		return '<li><span class=\"codfaa-recent__name\">' + name + '</span>' + meta + '</li>';
	} ).join( '' );

	$list.html( items );
}


function toggleClaimButton( $card, state, payload ) {
	var $button = $card.find( '.codfaa-claim-prize' );

	if ( ! $button.length ) {
		return;
	}

	var shouldShow = payload && payload.ended && payload.userIsWinner && ! payload.winnerClaimed;

	if ( ! shouldShow && state ) {
		var currentUserId = parseInt( CodfaaAuctionRegistration.currentUserId, 10 ) || 0;
		var lastBidUser = parseInt( state.lastBidUser, 10 ) || 0;
		if ( currentUserId && lastBidUser && currentUserId === lastBidUser && state.remaining <= 0 && ! state.winnerClaimed ) {
			shouldShow = true;
		}
	}

	switch ( true ) {
		case shouldShow:
			var label = payload.claimLabel || $button.data( 'label' );
			$button.text( label );
			$button.addClass( 'is-visible' ).prop( 'disabled', false ).attr( 'aria-hidden', 'false' ).data( 'claimed', 0 );
			break;
		default:
			$button.removeClass( 'is-visible' ).prop( 'disabled', true ).attr( 'aria-hidden', 'true' ).data( 'claimed', 1 );
	}
}

function applyStatusPayload( $card, state, payload ) {
	if ( ! payload ) {
		return;
	}

	if ( payload.state ) {
		state.phase = payload.state;
		if ( state.phase === 'live' && state.awaitingWinnerConfirm ) {
			state.awaitingWinnerConfirm = false;
		}
	}

	if ( payload.ready !== undefined ) {
		state.ready = !! payload.ready;
	}

	if ( payload.preliveDuration !== undefined ) {
		state.preliveBaseline = Math.max( 0, parseInt( payload.preliveDuration, 10 ) || 0 );
	}

	if ( payload.preliveRemaining !== undefined ) {
		state.preliveRemaining = Math.max( 0, parseInt( payload.preliveRemaining, 10 ) || 0 );
		if ( ! state.preliveBaseline ) {
			state.preliveBaseline = state.preliveRemaining;
		}
		updatePreliveTimer( $card, state.preliveRemaining, state.preliveBaseline );
	}

	if ( payload.goLiveTimestamp !== undefined ) {
		state.goLiveAt = parseInt( payload.goLiveTimestamp, 10 ) || 0;
	}

	if ( payload.lastBidderDisplay !== undefined ) {
		state.lastBidderDisplay = payload.lastBidderDisplay || '';
		updateWinnerPill( $card, state.lastBidderDisplay );
	}

	if ( payload.lastBidUser !== undefined ) {
		state.lastBidUser = parseInt( payload.lastBidUser, 10 ) || 0;
		if ( state.awaitingWinnerConfirm && ! isCurrentUserLastBidder( state ) ) {
			state.awaitingWinnerConfirm = false;
		}
	}

	if ( payload.winnerPill !== undefined ) {
		updateWinnerPill( $card, payload.winnerPill );
	}

	if ( payload.participants !== undefined || payload.required !== undefined || payload.participantLabel ) {
		updateParticipants( $card, payload );
	}

	if ( payload.userBidCount !== undefined || payload.bidCount !== undefined ) {
		updateBidSummary( $card, payload );
		var $totalEl = $card.find( '[data-codfaa-bid-total]' );
		if ( $totalEl.length ) {
			state.userTotalMinor = parseInt( $totalEl.data( 'minor' ), 10 ) || 0;
		}
	}

	if ( payload.remaining !== undefined ) {
		state.remaining = Math.max( 0, parseInt( payload.remaining, 10 ) || 0 );
		updateTimerDisplay( $card, state.remaining );
	}

	if ( payload.timerSeconds !== undefined ) {
		state.timerSeconds = Math.max( 0, parseInt( payload.timerSeconds, 10 ) || 0 );
	}

	if ( payload.statusMessage ) {
		setStatus( $card, payload.statusMessage, payload.statusVariant || 'info' );
	}

	if ( payload.canBid !== undefined ) {
		state.canBid = !! payload.canBid;
		toggleBidButton( $card, state.canBid );
	}

	setBidVisibility( $card, state );

	if ( payload.userRegistered !== undefined ) {
		state.userRegistered = !! payload.userRegistered;
	}

	if ( payload.registrationPending !== undefined ) {
		state.registrationPending = !! payload.registrationPending;
	}

	if ( payload.userIsWinner !== undefined ) {
		state.userIsWinner = !! payload.userIsWinner;
		if ( ! state.userIsWinner ) {
			state.awaitingWinnerConfirm = false;
		}
	}

	if ( payload.winnerClaimed !== undefined ) {
		state.winnerClaimed = !! payload.winnerClaimed;
	}

	if ( payload.ended !== undefined ) {
		state.ended = !! payload.ended;
		if ( state.ended ) {
			state.phase = 'ended';
			state.remaining = 0;
			updateTimerDisplay( $card, state.remaining );
			clearEndPolling( state );
			state.awaitingWinnerConfirm = false;
		} elseif ( state.awaitingWinnerConfirm && payload.remaining !== undefined && payload.remaining > 0 ) {
			state.awaitingWinnerConfirm = false;
		}
	}

	if ( ! state.phase ) {
		state.phase = state.ended ? 'ended' : ( state.ready ? 'ready' : 'upcoming' );
	}

	if ( payload.claimSummary !== undefined ) {
		state.claimSummary = payload.claimSummary || '';
		storeClaimSummaryAttr( $card, state.claimSummary );
		if ( state.claimSummary && ( state.ended || state.awaitingWinnerConfirm || payload.ended ) ) {
			updateWinnerSummary( $card, state.claimSummary );
		}
	}

	if ( payload.winnerSummary !== undefined && ! state.claimSummary ) {
		updateWinnerSummary( $card, payload.winnerSummary );
	}

	if ( payload.recentBidders !== undefined ) {
		updateRecentBidders( $card, payload.recentBidders );
	}

	updateRegistrationUi( $card, state );

	state.timerActive = ( state.phase === 'live' ) && ! state.ended;
	toggleTimerVisibility( $card, state.phase === 'live' || state.ended );
	togglePreliveVisibility( $card, state );
	updateLockBanner( $card, state );

	if ( payload.userIsWinner !== undefined || payload.ended !== undefined || payload.winnerClaimed !== undefined ) {
		toggleClaimButton( $card, state, {
			ended: state.ended,
			userIsWinner: state.userIsWinner,
			winnerClaimed: state.winnerClaimed,
			claimLabel: payload.claimLabel
		} );
	}

	if ( payload.claimLabel !== undefined ) {
		$card.find( '.codfaa-claim-prize' ).data( 'label', payload.claimLabel );
	}

	if ( ! state.lastBidderDisplay && $card.data( 'lastBidDisplay' ) ) {
		updateWinnerPill( $card, $card.data( 'lastBidDisplay' ) );
	}

	$card.data( 'codfaaState', state );
}


	function fetchStatus( $card, state ) {
		$.ajax( {
			type: 'POST',
			url: CodfaaAuctionRegistration.ajaxUrl,
			data: {
				action: 'codfaa_auction_status',
				nonce: CodfaaAuctionRegistration.nonce,
				auction_id: state.auctionId
			}
		} ).done( function( response ) {
			if ( response && response.success && response.data ) {
				applyStatusPayload( $card, state, response.data );
			}
		} );
	}

	function initAuctionCard( $card ) {
	var $bidTotalEl = $card.find( '[data-codfaa-bid-total]' );
	var initialUserTotalMinor = $bidTotalEl.length ? ( parseInt( $bidTotalEl.data( 'minor' ), 10 ) || 0 ) : 0;
	var initialClaimSummary = getStoredClaimSummary( $card );

	var state = {
		auctionId: parseInt( $card.data( 'auction' ), 10 ) || 0,
		timerSeconds: parseInt( $card.data( 'timer' ), 10 ) || 0,
		remaining: parseInt( $card.data( 'remaining' ), 10 ) || 0,
		canBid: toBool( $card.data( 'canBid' ) ),
		ended: toBool( $card.data( 'ended' ) ),
		ready: toBool( $card.data( 'ready' ) ),
		phase: $card.data( 'state' ) || '',
		userRegistered: toBool( $card.data( 'userRegistered' ) ),
		registrationPending: toBool( $card.data( 'registrationPending' ) ),
		userIsWinner: toBool( $card.data( 'userWinner' ) ),
		winnerClaimed: toBool( $card.data( 'winnerClaimed' ) ),
		preliveRemaining: parseInt( $card.data( 'prelive' ), 10 ) || 0,
		preliveBaseline: parseInt( $card.data( 'prelivetotal' ), 10 ) || 0,
		goLiveAt: parseInt( $card.data( 'goLive' ), 10 ) || 0,
	lastBidderDisplay: $card.data( 'lastBidDisplay' ) || '',
	lastBidUser: parseInt( $card.data( 'lastBidUser' ), 10 ) || 0,
		userTotalMinor: initialUserTotalMinor,
		claimSummary: initialClaimSummary,
		awaitingWinnerConfirm: false,
		endPollInterval: null
	};

	if ( ! state.preliveBaseline ) {
		state.preliveBaseline = state.preliveRemaining || 0;
	}

	if ( ! state.phase ) {
		state.phase = state.ended ? 'ended' : 'upcoming';
	}

	state.timerActive = ( state.phase === 'live' ) && ! state.ended;

	updateWinnerPill( $card, state.lastBidderDisplay );

		updateParticipants( $card, {
			participants: parseInt( $card.data( 'participants' ), 10 ) || 0,
			required: parseInt( $card.data( 'required' ), 10 ) || 0,
			participantLabel: $card.find( '[data-codfaa-progress-label]' ).text(),
			progressPercent: parseFloat( $card.data( 'progress' ) ) || 0
		} );

		updateBidSummary( $card, {
			userBidCount: parseInt( $card.find( '[data-codfaa-bid-count]' ).text(), 10 ) || 0,
			userTotalDisplay: $card.find( '[data-codfaa-bid-total]' ).html(),
			userTotalMinor: parseInt( $card.find( '[data-codfaa-bid-total]' ).data( 'minor' ), 10 ) || 0
		} );

	updateTimerDisplay( $card, state.remaining );
	toggleBidButton( $card, state.canBid );
	setBidVisibility( $card, state );
	toggleTimerVisibility( $card, state.phase === 'live' || state.ended );
	updatePreliveTimer( $card, state.preliveRemaining, state.preliveBaseline );
	togglePreliveVisibility( $card, state );
	updateLockBanner( $card, state );
	updateRegistrationUi( $card, state );

		var initialStatus = $card.data( 'initialStatus' );
		var statusVariant = $card.data( 'statusVariant' );

		if ( initialStatus ) {
			setStatus( $card, initialStatus, statusVariant );
		}

	var initialSummary = $card.find( '[data-codfaa-winner-summary]' ).html() || '';
	updateWinnerSummary( $card, initialSummary );

	if ( ! state.claimSummary && state.userIsWinner && initialSummary ) {
		state.claimSummary = initialSummary;
	}
	toggleClaimButton( $card, state, {
		ended: state.ended,
		userIsWinner: state.userIsWinner,
		winnerClaimed: state.winnerClaimed,
		claimLabel: $card.find( '.codfaa-claim-prize' ).data( 'label' )
	} );

	state.tickInterval = window.setInterval( function() {
		if ( state.timerActive && state.remaining > 0 ) {
			state.remaining -= 1;
			updateTimerDisplay( $card, state.remaining );
		}

		if ( state.timerActive && state.remaining <= 0 ) {
			state.timerActive = false;
			state.remaining = 0;

			if ( isCurrentUserLastBidder( state ) ) {
				state.awaitingWinnerConfirm = true;
				showClaimSummaryFallback( $card, state );
				toggleClaimButton( $card, state, {
					ended: true,
					userIsWinner: true,
					winnerClaimed: state.winnerClaimed,
					claimLabel: $card.find( '.codfaa-claim-prize' ).data( 'label' )
				} );
			}

			fetchStatus( $card, state );
			startEndPolling( $card, state );
		}

		if ( state.preliveRemaining > 0 && state.phase !== 'live' && ! state.ended ) {
			state.preliveRemaining -= 1;
			updatePreliveTimer( $card, state.preliveRemaining, state.preliveBaseline );
			updateLockBanner( $card, state );

			if ( state.preliveRemaining <= 0 ) {
				fetchStatus( $card, state );
			}
		}
	}, 1000 );

		var pollInterval = parseInt( CodfaaAuctionRegistration.statusInterval, 10 ) || 5000;

		if ( state.auctionId && pollInterval > 0 ) {
			state.pollInterval = window.setInterval( function() {
				fetchStatus( $card, state );
			}, pollInterval );

			fetchStatus( $card, state );
		}

	$card.data( 'codfaaState', state );
}

	function handleAjaxError( $button, response ) {
		var data = response && response.data ? response.data : {};
		var message = data.message || CodfaaAuctionRegistration.genericError || 'Something went wrong. Please try again.';

		setStatus( $button.closest( '.codfaa-auction-card' ), message, 'warning' );
		$button.prop( 'disabled', false ).removeClass( 'is-loading' ).data( 'processing', false );
	}

	$( document ).on( 'click', '[data-codfaa-terms-open]', function( event ) {
	event.preventDefault();
	event.stopPropagation();

	var $card = $( this ).closest( '.codfaa-auction-card' );
	var $modal = $card.find( '[data-codfaa-terms-modal]' );

	if ( ! $modal.length ) {
		return;
	}

	openTermsModal( $modal );
} );

$( document ).on( 'click', '[data-codfaa-terms-close]', function( event ) {
	event.preventDefault();
	var $modal = $( this ).closest( '[data-codfaa-terms-modal]' );
	closeTermsModal( $modal );
} );

$( document ).on( 'click', '.codfaa-terms-modal__overlay', function( event ) {
	var $modal = $( this ).closest( '[data-codfaa-terms-modal]' );
	closeTermsModal( $modal );
} );

$( document ).on( 'keydown', function( event ) {
	if ( 'Escape' !== event.key ) {
		return;
	}

	$( '.codfaa-terms-modal.is-open' ).each( function() {
		closeTermsModal( $( this ) );
	} );
} );

$( document ).on( 'change', '[data-codfaa-consent]', function() {
		var $checkbox = $( this );
		var $card = $checkbox.closest( '.codfaa-auction-card' );
		var $button = $card.find( '.codfaa-register' );

		if ( ! $button.length ) {
			return;
		}

	var disabled = !$checkbox.is( ':checked' );
	$button.attr( 'aria-disabled', disabled ? 'true' : 'false' ).prop( 'disabled', disabled );

	var $hint = $card.find( '[data-codfaa-consent-hint]' );
	if ( $hint.length && !$checkbox.is( ':disabled' ) ) {
		$hint.toggle( disabled );
	}
} );

	$( document ).on( 'click', '.codfaa-register', function( event ) {
		event.preventDefault();

	var $button = $( this );

	if ( $button.data( 'processing' ) || $button.is( '[aria-disabled="true"]' ) ) {
		return;
	}

	var $card = $button.closest( '.codfaa-auction-card' );
	var cardState = $card.data( 'codfaaState' ) || {};

	if ( cardState.registrationPending || cardState.userRegistered ) {
		return;
	}

	var $consent = $card.find( '[data-codfaa-consent]' );
	if ( $consent.length && !$consent.is( ':checked' ) ) {
		setStatus( $card, CodfaaAuctionRegistration.consentRequired || 'Please accept the Terms & Conditions before registering.', 'warning' );
		return;
	}

	$button.data( 'processing', true ).prop( 'disabled', true ).addClass( 'is-loading' );

	var sourceUrl = $button.data( 'return' ) || window.location.href;

		$.ajax( {
			type: 'POST',
			url: CodfaaAuctionRegistration.ajaxUrl,
			data: {
				action: 'codfaa_register',
				nonce: CodfaaAuctionRegistration.nonce,
				auction_id: $button.data( 'auction' ),
				source_url: sourceUrl
			}
	} ).done( function( response ) {
		var $card = $button.closest( '.codfaa-auction-card' );
	var cardState = $card.data( 'codfaaState' ) || {};

	if ( cardState.registrationPending || cardState.userRegistered ) {
		$button.prop( 'disabled', true );
		return;
	}
			if ( response && response.success && response.data ) {
				if ( response.data.redirect ) {
					window.location.href = response.data.redirect;
					return;
				}

				setStatus( $card, response.data.message || CodfaaAuctionRegistration.genericError, 'info' );
				$button.prop( 'disabled', true ).removeClass( 'is-loading' ).data( 'processing', false );

				var state = $card.data( 'codfaaState' );
				if ( state ) {
					fetchStatus( $card, state );
				}
				return;
			}

			handleAjaxError( $button, response );
		} ).fail( function( jqXHR ) {
			handleAjaxError( $button, jqXHR.responseJSON || {} );
		} );
	} );

	$( document ).on( 'click', '.codfaa-place-bid', function( event ) {
		event.preventDefault();

		var $button = $( this );

		if ( ! $button.hasClass( 'is-active' ) || $button.data( 'processing' ) ) {
			return;
		}

		var $card = $button.closest( '.codfaa-auction-card' );

		$button.data( 'processing', true ).prop( 'disabled', true ).addClass( 'is-loading' );

		$.ajax( {
			type: 'POST',
			url: CodfaaAuctionRegistration.ajaxUrl,
			data: {
				action: 'codfaa_place_bid',
				nonce: CodfaaAuctionRegistration.nonce,
				auction_id: $button.data( 'auction' )
			}
		} ).done( function( response ) {
			var state = $card.data( 'codfaaState' ) || {};

			if ( response && response.success && response.data ) {
				applyStatusPayload( $card, state, response.data );
				$button.removeClass( 'is-loading' ).data( 'processing', false ).prop( 'disabled', false );
				return;
			}

			handleAjaxError( $button, response );
		} ).fail( function( jqXHR ) {
			handleAjaxError( $button, jqXHR.responseJSON || {} );
		} );
	} );

	$( document ).on( 'click', '.codfaa-claim-prize', function( event ) {
		event.preventDefault();

		var $button = $( this );

		if ( ! $button.hasClass( 'is-visible' ) || $button.data( 'processing' ) ) {
			return;
		}

		var $card = $button.closest( '.codfaa-auction-card' );
		$button.data( 'processing', true ).addClass( 'is-loading' ).prop( 'disabled', true );

		$.ajax( {
			type: 'POST',
			url: CodfaaAuctionRegistration.ajaxUrl,
			data: {
				action: 'codfaa_claim_prize',
				nonce: CodfaaAuctionRegistration.nonce,
				auction_id: $button.data( 'auction' )
			}
		} ).done( function( response ) {
			var state = $card.data( 'codfaaState' ) || {};

			if ( response && response.success && response.data ) {
				if ( response.data.redirect ) {
					window.location.href = response.data.redirect;
					return;
				}

			state.winnerClaimed = true;
			$card.data( 'winnerClaimed', 1 );
			setStatus( $card, response.data.message || CodfaaAuctionRegistration.genericError, 'success' );
			toggleClaimButton( $card, state, {
					ended: true,
					userIsWinner: true,
					winnerClaimed: true
				} );
				$button.removeClass( 'is-loading' ).data( 'processing', false );
				fetchStatus( $card, state );
				return;
			}

			handleAjaxError( $button, response );
		} ).fail( function( jqXHR ) {
			handleAjaxError( $button, jqXHR.responseJSON || {} );
		} );
	} );

	$( document ).on( 'click', '[data-codfaa-modal-open]', function( event ) {
	event.preventDefault();
	var target = $( this ).attr( 'data-codfaa-modal-open' );
	openProductModal( target );
} );

$( document ).on( 'click', '[data-codfaa-modal-close]', function( event ) {
	event.preventDefault();
	var $modal = $( this ).closest( '[data-codfaa-modal]' );
	closeProductModal( $modal );
} );

$( document ).on( 'click', '.codfaa-product-modal__overlay', function( event ) {
	var $modal = $( this ).closest( '[data-codfaa-modal]' );
	closeProductModal( $modal );
} );

$( document ).on( 'keyup', function( event ) {
	if ( event.key !== 'Escape' && event.key !== 'Esc' ) {
		return;
	}

	$( '[data-codfaa-modal].is-open' ).each( function() {
		closeProductModal( $( this ) );
	} );
} );

$( function() {
		$( '.codfaa-auction-card' ).each( function() {
			initAuctionCard( $( this ) );
		} );
	} );
})( jQuery );
