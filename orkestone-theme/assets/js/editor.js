/**
 * Editor assets for Vertical Block Base.
 * Provides vertical context to the Gutenberg editor.
 *
 * @package VerticalBlockBase
 */

/* global VerticalBlockBaseEditor, wp */

wp.domReady( function () {
	if ( typeof VerticalBlockBaseEditor === 'undefined' ) {
		return;
	}

	// Log vertical context for debugging
	console.log(
		'[VBB Editor] Vertical activa:',
		VerticalBlockBaseEditor.activeVertical
	);
} );
