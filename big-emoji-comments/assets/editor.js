( function( blocks, element ) {
	var el = element.createElement;

	blocks.registerBlockType( 'big-emoji-comments/reactions', {
		title: 'Emoji Reactions',
		icon: 'smiley',
		category: 'widgets',
		description: 'Allows visitors to react with emojis. Clicking an emoji submits a comment.',
		edit: function() {
			return el(
				'div',
				{ className: 'wp-block-big-emoji-reactions-editor' },
				el( 'span', { className: 'editor-placeholder-icon' }, '👍 ❤️ 😄 😮 😢 😡 🔥 ⭐' ),
				el( 'span', { className: 'editor-placeholder-text' }, ' Emoji Reactions Block (Counts and buttons will render on the frontend)' )
			);
		},
		save: function() {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element );
