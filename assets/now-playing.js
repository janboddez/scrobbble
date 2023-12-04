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

	let previous = '';

	const render = ( response ) => {
		if ( ! nowPlaying ) {
			return;
		}

		if ( JSON.stringify( response ) === previous  ) {
			return;
		}

		previous = JSON.stringify( response );

		let current = '';
		let cover   = '';

		if ( response.title ) {
			const artist = response.artist ?? '';
			const album  = response.album ?? '';

			current += response.title + ( '' !== artist ? ' – ' + response.artist : '' ) + ( '' !== album ? ' <span class="screen-reader-text">(' + response.album + ')</span>' : '' );
		}

		if ( '' !== current ) {
			const aboutUrl = nowPlaying.dataset?.url ?? '';
			let heading    = '<span>' + ( nowPlaying.dataset?.title ?? window.wp.i18n.__( 'Now Playing', 'scrobbble' ) ) + '</span>';

			if ( aboutUrl ) {
				heading += ' ' + window.wp.i18n.sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer"><abbr title="%s">[?]</abbr></a>', aboutUrl, window.wp.i18n.__( 'What is this?', 'scrobbble' ) );
			}

			if ( response.cover ) {
				cover += '<img src="' + response.cover + '" width="60" height="60" alt="" />';
			}

			nowPlaying.style.display = 'none';
			nowPlaying.innerHTML     = '<div><small>' + heading + '</small><span><span>' + current + '</span></span></div>' + cover;
			nowPlaying.style.display = 'flex';
		} else {
			nowPlaying.style.display = 'none';
			nowPlaying.innerHTML     = '';
		}
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
