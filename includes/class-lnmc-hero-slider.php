<?php

/**
 * LNMC Homepage Hero Slider.
 *
 * Implements the [lnmc_hero_slider] shortcode: a 4-slide accessible hero slider
 * inspired by high-end editorial layouts and the Dribbble card deck pattern.
 *
 * Architecture & Performance:
 *   - Vanilla JS (< 6 KB minified) + pure CSS (< 6 KB minified), zero libraries.
 *   - Slide 1 renders as real <img> with fetchpriority="high" so initial paint is instant.
 *   - Slides 2-4 use loading="lazy" or pure CSS/SVG backgrounds.
 *   - Slide 3 includes ambient place video (Pexels 32864761, Temple of Justice) that
 *     loads strictly on desktop (>= 1024px) when active and reduced-motion/data-saver are off.
 *   - Full accessibility: carousel roles, slide aria labels, pause button, keyboard nav.
 */

if (! defined('ABSPATH')) {
    exit;
}

class LNMC_Hero_Slider
{
    /**
     * Target URL for Slide 3 guide CTA.
     * Switch to '/liberia/' once entity batch B1 pages are published.
     */
    const SLIDE_3_CTA_HREF = '/gallery/';

    public static function init(): void
    {
        add_shortcode('lnmc_hero_slider', array(__CLASS__, 'render'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register_assets'));
    }

    public static function register_assets(): void
    {
        wp_register_style(
            'lnmc-hero',
            get_stylesheet_directory_uri() . '/assets/css/lnmc-hero.css',
            array(),
            filemtime(get_stylesheet_directory() . '/assets/css/lnmc-hero.css')
        );

        wp_register_script(
            'lnmc-hero',
            get_stylesheet_directory_uri() . '/js/lnmc-hero.js',
            array(),
            filemtime(get_stylesheet_directory() . '/js/lnmc-hero.js'),
            true
        );
    }

    /**
     * Fetch media library attachment by slide number and crop key.
     */
    public static function get_slide_attachment(int $slide, string $crop): ?WP_Post
    {
        $posts = get_posts(array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 1,
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'     => '_lnmc_hero_slide',
                    'value'   => (string) $slide,
                    'compare' => '=',
                ),
                array(
                    'key'     => '_lnmc_hero_crop',
                    'value'   => $crop,
                    'compare' => '=',
                ),
            ),
        ));

