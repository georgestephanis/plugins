import './portal-style.scss';

/**
 * Frontend Portal Script - Ndizi Project Management
 *
 * @param {Object} $ jQuery instance.
 */
( function ( $ ) {
	'use strict';

	$( document ).ready( function () {
		initProjectAccordion();
		initTaskCommentModal();
	} );

	/**
	 * Project Card Accordion
	 */
	function initProjectAccordion() {
		$( '.ndizi-project-card-header' ).on( 'click', function ( e ) {
			// Prevent toggle if clicking links or action buttons inside header (if any)
			if ( $( e.target ).closest( 'a, button' ).length ) {
				return;
			}

			const $card = $( this ).closest( '.ndizi-project-card' );
			const $content = $card.find( '.ndizi-project-card-content' );

			$card.toggleClass( 'ndizi-active-project' );
			$content.slideToggle( 300 );
		} );
	}

	/**
	 * Task Comment Overlay Modal
	 */
	function initTaskCommentModal() {
		const $modal = $( '#ndizi_task_comment_modal' );
		if ( ! $modal.length ) {
			return;
		}

		// Open Modal on comment click
		$( document ).on( 'click', '.ndizi-btn-comment-dialog', function ( e ) {
			e.preventDefault();
			const $btn = $( this );
			const taskId = $btn.data( 'post-id' );
			const title = $btn.data( 'title' );

			$( '#ndizi_modal_task_title' ).text( 'Discussion: ' + title );
			$( '#ndizi_modal_discussion_container' ).html(
				'<div class="no-items"><span class="spinner is-active" style="float:none; margin:0 auto 10px;"></span> Loading messages...</div>'
			);

			$modal.show();

			// Fetch discussion HTML via AJAX
			wp.ajax
				.post( 'ndizi_load_task_discussion', {
					task_id: taskId,
				} )
				.done( function ( response ) {
					if ( response.html ) {
						$( '#ndizi_modal_discussion_container' ).html(
							response.html
						);
					}
				} )
				.fail( function () {
					$( '#ndizi_modal_discussion_container' ).html(
						'<div class="ndizi-portal-alert alert-error">Error loading discussion thread.</div>'
					);
				} );
		} );

		// Close modal button
		$( '.ndizi-portal-modal-close-btn' ).on( 'click', function () {
			$modal.hide();
			$( '#ndizi_modal_discussion_container' ).empty();
		} );

		// Close modal on background click
		$modal.on( 'click', function ( e ) {
			if ( $( e.target ).hasClass( 'ndizi-portal-modal' ) ) {
				$modal.hide();
				$( '#ndizi_modal_discussion_container' ).empty();
			}
		} );
	}
} )( window.jQuery );
