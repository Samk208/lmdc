/**
 * LNMC Diaspora — homepage design layer (child theme owned)
 *
 * Migrated from `lnmc-member-hub/assets/js/global-scripts.js` on 2026-05-01.
 * The membership plugin no longer injects theme classes into the Divi DOM;
 * presentation lives here so the plugin can be replaced/updated without
 * breaking the homepage.
 *
 * Behaviour layered onto Divi at runtime:
 *   1. Tags semantic homepage sections with theme-aware classes used by CSS
 *      (.lnmc-pattern-dots, .lnmc-pattern-waves, etc.).
 *   2. Header gets `.lnmc-header-scrolled` after 50px of scroll for shadow.
 *   3. Below-the-fold content fades up via IntersectionObserver
 *      (`prefers-reduced-motion` aware).
 *   4. Buttons get a CSS-driven press effect — no jQuery ripple painting.
 *   5. Smooth-scroll anchor links with header-offset compensation.
 *   6. Optional image swap: if the plugin printed `window.LnmcDesign`, the
 *      cultural images are substituted for any sufficiently-large content
 *      image. Silently no-ops without the plugin.
 *
 * Vanilla JS. No jQuery. Defers nothing the user can see — every IO
 * subscription is one-shot and unobserves on first hit.
 */
(function () {
	'use strict';

	var REDUCE_MOTION = window.matchMedia
		&& window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	/* -------------------------------------------------------------- */
	/* 1. SECTION TAGGING — give Divi sections semantic theme classes */
	/* -------------------------------------------------------------- */
	function tagHomepageSections() {
		if ( ! document.body.classList.contains( 'home' ) ) {
			return;
		}
		var sections = document.querySelectorAll( '#main-content .et_pb_section' );
		if ( sections.length < 6 ) {
			return; // Layout doesn't match expected shape — bail safely.
		}
		// Index → semantic name map. Order is enforced by the homepage builder.
		// CSS in style.css already targets et_pb_section_N selectors; these
		// classes give designers a more readable hook (e.g. .lnmc-cta-section).
		var map = [
			'lnmc-hero-section',
			'lnmc-mission-section',
			'lnmc-services-section',
			'lnmc-testimonials-section',
			'lnmc-events-section',
			'lnmc-cta-section',
			'lnmc-contact-section'
		];
		for ( var i = 0; i < sections.length && i < map.length; i++ ) {
			sections[ i ].classList.add( map[ i ] );
		}
	}

	/* -------------------------------------------------------------- */
	/* 2. HEADER SCROLL STATE                                         */
	/* -------------------------------------------------------------- */
	function bindHeaderScroll() {
		var header = document.getElementById( 'main-header' )
			|| document.querySelector( '.et-l--header' );
		if ( ! header ) return;

		var ticking = false;
		var apply = function () {
			var scrolled = window.scrollY > 50;
			header.classList.toggle( 'lnmc-header-scrolled', scrolled );
			ticking = false;
		};

		window.addEventListener( 'scroll', function () {
			if ( ! ticking ) {
				window.requestAnimationFrame( apply );
				ticking = true;
			}
		}, { passive: true } );

		apply();
	}

	/* -------------------------------------------------------------- */
	/* 3. SCROLL-REVEAL FADE-IN                                       */
	/* -------------------------------------------------------------- */
	function bindScrollReveal() {
		// Anything the theme wants to fade in. Hero is excluded — it
		// must be visible immediately for LCP / first paint.
		// Includes .et_pb_team_member so /about/ leader portraits get the
		// reveal + eager-load swap (otherwise lazy lazy-loaded portraits
		// below the fold can stay blank if the browser's native lazy
		// observer hasn't kicked in by the time the user scrolls).
		var selector = [
			'.et_pb_blurb', '.et_pb_testimonial', '.et_pb_pricing_table',
			'.et_pb_gallery_item', '.et_pb_number_counter',
			'.et_pb_team_member',
			'#main-content .et_pb_image', '#main-content .et_pb_text'
		].join( ',' );

		var nodes = document.querySelectorAll( selector );
		if ( ! nodes.length ) return;

		// In hero (.et_pb_section_0) we skip — DON'T add the fade class
		// or those modules render at opacity:0 until the observer fires.
		var heroSel = '.et_pb_section_0:not(.et_pb_section_0_tb_header) ';
		var heroExclude = document.querySelectorAll(
			heroSel + '.et_pb_blurb, ' + heroSel + '.et_pb_text, ' +
			heroSel + '.et_pb_image, ' + heroSel + '.et_pb_heading'
		);
		var inHero = new Set( Array.prototype.slice.call( heroExclude ) );

		var queued = [];
		nodes.forEach( function ( el ) {
			if ( inHero.has( el ) ) return;
			el.classList.add( 'lnmc-fade-in' );
			queued.push( el );
		} );

		if ( REDUCE_MOTION || ! ( 'IntersectionObserver' in window ) ) {
			queued.forEach( function ( el ) {
				el.classList.add( 'visible' );
				el.querySelectorAll( 'img[loading="lazy"]' ).forEach( function ( img ) {
					// Just toggling loading=eager is not enough — once the browser has
// "skipped" a lazy img inside an opacity:0 ancestor it won't re-check
// without an attribute change to src. Re-assigning src kicks the
// fetch reliably; we drop srcset to avoid old-path thumbnails.
if ( ! img.complete || img.naturalWidth === 0 ) {
	var oldSrc = img.getAttribute( 'src' );
	if ( oldSrc ) {
		img.removeAttribute( 'srcset' );
		img.removeAttribute( 'sizes' );
		img.removeAttribute( 'loading' );
		img.setAttribute( 'src', oldSrc );
	}
}
				} );
			} );
			return;
		}

		var io = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( ! entry.isIntersecting ) return;
				var target = entry.target;
				// Force-eager any descendant lazy <img> the moment the
				// container intersects. Browsers' built-in lazy-load
				// observer can fail to fire when an ancestor starts at
				// opacity:0 (which our fade-in does), so testimonials
				// would render as blank circles indefinitely.
				var lazyImgs = target.querySelectorAll( 'img[loading="lazy"]' );
				lazyImgs.forEach( function ( img ) { // Just toggling loading=eager is not enough — once the browser has
// "skipped" a lazy img inside an opacity:0 ancestor it won't re-check
// without an attribute change to src. Re-assigning src kicks the
// fetch reliably; we drop srcset to avoid old-path thumbnails.
if ( ! img.complete || img.naturalWidth === 0 ) {
	var oldSrc = img.getAttribute( 'src' );
	if ( oldSrc ) {
		img.removeAttribute( 'srcset' );
		img.removeAttribute( 'sizes' );
		img.removeAttribute( 'loading' );
		img.setAttribute( 'src', oldSrc );
	}
} } );

				// Subtle stagger so siblings don't snap in unison.
				var siblings = target.parentNode
					? target.parentNode.children : [];
				var idx = Array.prototype.indexOf.call( siblings, target );
				var delay = Math.min( Math.max( idx, 0 ) * 90, 360 );
				setTimeout( function () {
					target.classList.add( 'visible' );
				}, delay );
				io.unobserve( target );
			} );
		}, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' } );

		queued.forEach( function ( el ) { io.observe( el ); } );
	}

	/* -------------------------------------------------------------- */
	/* 3b. ROUND 11 FUNCTIONAL MOTION                                 */
	/*     A controlled cinematic layer that extends the existing     */
	/*     child-theme reveal system. Header, hero, footer, and       */
	/*     plugin business logic stay untouched.                      */
	/* -------------------------------------------------------------- */
	function bindFunctionalMotion() {
		var selector = [
			'body.home #main-content .et_pb_section_1 .et_pb_column',
			'body.home #main-content .et_pb_section_2 .et_pb_row_2',
			'body.home #main-content .et_pb_section_2 .et_pb_row_3 > .et_pb_column',
			'body.home #main-content .et_pb_section_3 .et_pb_row_5',
			'body.home #main-content .et_pb_section_3 .et_pb_row_6',
			'body.home #main-content .et_pb_section_4 .et_pb_row_7',
			'body.home #main-content .et_pb_section_4 .et_pb_row_8 > .et_pb_column',
			'body.home #main-content .et_pb_section_4 .et_pb_row_9 > .et_pb_column',
			'body.home #main-content .et_pb_section_5 .et_pb_row_11',
			'body.home #main-content .et_pb_section_6 .et_pb_column_21',
			'body.home #main-content .et_pb_section_6 .et_pb_column_22',
			'body:not(.home) #main-content .et_pb_section:not(.et_pb_section_0) > .et_pb_row',
			'#main-content .lnmc-dir .lnmc-card',
			'#main-content .lnmc-grid .lnmc-card',
			'#main-content .lnmc-member-hub',
			'#main-content .lnmc-pricing-table',
			'#main-content .lnmc-member-dashboard',
			'#main-content .lnmc-membership-form'
		].join( ',' );

		var nodes = Array.prototype.slice.call( document.querySelectorAll( selector ) );
		if ( ! nodes.length ) return;

		nodes.forEach( function ( el ) {
			if ( el.closest( '.et-l--header, #main-header, .et-l--footer, #main-footer' ) ) {
				return;
			}
			el.classList.add( 'lnmc-motion-ready' );
			el.classList.add( 'lnmc-motion-rise' );

			if (
				el.matches( '.et_pb_row, .et_pb_column, .lnmc-card, .lnmc-member-hub, .lnmc-pricing-table, .lnmc-member-dashboard, .lnmc-membership-form' )
			) {
				el.classList.add( 'lnmc-motion-depth' );
			}
			if ( el.querySelector( 'img, .et_pb_image_wrap' ) ) {
				el.classList.add( 'lnmc-motion-image-pan' );
			}
			if ( el.parentNode ) {
				var siblings = Array.prototype.filter.call( el.parentNode.children, function ( child ) {
					return nodes.indexOf( child ) !== -1;
				} );
				var idx = Math.max( siblings.indexOf( el ), 0 );
				el.style.setProperty( '--lnmc-motion-delay', Math.min( idx * 90, 360 ) + 'ms' );
			}
		} );

		function show( el ) {
			el.classList.add( 'lnmc-motion-in' );
		}

		if ( REDUCE_MOTION || ! ( 'IntersectionObserver' in window ) ) {
			nodes.forEach( show );
			return;
		}

		var io = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( ! entry.isIntersecting ) return;
				show( entry.target );
				io.unobserve( entry.target );
			} );
		}, { threshold: 0.16, rootMargin: '0px 0px -8% 0px' } );

		nodes.forEach( function ( el ) { io.observe( el ); } );

		// Defense in depth: visual motion must never be allowed to hide content.
		setTimeout( function () {
			nodes.forEach( show );
		}, 1600 );
	}

	/* -------------------------------------------------------------- */
	/* 4. SMOOTH SCROLL FOR IN-PAGE ANCHORS                           */
	/* -------------------------------------------------------------- */
	function bindAnchorScroll() {
		document.addEventListener( 'click', function ( e ) {
			var anchor = e.target.closest( 'a[href^="#"]' );
			if ( ! anchor ) return;
			var hash = anchor.getAttribute( 'href' );
			if ( ! hash || hash === '#' || hash === '#0' ) return;
			var target = document.querySelector( hash );
			if ( ! target ) return;

			e.preventDefault();
			var header = document.getElementById( 'main-header' );
			var offset = header ? header.getBoundingClientRect().height + 16 : 80;
			var top = target.getBoundingClientRect().top + window.scrollY - offset;
			window.scrollTo( {
				top: top,
				behavior: REDUCE_MOTION ? 'auto' : 'smooth'
			} );
			// Move keyboard focus too so screen readers track.
			target.setAttribute( 'tabindex', '-1' );
			target.focus( { preventScroll: true } );
		} );
	}

	/* -------------------------------------------------------------- */
	/* 5. IMAGE REPLACEMENT (consumes plugin-supplied LnmcDesign)     */
	/* -------------------------------------------------------------- */
	function replaceCulturalImages() {
		// IMPORTANT — production constraint:
		// We must NEVER overwrite an image the editor has uploaded directly
		// (e.g. /uploads/YYYY/MM/dr-brima-sylla.jpg). Earlier this function
		// was too aggressive and replaced unique editor-supplied leader
		// portraits with the plugin's generic group photo because the
		// surrounding text contained "leader" or "about".
		//
		// New rule: ONLY swap images whose CURRENT src is one of the
		// plugin's bundled LnmcDesign URLs. That preserves explicit
		// editor choices and only "fills in" placeholder/legacy images.
		// Disabled entirely on /about/, /spotlights/, and any page where
		// the editor has clearly hand-curated portraits.
		if ( ! window.LnmcDesign || REDUCE_MOTION ) return;

		// Pages where the editor manages portraits directly — NEVER auto-swap.
		var protectedPaths = [ '/about', '/spotlights', '/sister-organizations',
			'/global-network', '/collaboration-opportunities' ];
		var here = window.location.pathname.toLowerCase();
		for ( var p = 0; p < protectedPaths.length; p++ ) {
			if ( here.indexOf( protectedPaths[ p ] ) === 0 ) return;
		}

		var d = window.LnmcDesign;
		// Build a set of plugin-bundled URLs so we can detect "is the
		// current src one of ours". Anything else is editor-owned and
		// off-limits.
		var bundled = new Set();
		Object.keys( d ).forEach( function ( k ) {
			var u = d[ k ];
			if ( typeof u === 'string' && u.indexOf( '/lnmc-member-hub/assets/images/' ) !== -1 ) {
				bundled.add( u );
			}
		} );

		var imgs = document.querySelectorAll(
			'#main-content .et_pb_section img' +
			':not(.lnmc-replaced-image)' +
			':not(.lnmc-leader-portrait-deduped)' +
			':not(.et_pb_logo)'
		);
		imgs.forEach( function ( img ) {
			var currentSrc = img.currentSrc || img.src;
			// Editor-uploaded image: hands-off.
			if ( ! bundled.has( currentSrc ) ) return;
			if ( img.complete && img.naturalWidth > 0 && img.naturalWidth < 150 ) return;

			var section = img.closest( '.et_pb_section, .et_pb_row, .et_pb_column' );
			if ( ! section ) return;
			var ctx = ( section.textContent || '' ).toLowerCase();

			var src = null;
			if ( /education|school|learn|program|study/.test( ctx ) ) {
				src = Math.random() < 0.5 ? d.educationImage : d.educationGroup;
			} else if ( /event|celebration|gathering|festival/.test( ctx ) ) {
				src = d.communityCelebration;
			} else if ( /family|support|home/.test( ctx ) ) {
				src = Math.random() < 0.5 ? d.familyHome : d.familyImage;
			}
			// Note: removed the "leader/about/founder" branch — too generic,
			// too easily over-fires on any /about/ context.
			if ( ! src || src === currentSrc ) return;

			img.setAttribute( 'src', src );
			img.removeAttribute( 'srcset' );
			img.classList.add( 'lnmc-replaced-image' );
		} );
	}

	/* -------------------------------------------------------------- */
	/* 6a. DARK-BG AUTO-DETECTION                                     */
	/*     Solves the persistent "black H3 on dark green" problem:    */
	/*     Divi customizer cached <style> sets h1..h6 { color:#0e0c19} */
	/*     site-wide. Inside any green or otherwise-dark Divi section */
	/*     this fails contrast (2.46:1). At runtime we inspect every  */
	/*     section / row / column, compute the effective background   */
	/*     luminance, and tag low-luminance ancestors with            */
	/*     `.lnmc-on-dark`. CSS then forces a readable cream/white    */
	/*     foreground for every descendant.                           */
	/* -------------------------------------------------------------- */
	function relativeLuminance( rgbStr ) {
		var m = rgbStr.match( /\d+(?:\.\d+)?/g );
		if ( ! m || m.length < 3 ) return 1;
		var rgb = m.slice( 0, 3 ).map( Number );
		var alpha = m.length >= 4 ? parseFloat( m[ 3 ] ) : 1;
		if ( alpha < 0.5 ) return 1; // mostly transparent — caller falls through
		var lin = rgb.map( function ( v ) {
			v /= 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
		} );
		return 0.2126 * lin[ 0 ] + 0.7152 * lin[ 1 ] + 0.0722 * lin[ 2 ];
	}
	function effectiveBg( el ) {
		var cur = el;
		while ( cur && cur !== document.body ) {
			var cs = window.getComputedStyle( cur );
			var bg = cs.backgroundColor;
			if ( bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent' ) {
				return bg;
			}
			// also consider background-image gradients that include rgba
			var bi = cs.backgroundImage;
			if ( bi && bi !== 'none' && bi.indexOf( 'gradient' ) !== -1 ) {
				// crude: pull first rgb(a) tuple
				var t = bi.match( /rgba?\([^)]+\)/ );
				if ( t ) return t[ 0 ];
			}
			cur = cur.parentElement;
		}
		return 'rgb(255, 255, 255)';
	}
	function tagDarkContainers() {
		// Tag containers whose effective background is dark with
		// `.lnmc-on-dark` (CSS forces cream/white text). ALSO tag
		// containers with light backgrounds that sit INSIDE a dark
		// container with `.lnmc-on-light` — those need their text colour
		// reset to dark, otherwise the cascade from `.lnmc-on-dark`
		// inherits cream text into white-bg cards (e.g. white testimonial
		// cards inside a dark green section → unreadable cream-on-white).
		var candidates = document.querySelectorAll(
			'.et_pb_section, .et_pb_row, .et_pb_column, .et_pb_blurb, ' +
			'.et_pb_text, .et_pb_testimonial, .et_pb_pricing_table, ' +
			'.et_pb_team_member, .et_pb_blog_grid_wrapper'
		);
		for ( var i = 0; i < candidates.length; i++ ) {
			var el = candidates[ i ];
			// Look only at the element's OWN computed background, not its
			// ancestor chain — we want to tag light cards independently.
			var ownCs = window.getComputedStyle( el );
			var ownBg = ownCs.backgroundColor;
			var ownHasOpaque = ownBg
				&& ownBg !== 'rgba(0, 0, 0, 0)'
				&& ownBg !== 'transparent'
				&& ! /,\s*0(\.0+)?\)/.test( ownBg ); // alpha 0 => skip
			if ( ownHasOpaque ) {
				var ownLum = relativeLuminance( ownBg );
				if ( ownLum < 0.30 ) {
					el.classList.add( 'lnmc-on-dark' );
				} else if ( ownLum > 0.65 ) {
					el.classList.add( 'lnmc-on-light' );
				}
				continue;
			}
			// No own bg — fall back to inherited (effective) bg only for
			// the dark case, so we still rescue text on a dark section
			// whose children don't have their own bg colour.
			var inheritedLum = relativeLuminance( effectiveBg( el ) );
			if ( inheritedLum < 0.30 ) {
				el.classList.add( 'lnmc-on-dark' );
			}
		}
	}

	/* -------------------------------------------------------------- */
	/* 6b. DEAD-CTA HREF ROUTER                                       */
	/*     Fallback for any future Divi button published without a    */
	/*     href. Current saved shortcodes have persistent URLs, but   */
	/*     this keeps the handover safe if editors add a new CTA.     */
	/* -------------------------------------------------------------- */
	function routeDeadCtas() {
		// Map label text (lowercased, trimmed) → destination path.
		// Covers every empty-href CTA found across all 12 pages of the
		// site. Edit/extend here; any new label authored either needs an
		// entry here OR a real href in the Divi Builder. Routing is the
		// safety net — the right long-term fix is real hrefs in modules.
		var routes = {
			// --- About / Mission ---
			'learn more':              '/about/',
			'learn about us':          '/about/',
			'discover more':           '/about/',
			'discover our mission':    '/about/',
			// --- Community engagement ---
			'explore now':             '/community/',
			'explore events':          '/community/',
			'join a program':          '/community/',
			'register now':            '/community/',
			'participate now':         '/community/',
			'join the celebration':    '/community/',
			'get involved':            '/community/',
			'get involved today':      '/community/',
			// --- Resources ---
			'explore services':        '/resources/',
			'access resources':        '/resources/',
			'discover resources':      '/resources/',
			'access aid':              '/resources/',
			// --- Membership / signup ---
			'sign up':                 '/membership/',
			'sign up today':           '/membership/',
			'join us':                 '/membership/',
			'join now':                '/membership/',
			'join our movement':       '/membership/',
			'join the movement':       '/membership/',
			'become a member':         '/membership/',
			'become a member today':   '/membership/',
			'subscribe':               '/membership/',
			'subscribe now':           '/membership/',
			'get started':             '/membership/',
			'take action now':         '/membership/',
			'contribute now':          '/membership/',
			// --- Contact / support ---
			'connect now':             '/contact/',
			'contact us':              '/contact/',
			'contact us now':          '/contact/',
			'get in touch now':        '/contact/',
			'get help':                '/contact/',
			'request support':         '/contact/',
			// --- Partner organisations ---
			'apply for partnership':   '/collaboration-opportunities/',
			'become a partner':        '/collaboration-opportunities/',
			'explore partnership areas':'/collaboration-opportunities/',
			'view directory':          '/sister-organizations/',
			'explore our network':     '/global-network/',
			'view detailed stats':     '/global-network/',
			'explore stories':         '/spotlights/',
			// --- Donate (optional, page may not exist yet) ---
			'donate':                  '/donate/',
			'donate now':              '/donate/',
			'donate today':            '/donate/',
			'join the cause':          '/donate/',
			'support now':             '/donate/',
			'transform lives':         '/donate/'
		};
		var btns = document.querySelectorAll( 'a.et_pb_button, .lnmc-brand-btn' );
		for ( var i = 0; i < btns.length; i++ ) {
			var a = btns[ i ];
			var href = a.getAttribute( 'href' );
			if ( href && href !== '' && href !== '#' && href !== 'javascript:void(0)' ) {
				continue; // already has a real destination
			}
			var label = ( a.textContent || '' ).trim().toLowerCase();
			var dest = routes[ label ];
			if ( dest ) {
				a.setAttribute( 'href', dest );
				a.setAttribute( 'data-lnmc-routed', '1' );
			}
		}
	}

	/* -------------------------------------------------------------- */
	/* 6c. /about/ LEADER PORTRAIT DEDUPLICATION                      */
	/*     Three leader cards on /about/ all point at the same        */
	/*     `lmmc-diaspora-networking-professionals.jpg` group photo.  */
	/*     Match each card to its named-portrait file by alt text or  */
	/*     adjacent heading. Each leader gets a distinct portrait;    */
	/*     no two leaders share an image.                             */
	/* -------------------------------------------------------------- */
	function dedupeLeaderPortraits() {
		var origin = window.location.origin;
		var portraits = {
			'brima sylla':         origin + '/wp-content/uploads/2026/05/lnmc-dr-brima-sylla.jpg',
			'omar abdallah dukuly': origin + '/wp-content/uploads/2026/05/lnmc-dr-omar-dukuly.jpg',
			'omar dukuly':         origin + '/wp-content/uploads/2026/05/lnmc-dr-omar-dukuly.jpg',
			'ibrahim fofana':      origin + '/wp-content/uploads/2026/05/lnmc-dr-ibrahim-fofana.jpg'
		};
		// Find every image, look at its alt or the nearest heading text.
		var imgs = document.querySelectorAll(
			'#main-content img[src*="lmmc-diaspora-networking-professionals"], ' +
			'#main-content img[src*="leader-professional-portrait"]'
		);
		var assigned = {};
		imgs.forEach( function ( img ) {
			var alt = ( img.alt || '' ).toLowerCase();
			// Also probe the nearest heading inside the same blurb/column.
			var ctx = '';
			var card = img.closest( '.et_pb_blurb, .et_pb_team_member, .et_pb_column' );
			if ( card ) {
				var h = card.querySelector( 'h1,h2,h3,h4,h5,h6' );
				if ( h ) ctx = ( h.textContent || '' ).toLowerCase();
			}
			var combined = alt + ' ' + ctx;
			var match = null;
			Object.keys( portraits ).some( function ( name ) {
				if ( combined.indexOf( name ) !== -1 && ! assigned[ name ] ) {
					match = portraits[ name ];
					assigned[ name ] = true;
					return true;
				}
				return false;
			} );
			if ( match ) {
				img.setAttribute( 'src', match );
				img.removeAttribute( 'srcset' );
				img.classList.add( 'lnmc-leader-portrait-deduped' );
			}
		} );
	}

	/* -------------------------------------------------------------- */
	/* 7. KEYBOARD-VS-MOUSE FOCUS RING                                */
	/* -------------------------------------------------------------- */
	function bindKeyboardFocus() {
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Tab' ) {
				document.body.classList.add( 'lnmc-keyboard-nav' );
			}
		} );
		document.addEventListener( 'mousedown', function () {
			document.body.classList.remove( 'lnmc-keyboard-nav' );
		} );
	}

	/* ------------------------------- boot ------------------------- */
	ready( function () {
		try {
			tagHomepageSections();
			tagDarkContainers();      // before reveal so dark-bg text never flashes black
			routeDeadCtas();          // before scroll-reveal observation
			dedupeLeaderPortraits();  // /about/ leaders get distinct faces
			bindHeaderScroll();
			bindFunctionalMotion();
			bindScrollReveal();
			bindAnchorScroll();
			bindKeyboardFocus();
			replaceCulturalImages();
		} catch ( err ) {
			// Theme code must never throw uncaught — Divi pages would lose
			// their below-the-fold reveal. Log to console only.
			if ( window.console && console.warn ) {
				console.warn( '[LNMC design] init error:', err );
			}
		}
	} );
})();
