document.addEventListener( 'DOMContentLoaded', function() {
	const nowPlaying = document.querySelector( '.wp-block-scrobbble-now-playing' );

	const updateNowPlaying = () => {
		// Like a time-out.
		const controller = new AbortController();
		const timeoutId  = setTimeout( () => {
			controller.abort();
		}, 6000 );

		window.wp.apiFetch( {
			path: '/scrobbble/v1/nowplaying',
			signal: controller.signal, // That time-out thingy.
		} ).then( function( response ) {
			clearTimeout( timeoutId );
			render( response );
		} ).catch( function( error ) {
			// The request timed out or otherwise failed. Leave as is.
		} );
	};

	const render = ( response ) => {
		if ( ! nowPlaying ) {
			return;
		}

		let output  = '';
		let current = '';

		if ( response.title ) {
			artist   = response.artist ?? '';
			album    = response.album ?? '';
			current += response.title + ( '' !== artist ? ' – ' + response.artist : '' ) + ( '' !== album ? ' <span class="screen-reader-text">(' + response.album + ')</span>' : '' );
		}

		if ( '' !== current ) {
			// @todo: Make this generic!
			const aboutUrl = nowPlaying.dataset?.url ?? '';
			let heading    = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" style="position:relative;top:0.25em;"><g style="fill:none;fill-rule:evenodd;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round"><path d="M3.5 5.5v4M5.5 3.5v9M7.5 6.5v2M9.5 4.5v6.814M11.5 1.5v12"/></g></svg>';
			heading       += ' <span>' + ( nowPlaying.dataset?.title ?? window.wp.i18n.__( 'Now Playing', 'scrobbble' ) ) + '</span>';

			if ( aboutUrl ) {
				heading += ' ' + window.wp.i18n.sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer"><abbr title="%s">[?]</abbr></a>', aboutUrl, window.wp.i18n.__( 'What is this?', 'scrobbble' ) );
			}

			output = '<div><small>' + heading + '</small><span>' + current + '</span></div>';
		} else {
			output = '';
		}

		nowPlaying.innerHTML = output;
	};

	let lastReload = parseInt( Date.now() / 1000 );
	const setTimer = () => {
		return setInterval( () => {
			if ( parseInt( Date.now() / 1000 ) - lastReload > 30 ) {
				// The data was last refreshed over 30 seconds ago.
				lastReload = parseInt( Date.now() / 1000 );
				updateNowPlaying();
			}
		}, 5000 ); // Run every 5 seconds.
	};

	updateNowPlaying();

	let intervalId = setTimer();
	window.addEventListener( 'blur', () => {
		clearInterval( intervalId );
	} );
	window.addEventListener( 'focus', () => {
		intervalId = setTimer();
	} );
} );
