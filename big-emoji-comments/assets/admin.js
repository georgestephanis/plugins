document.addEventListener( 'DOMContentLoaded', function() {
	var tabs = document.querySelectorAll( '.nav-tab' );
	var tabContents = document.querySelectorAll( '.tab-content' );

	tabs.forEach( function( tab ) {
		tab.addEventListener( 'click', function( e ) {
			e.preventDefault();
			var target = tab.getAttribute( 'href' );

			tabs.forEach( function( t ) {
				t.classList.remove( 'nav-tab-active' );
			} );
			tabContents.forEach( function( content ) {
				content.style.display = 'none';
			} );

			tab.classList.add( 'nav-tab-active' );
			document.querySelector( target ).style.display = 'block';
		} );
	} );

	// Search logic
	var searchForm = document.getElementById( 'big-emoji-search-form' );
	var searchInput = document.getElementById( 'big-emoji-search-input' );
	var resultsGrid = document.getElementById( 'big-emoji-search-results' );

	if ( searchForm ) {
		searchForm.addEventListener( 'submit', function( e ) {
			e.preventDefault();
			var query = searchInput.value.trim();
			if ( ! query ) {
				return;
			}

			resultsGrid.innerHTML = '<div class="spinner-loading">Searching Slackmojis...</div>';

			var formData = new FormData();
			formData.append( 'action', 'big_emoji_search' );
			formData.append( 'query', query );
			formData.append( 'nonce', bigEmojiAdminSettings.nonce );

			fetch( bigEmojiAdminSettings.ajaxUrl, {
				method: 'POST',
				body: formData
			} )
			.then( function( response ) {
				return response.json();
			} )
			.then( function( data ) {
				if ( ! data.success ) {
					resultsGrid.innerHTML = '<div class="error-msg">' + ( data.data || 'Failed to search.' ) + '</div>';
					return;
				}

				var emojis = data.data;
				if ( ! emojis || emojis.length === 0 ) {
					resultsGrid.innerHTML = '<div>No emojis found matching that search.</div>';
					return;
				}

				resultsGrid.innerHTML = '';
				emojis.forEach( function( emoji ) {
					var item = document.createElement( 'div' );
					item.className = 'emoji-card';

					var img = document.createElement( 'img' );
					img.src = emoji.image_url;
					img.alt = emoji.name;
					img.className = 'emoji-img';

					var name = document.createElement( 'div' );
					name.className = 'emoji-name';
					name.textContent = ':' + emoji.name + ':';

					var btn = document.createElement( 'button' );
					btn.className = 'button button-secondary import-btn';
					btn.textContent = 'Import';

					// Check if already imported
					if ( bigEmojiAdminSettings.imported.indexOf( emoji.name ) !== -1 ) {
						btn.textContent = 'Imported';
						btn.setAttribute( 'disabled', 'disabled' );
					}

					btn.addEventListener( 'click', function() {
						btn.setAttribute( 'disabled', 'disabled' );
						btn.textContent = 'Importing...';

						var importData = new FormData();
						importData.append( 'action', 'big_emoji_import' );
						importData.append( 'name', emoji.name );
						importData.append( 'url', emoji.image_url );
						importData.append( 'nonce', bigEmojiAdminSettings.nonce );

						fetch( bigEmojiAdminSettings.ajaxUrl, {
							method: 'POST',
							body: importData
						} )
						.then( function( res ) {
							return res.json();
						} )
						.then( function( resData ) {
							if ( resData.success ) {
								btn.textContent = 'Imported';
								btn.className = 'button button-secondary disabled';
								bigEmojiAdminSettings.imported.push( emoji.name );
								// Reload current list if active
								reloadLocalEmojis();
							} else {
								btn.removeAttribute( 'disabled' );
								btn.textContent = 'Import';
								alert( resData.data || 'Failed to import.' );
							}
						} )
						.catch( function( err ) {
							btn.removeAttribute( 'disabled' );
							btn.textContent = 'Import';
							console.error( err );
						} );
					} );

					item.appendChild( img );
					item.appendChild( name );
					item.appendChild( btn );
					resultsGrid.appendChild( item );
				} );
			} )
			.catch( function( err ) {
				resultsGrid.innerHTML = '<div class="error-msg">An error occurred while searching.</div>';
				console.error( err );
			} );
		} );
	}

	// Delete logic
	var localGrid = document.getElementById( 'big-emoji-local-grid' );

	function attachDeleteListeners() {
		if ( ! localGrid ) {
			return;
		}

		var deleteButtons = localGrid.querySelectorAll( '.delete-btn' );
		deleteButtons.forEach( function( btn ) {
			btn.addEventListener( 'click', function() {
				var name = btn.getAttribute( 'data-name' );
				if ( ! name || ! confirm( 'Are you sure you want to delete :' + name + ':?' ) ) {
					return;
				}

				btn.setAttribute( 'disabled', 'disabled' );
				btn.textContent = 'Deleting...';

				var delData = new FormData();
				delData.append( 'action', 'big_emoji_delete' );
				delData.append( 'name', name );
				delData.append( 'nonce', bigEmojiAdminSettings.nonce );

				fetch( bigEmojiAdminSettings.ajaxUrl, {
					method: 'POST',
					body: delData
				} )
				.then( function( res ) {
					return res.json();
				} )
				.then( function( resData ) {
					if ( resData.success ) {
						var idx = bigEmojiAdminSettings.imported.indexOf( name );
						if ( idx !== -1 ) {
							bigEmojiAdminSettings.imported.splice( idx, 1 );
						}
						reloadLocalEmojis();
					} else {
						btn.removeAttribute( 'disabled' );
						btn.textContent = 'Delete';
						alert( resData.data || 'Failed to delete.' );
					}
				} )
				.catch( function( err ) {
					btn.removeAttribute( 'disabled' );
					btn.textContent = 'Delete';
					console.error( err );
				} );
			} );
		} );
	}

	function reloadLocalEmojis() {
		if ( ! localGrid ) {
			return;
		}

		var getData = new FormData();
		getData.append( 'action', 'big_emoji_get_local' );
		getData.append( 'nonce', bigEmojiAdminSettings.nonce );

		fetch( bigEmojiAdminSettings.ajaxUrl, {
			method: 'POST',
			body: getData
		} )
		.then( function( res ) {
			return res.json();
		} )
		.then( function( resData ) {
			if ( resData.success ) {
				var html = '';
				var emojis = resData.data;

				if ( Object.keys( emojis ).length === 0 ) {
					html = '<p>No custom emojis imported yet. Go to the "Search Slackmojis" tab to find and add some!</p>';
				} else {
					Object.keys( emojis ).forEach( function( key ) {
						var emoji = emojis[ key ];
						html += '<div class="emoji-card">';
						html += '  <img src="' + emoji.url + '" class="emoji-img" alt="' + emoji.name + '" />';
						html += '  <div class="emoji-name">:' + emoji.name + ':</div>';
						html += '  <button class="button button-link-delete delete-btn" data-name="' + emoji.name + '">Delete</button>';
						html += '</div>';
					} );
				}

				localGrid.innerHTML = html;
				attachDeleteListeners();
			}
		} );
	}

	attachDeleteListeners();
} );
