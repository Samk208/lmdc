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
     * Slide 3 points at the Liberia guide once its hub is published, and at
     * the gallery until then, so the hero can ship before or after the guide.
     */
    private static function slide_3_cta(): array
    {
        $guide = get_page_by_path('liberia');
        if ($guide && 'publish' === $guide->post_status) {
            return array(get_permalink($guide), 'Explore the Liberia guide');
        }

        return array(home_url('/gallery/'), 'See the photo gallery');
    }

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

        list($s3_cta_href, $s3_cta_label) = self::slide_3_cta();

        ob_start();
        ?>
        <section class="lnmc-hero" aria-roledescription="carousel" aria-label="Highlighted community stories" tabindex="0">
            <div class="lnmc-hero__track">
                
                <!-- SLIDE 1: Who We Are -->
                <div class="lnmc-hero__slide is-active" data-slide="1" data-label="Our mission" role="group" aria-roledescription="slide" aria-label="1 of 4">
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
                        <!-- DRAFT copy, awaiting owner approval -->
                        <h1 class="lnmc-hero__title">Empowering the Liberian Muslim Diaspora</h1>
                        <p class="lnmc-hero__desc">Faith, heritage and mutual support for Liberian Muslim families, wherever they have made their home.</p>
                        <div class="lnmc-hero__actions">
                            <a href="<?php echo esc_url(home_url('/about/')); ?>" class="lnmc-hero__cta lnmc-hero__cta--gold">Learn about our mission</a>
                        </div>
                    </div>
                </div>

                <!-- SLIDE 2: Sister Organizations -->
                <div class="lnmc-hero__slide lnmc-hero__slide--split" data-slide="2" data-label="Sister organizations" role="group" aria-roledescription="slide" aria-label="2 of 4">
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
                        <!-- DRAFT copy, awaiting owner approval -->
                        <h2 class="lnmc-hero__title">One people, connected across continents</h2>
                        <p class="lnmc-hero__desc">Ten sister organizations in Liberia, North America, Europe, Ghana and Australia work alongside us.</p>
                        <div class="lnmc-hero__actions">
                            <a href="<?php echo esc_url(home_url('/sister-organizations/')); ?>" class="lnmc-hero__cta lnmc-hero__cta--cream">Meet our sister organizations</a>
                        </div>
                    </div>
                </div>

                <!-- SLIDE 3: Know Liberia (Designed Graphic + Ambient Desktop Video) -->
                <div class="lnmc-hero__slide lnmc-hero__slide--graphic" data-slide="3" data-label="Know Liberia" role="group" aria-roledescription="slide" aria-label="3 of 4">
                    <div class="lnmc-hero__media lnmc-hero__media--graphic">
                        <!-- Deep green + geometric pattern; the poster/video sit on top on desktop -->
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
                        <!-- DRAFT copy, awaiting owner approval -->
                        <h2 class="lnmc-hero__title">Know Liberia: its people, faith and institutions</h2>
                        <p class="lnmc-hero__desc">Sourced reference pages on Liberia’s government, ethnic groups, religion and diaspora organizations.</p>
                        <div class="lnmc-hero__actions">
                            <a href="<?php echo esc_url($s3_cta_href); ?>" class="lnmc-hero__cta lnmc-hero__cta--cream"><?php echo esc_html($s3_cta_label); ?></a>
                        </div>
                    </div>
                </div>

                <!-- SLIDE 4: Join Us -->
                <div class="lnmc-hero__slide lnmc-hero__slide--split" data-slide="4" data-label="Join us" role="group" aria-roledescription="slide" aria-label="4 of 4">
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
                        <!-- DRAFT copy, awaiting owner approval -->
                        <h2 class="lnmc-hero__title">Your community, wherever you are</h2>
                        <p class="lnmc-hero__desc">Become a member to stay close to events, news and fellow Liberian Muslims across the diaspora.</p>
                        <div class="lnmc-hero__actions">
                            <a href="<?php echo esc_url(home_url('/membership/')); ?>" class="lnmc-hero__cta lnmc-hero__cta--gold">Become a member</a>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Card Deck (Dribbble Signature Pattern): Previews Upcoming Slides on Desktop -->
            <aside class="lnmc-hero__deck" aria-label="Next stories">
                <button type="button" class="lnmc-hero__card" data-go="2" aria-label="Show slide: Sister organizations">
                    <span class="lnmc-hero__card-thumb">
                        <img src="<?php echo esc_url($s2_m_url); ?>" alt="" loading="lazy">
                    </span>
                    <span class="lnmc-hero__card-text">
                        <span class="lnmc-hero__card-label">Sister organizations</span>
                    </span>
                </button>
                <button type="button" class="lnmc-hero__card" data-go="3" aria-label="Show slide: Know Liberia">
                    <span class="lnmc-hero__card-thumb lnmc-hero__card-thumb--graphic">
                        <?php if ($s3_p_url): ?>
                            <img src="<?php echo esc_url($s3_p_url); ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <span class="lnmc-hero__card-icon" aria-hidden="true">&starf;</span>
                        <?php endif; ?>
                    </span>
                    <span class="lnmc-hero__card-text">
                        <span class="lnmc-hero__card-label">Know Liberia</span>
                    </span>
                </button>
                <button type="button" class="lnmc-hero__card" data-go="4" aria-label="Show slide: Join us">
                    <span class="lnmc-hero__card-thumb">
                        <img src="<?php echo esc_url($s4_m_url); ?>" alt="" loading="lazy">
                    </span>
                    <span class="lnmc-hero__card-text">
                        <span class="lnmc-hero__card-label">Join us</span>
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
