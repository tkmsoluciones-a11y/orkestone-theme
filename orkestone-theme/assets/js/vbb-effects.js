/**
 * VBB Scroll Effects — IntersectionObserver
 *
 * Watches .vbb-effect elements and toggles .vbb-visible when they enter
 * the viewport. Respects `prefers-reduced-motion`.
 *
 * @package VerticalBlockBase
 */

( function () {
	'use strict';

	if ( typeof window === 'undefined' || ! window.IntersectionObserver ) {
		return;
	}

	// Bail for reduced motion.
	if ( window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
		// Show everything immediately.
		document.querySelectorAll( '.vbb-effect' ).forEach( function ( el ) {
			el.classList.add( 'vbb-visible' );
		} );
		return;
	}

	var observer = new IntersectionObserver(
		function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					entry.target.classList.add( 'vbb-visible' );
					observer.unobserve( entry.target );
				}
			} );
		},
		{
			rootMargin: '0px 0px -60px 0px',
			threshold: 0.1,
		}
	);

	// Observe all .vbb-effect elements.
	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.vbb-effect' ).forEach( function ( el ) {
			// Respect manual stagger inner elements.
			if ( el.classList.contains( 'vbb-effect-stagger' ) ) {
				observer.observe( el );
				return;
			}
			// Direct effect on the element itself.
			if (
				el.classList.contains( 'vbb-effect-fade' ) ||
				el.classList.contains( 'vbb-effect-slide-up' ) ||
				el.classList.contains( 'vbb-effect-slide-left' ) ||
				el.classList.contains( 'vbb-effect-slide-right' ) ||
				el.classList.contains( 'vbb-effect-zoom' ) ||
				el.classList.contains( 'vbb-effect-flip' )
			) {
				observer.observe( el );
			}
		} );
	} );
} )();