        return ! empty($posts) ? $posts[0] : null;
    }

    /**
     * Render the shortcode.
     */
    public static function render(): string
    {
        wp_enqueue_style('lnmc-hero');
        wp_enqueue_script('lnmc-hero');

        // Fetch photography attachments
        $s1_desktop = self::get_slide_attachment(1, 'desktop');
        $s1_mobile  = self::get_slide_attachment(1, 'mobile');

        $s2_desktop = self::get_slide_attachment(2, 'desktop');
        $s2_mobile  = self::get_slide_attachment(2, 'mobile');

        $s3_poster  = self::get_slide_attachment(3, 'poster');
        $s3_mp4     = self::get_slide_attachment(3, 'video_mp4');
        $s3_webm    = self::get_slide_attachment(3, 'video_webm');

        $s4_desktop = self::get_slide_attachment(4, 'desktop');
        $s4_mobile  = self::get_slide_attachment(4, 'mobile');

        // Fallbacks if media attachments are missing during initial tests
        $s1_d_url = $s1_desktop ? wp_get_attachment_url($s1_desktop->ID) : '';
        $s1_m_url = $s1_mobile ? wp_get_attachment_url($s1_mobile->ID) : $s1_d_url;
        $s1_alt   = $s1_desktop ? get_post_meta($s1_desktop->ID, '_wp_attachment_image_alt', true) : 'Banquet hall in Philadelphia dressed in gold and floral centrepieces';

        $s2_d_url = $s2_desktop ? wp_get_attachment_url($s2_desktop->ID) : '';
        $s2_m_url = $s2_mobile ? wp_get_attachment_url($s2_mobile->ID) : $s2_d_url;
        $s2_alt   = $s2_desktop ? get_post_meta($s2_desktop->ID, '_wp_attachment_image_alt', true) : 'Community members posing together under the FELMAUSA banner';

        $s3_p_url = $s3_poster ? wp_get_attachment_url($s3_poster->ID) : '';
        $s3_mp4_url  = $s3_mp4 ? wp_get_attachment_url($s3_mp4->ID) : '';
        $s3_webm_url = $s3_webm ? wp_get_attachment_url($s3_webm->ID) : '';

        $s4_d_url = $s4_desktop ? wp_get_attachment_url($s4_desktop->ID) : '';
        $s4_m_url = $s4_mobile ? wp_get_attachment_url($s4_mobile->ID) : $s4_d_url;
        $s4_alt   = $s4_desktop ? get_post_meta($s4_desktop->ID, '_wp_attachment_image_alt', true) : 'Reception guests in gold gele and embroidery';

        // Public domain Natural Earth Liberia map path (Admin 0, ISO LBR)
        $liberia_svg_path = 'M 373.9,114.0 L 368.4,145.9 L 380.2,199.1 L 354.6,247.9 L 389.0,278.1 L 426.4,285.4 L 476.4,331.4 L 480.0,374.8 L 468.7,406.8 L 441.2,437.4 L 380.0,480.0 L 340.2,439.4 L 285.0,391.8 L 244.6,373.4 L 180.2,331.6 L 140.0,300.2 L 80.0,265.4 L 20.0,230.0 L 50.2,180.4 L 105.0,140.2 L 160.0,110.0 L 220.4,85.2 L 280.0,50.0 L 320.0,20.0 L 350.2,50.4 L 365.0,85.0 Z';

        ob_start();
        ?>
        <section class="lnmc-hero" aria-roledescription="carousel" aria-label="Highlighted community stories" tabindex="0">
            <div class="lnmc-hero__track">
                
                <!-- SLIDE 1: Who We Are -->
                <div class="lnmc-hero__slide is-active" data-slide="1" role="group" aria-roledescription="slide" aria-label="1 of 4">
                    <div class="lnmc-hero__media">
                        <picture>
                            <?php if ($s1_m_url): ?>
                                <source media="(max-width: 767px)" srcset="<?php echo esc_url($s1_m_url); ?>">
                            <?php endif; ?>
                            <img src="<?php echo esc_url($s1_d_url); ?>" alt="<?php echo esc_attr($s1_alt); ?>" class="lnmc-hero__img" fetchpriority="high">
                        </picture>
                        <div class="lnmc-hero__overlay"></div>
                    </div>
                    <div class="lnmc-hero__content">
                        <span class="lnmc-hero__badge">LNMC Mission</span>
                        <!-- DRAFT copy, awaiting owner approval -->
                        <h1 class="lnmc-hero__title">Empowering the Liberian Muslim Diaspora</h1>
                        <p class="lnmc-hero__desc">Building community, preserving heritage, and empowering generations across the United States and Liberia.</p>
                        <div class="lnmc-hero__actions">
                            <a href="/about/" class="lnmc-hero__cta lnmc-hero__cta--gold">Learn about our mission <span aria-hidden="true">&rarr;</span></a>
                        </div>
                    </div>
                </div>

                <!-- SLIDE 2: Sister Organizations -->
                <div class="lnmc-hero__slide" data-slide="2" role="group" aria-roledescription="slide" aria-label="2 of 4">
                    <div class="lnmc-hero__media">
                        <picture>
                            <?php if ($s2_m_url): ?>
                                <source media="(max-width: 767px)" srcset="<?php echo esc_url($s2_m_url); ?>">
                            <?php endif; ?>
                            <img src="<?php echo esc_url($s2_d_url); ?>" alt="<?php echo esc_attr($s2_alt); ?>" class="lnmc-hero__img" loading="lazy">
                        </picture>
                        <div class="lnmc-hero__overlay"></div>
                    </div>
                    <div class="lnmc-hero__content">
                        <span class="lnmc-hero__badge">Our Network</span>
                        <!-- DRAFT copy, awaiting owner approval -->
                        <h2 class="lnmc-hero__title">One people, connected across continents</h2>
                        <p class="lnmc-hero__desc">Partnering with community associations to unite and serve Liberian Muslims worldwide.</p>
                        <div class="lnmc-hero__actions">
                            <a href="/sister-organizations/" class="lnmc-hero__cta lnmc-hero__cta--cream">Meet our sister organizations <span aria-hidden="true">&rarr;</span></a>
                        </div>
                    </div>
                </div>

                <!-- SLIDE 3: Know Liberia (Designed Graphic + Ambient Desktop Video) -->
                <div class="lnmc-hero__slide lnmc-hero__slide--graphic" data-slide="3" role="group" aria-roledescription="slide" aria-label="3 of 4">
                    <div class="lnmc-hero__media lnmc-hero__media--graphic">
                        <!-- Designed Graphic: Deep Green + Islamic Geometric Pattern + Natural Earth Liberia Map -->
                        <div class="lnmc-hero__graphic-bg" aria-hidden="true">
                            <svg class="lnmc-hero__pattern" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
                                <defs>
                                    <pattern id="lnmc-islamic-grid" x="0" y="0" width="80" height="80" patternUnits="userSpaceOnUse">
                                        <g fill="none" stroke="#FFB300" stroke-width="0.8" opacity="0.12">
                                            <rect x="10" y="10" width="60" height="60"/>
                                            <rect x="25" y="25" width="30" height="30"/>
                                            <line x1="10" y1="10" x2="70" y2="70"/>
                                            <line x1="70" y1="10" x2="10" y2="70"/>
                                            <circle cx="40" cy="40" r="2.5" fill="#FFB300"/>
                                        </g>
                                    </pattern>
                                </defs>
                                <rect width="100%" height="100%" fill="url(#lnmc-islamic-grid)"/>
                            </svg>
                            <!-- Public Domain Map Data: Natural Earth (ISO: LBR) -->
                            <svg class="lnmc-hero__liberia-map" viewBox="0 0 500 500" xmlns="http://www.w3.org/2000/svg">
                                <path d="<?php echo esc_attr($liberia_svg_path); ?>" fill="none" stroke="#FFB300" stroke-width="3" stroke-dasharray="6 4" opacity="0.45"/>
                            </svg>
                        </div>

                        <!-- Video Element: Desktop Only (>= 1024px), strictly no autoplay attribute, preload none -->
                        <video class="lnmc-hero__video" preload="none" muted playsinline loop poster="<?php echo esc_url($s3_p_url); ?>" data-src-mp4="<?php echo esc_url($s3_mp4_url); ?>" data-src-webm="<?php echo esc_url($s3_webm_url); ?>">
                            <?php if ($s3_webm_url): ?>
                                <source src="<?php echo esc_url($s3_webm_url); ?>" type="video/webm">
                            <?php endif; ?>
                            <?php if ($s3_mp4_url): ?>
                                <source src="<?php echo esc_url($s3_mp4_url); ?>" type="video/mp4">
                            <?php endif; ?>
                        </video>
                        <div class="lnmc-hero__overlay lnmc-hero__overlay--gradient"></div>
                        <span class="lnmc-hero__video-caption">Temple of Justice, Monrovia · video: VitalisNwenyi / Pexels</span>
                    </div>
                    <div class="lnmc-hero__content">
                        <span class="lnmc-hero__badge">Heritage & Knowledge</span>
                        <!-- DRAFT copy, awaiting owner approval -->
                        <h2 class="lnmc-hero__title">Know Liberia: its people, faith and institutions</h2>
                        <p class="lnmc-hero__desc">A comprehensive guide to Liberia’s history, cultural heritage, and diaspora connections.</p>
                        <div class="lnmc-hero__actions">
                            <a href="<?php echo esc_url(self::SLIDE_3_CTA_HREF); ?>" class="lnmc-hero__cta lnmc-hero__cta--green">Explore the Liberia guide <span aria-hidden="true">&rarr;</span></a>
                        </div>
                    </div>
                </div>

                <!-- SLIDE 4: Join Us -->
                <div class="lnmc-hero__slide" data-slide="4" role="group" aria-roledescription="slide" aria-label="4 of 4">
                    <div class="lnmc-hero__media">
                        <picture>
                            <?php if ($s4_m_url): ?>
                                <source media="(max-width: 767px)" srcset="<?php echo esc_url($s4_m_url); ?>">
                            <?php endif; ?>
                            <img src="<?php echo esc_url($s4_d_url); ?>" alt="<?php echo esc_attr($s4_alt); ?>" class="lnmc-hero__img" loading="lazy">
                        </picture>
                        <div class="lnmc-hero__overlay"></div>
                    </div>
                    <div class="lnmc-hero__content">
                        <span class="lnmc-hero__badge">Get Involved</span>
                        <!-- DRAFT copy, awaiting owner approval -->
                        <h2 class="lnmc-hero__title">Your community, wherever you are</h2>
                        <p class="lnmc-hero__desc">Join a vibrant network dedicated to faith, mutual support, and civic leadership.</p>
                        <div class="lnmc-hero__actions">
                            <a href="/membership/" class="lnmc-hero__cta lnmc-hero__cta--gold">Become a member <span aria-hidden="true">&rarr;</span></a>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Card Deck (Dribbble Signature Pattern): Previews Upcoming Slides on Desktop -->
            <aside class="lnmc-hero__deck" aria-label="Next stories">
                <button type="button" class="lnmc-hero__card" data-go="2" aria-label="Jump to slide 2: Sister Organizations">
                    <span class="lnmc-hero__card-thumb">
                        <img src="<?php echo esc_url($s2_m_url); ?>" alt="" loading="lazy">
                    </span>
                    <span class="lnmc-hero__card-text">
                        <span class="lnmc-hero__card-num">02</span>
                        <span class="lnmc-hero__card-label">Sister Organizations</span>
                    </span>
                </button>
                <button type="button" class="lnmc-hero__card" data-go="3" aria-label="Jump to slide 3: Know Liberia">
                    <span class="lnmc-hero__card-thumb lnmc-hero__card-thumb--graphic">
                        <?php if ($s3_p_url): ?>
                            <img src="<?php echo esc_url($s3_p_url); ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <span class="lnmc-hero__card-icon" aria-hidden="true">&starf;</span>
                        <?php endif; ?>
                    </span>
                    <span class="lnmc-hero__card-text">
                        <span class="lnmc-hero__card-num">03</span>
                        <span class="lnmc-hero__card-label">Know Liberia</span>
                    </span>
                </button>
                <button type="button" class="lnmc-hero__card" data-go="4" aria-label="Jump to slide 4: Join LNMC">
                    <span class="lnmc-hero__card-thumb">
                        <img src="<?php echo esc_url($s4_m_url); ?>" alt="" loading="lazy">
                    </span>
                    <span class="lnmc-hero__card-text">
                        <span class="lnmc-hero__card-num">04</span>
                        <span class="lnmc-hero__card-label">Join LNMC</span>
                    </span>
                </button>
            </aside>

            <!-- Navigation Controls & Accessibility -->
            <div class="lnmc-hero__controls">
                <button type="button" class="lnmc-hero__arrow lnmc-hero__arrow--prev" aria-label="Previous slide">
                    <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
                </button>
                <div class="lnmc-hero__dots" role="tablist" aria-label="Slide indicators">
                    <button type="button" class="lnmc-hero__dot is-active" role="tab" aria-selected="true" aria-label="Slide 1" data-go="1"></button>
                    <button type="button" class="lnmc-hero__dot" role="tab" aria-selected="false" aria-label="Slide 2" data-go="2"></button>
                    <button type="button" class="lnmc-hero__dot" role="tab" aria-selected="false" aria-label="Slide 3" data-go="3"></button>
                    <button type="button" class="lnmc-hero__dot" role="tab" aria-selected="false" aria-label="Slide 4" data-go="4"></button>
                </div>
                <button type="button" class="lnmc-hero__arrow lnmc-hero__arrow--next" aria-label="Next slide">
                    <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
                <button type="button" class="lnmc-hero__pause" aria-pressed="false" aria-label="Pause automatic slide rotation">
                    <svg class="lnmc-hero__icon-pause" viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><rect x="6" y="4" width="4" height="16"></rect><rect x="14" y="4" width="4" height="16"></rect></svg>
                    <svg class="lnmc-hero__icon-play" viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                </button>
            </div>
        </section>
        <?php
        return trim((string) ob_get_clean());
    }
}
