document.addEventListener( 'DOMContentLoaded', function() {
	var blocks = document.querySelectorAll( '.big-emoji-reactions' );

	blocks.forEach( function( block ) {
		var postId = block.getAttribute( 'data-post-id' );
		if ( ! postId ) {
			return;
		}

		var storageKey = 'big_emoji_reactions_' + postId;
		var clickedEmojis = [];

		try {
			var stored = localStorage.getItem( storageKey );
			if ( stored ) {
				clickedEmojis = JSON.parse( stored );
			}
		} catch ( e ) {
			// Fail silently if localStorage is disabled or full.
		}

		// Highlight already clicked buttons
		var buttons = block.querySelectorAll( '.big-emoji-reaction-button' );
		buttons.forEach( function( button ) {
			var emoji = button.getAttribute( 'data-emoji' );
			if ( clickedEmojis.indexOf( emoji ) !== -1 ) {
				button.classList.add( 'is-active' );
				button.setAttribute( 'disabled', 'disabled' );
			}

			button.addEventListener( 'click', function() {
				if ( button.classList.contains( 'is-active' ) || button.classList.contains( 'is-loading' ) ) {
					return;
				}

				button.classList.add( 'is-loading' );

				fetch( bigEmojiCommentsSettings.restUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': bigEmojiCommentsSettings.nonce
					},
					body: JSON.stringify( {
						post_id: parseInt( postId, 10 ),
						emoji: emoji
					} )
				} )
				.then( function( response ) {
					if ( ! response.ok ) {
						throw new Error( 'Network response was not ok' );
					}
					return response.json();
				} )
				.then( function( data ) {
					button.classList.remove( 'is-loading' );
					button.classList.add( 'is-active' );
					button.setAttribute( 'disabled', 'disabled' );

					// Save reaction to localStorage
					clickedEmojis.push( emoji );
					try {
						localStorage.setItem( storageKey, JSON.stringify( clickedEmojis ) );
					} catch ( e ) {}

					// Update counts
					if ( data && typeof data === 'object' ) {
						Object.keys( data ).forEach( function( key ) {
							var countBtn = block.querySelector( '.big-emoji-reaction-button[data-emoji="' + key + '"]' );
							if ( countBtn ) {
								var countSpan = countBtn.querySelector( '.emoji-count' );
								if ( countSpan ) {
									countSpan.textContent = data[ key ];
								}
							}
						} );
					}
				} )
				.catch( function( error ) {
					button.classList.remove( 'is-loading' );
					console.error( 'Error submitting reaction:', error );
				} );
			} );
		} );
	} );
} );
