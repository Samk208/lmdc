/**
 * LNMC Diaspora — legacy animation shim.
 *
 * The scroll-reveal logic that lived here was superseded by the production
 * implementation in `lnmc-design.js` on 2026-05-01. This shim still exists
 * so that any cached pages referencing the old script handle keep loading
 * with a 200, and so the behaviour is reproducible if the design module
 * fails (defense in depth — never leave content stuck at opacity 0).
 *
 * It runs after a microtask delay; if `lnmc-design` already added `.visible`
 * to every `.lnmc-fade-in` (which it does in the same paint), this module
 * is a no-op. Otherwise it forces them visible — guarantees content always
 * appears even if the design module errors out before reveal.
 */
(function () {
	'use strict';
	function ensureVisible() {
		// One-shot safety: if anything is still at opacity 0 after 1.5s
		// (well past first contentful paint + observer settle), force it.
		setTimeout( function () {
			var nodes = document.querySelectorAll(
				'.lnmc-fade-in:not(.visible), .lnmc-reveal:not(.is-visible), .lnmc-motion-ready:not(.lnmc-motion-in)'
			);
			for ( var i = 0; i < nodes.length; i++ ) {
				nodes[ i ].classList.add( 'visible' );
				nodes[ i ].classList.add( 'is-visible' );
				if ( nodes[ i ].classList.contains( 'lnmc-motion-ready' ) ) {
					nodes[ i ].classList.add( 'lnmc-motion-in' );
				}
			}
		}, 1500 );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', ensureVisible );
	} else {
		ensureVisible();
	}
})();
