<?php

/**
 * LNMC event galleries.
 *
 * Photography is stored in the WordPress media library and tagged with the
 * `_lnmc_gallery_event` meta key (see tools/lnmc-gallery-import.php), which
 * keeps the theme free of hard-coded attachment IDs. Publishing a new album
 * means: optimise the files, import them with the same event slug, and the
 * existing `[lnmc_gallery]` shortcode picks them up on the next page load.
 *
 * The rendered markup is progressive enhancement-first: every tile is a plain
 * link to the full-size file, so the gallery stays usable if JavaScript fails
 * or the browser does not support <dialog>. js/lnmc-gallery.js upgrades those
 * links into an accessible modal viewer.
 */

if (! defined('ABSPATH')) {
    exit;
}

class LNMC_Gallery
{
    /**
     * Per-event copy. The shortcode only needs the slug; everything else that
     * ends up in the page copy and in the structured data lives here.
     */
    const EVENTS = array(
        'felmausa-inauguration-2026' => array(
            'title'       => 'FELMAUSA Inauguration 2026',
            'subtitle'    => 'Two historic nights of success, unity and celebration',
            'description' => 'Delegates and guests gather in Philadelphia for the 2026 inauguration of the Federation of Liberian Mandingo Associations in the U.S.A. (FELMAUSA), as Hon. Alpha Jabateh takes office as president.',
            'date'        => '2026-10-16',
            'date_end'    => '2026-10-18',
            'location'    => array(
                'name'    => 'Philadelphia, Pennsylvania',
                'street'  => '5616 Woodland Avenue',
                'city'    => 'Philadelphia',
                'region'  => 'PA',
                'country' => 'US',
            ),
            'organizer'   => array(
                'name'      => 'Federation of Liberian Mandingo Associations in the U.S.A.',
                'short'     => 'FELMAUSA',
                'url'       => 'https://www.felmausa.org/',
                'profile'   => '/community-partners/sister-organizations/federation-of-liberian-mandingo-associations-in-the-usa-felmausa/',
            ),
        ),
    );

    public static function init(): void
    {
        add_shortcode('lnmc_gallery', array(__CLASS__, 'render'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register_assets'));
    }

    /**
     * Register only — the assets are enqueued from render() so pages without a
     * gallery never pay for the stylesheet or the viewer script.
     */
    public static function register_assets(): void
    {
        wp_register_style(
            'lnmc-gallery',
            get_stylesheet_directory_uri() . '/assets/css/lnmc-gallery.css',
            array(),
            filemtime(get_stylesheet_directory() . '/assets/css/lnmc-gallery.css')
        );

        wp_register_script(
            'lnmc-gallery',
            get_stylesheet_directory_uri() . '/js/lnmc-gallery.js',
            array(),
            filemtime(get_stylesheet_directory() . '/js/lnmc-gallery.js'),
            true
        );
    }

    /**
     * Attachments tagged for an event, in the order they were imported.
     */
    public static function get_event_photos(string $event): array
    {
        $photos = get_posts(array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => 'image',
            'posts_per_page' => 200,
            'orderby'        => array('menu_order' => 'ASC', 'ID' => 'ASC'),
            'meta_key'       => '_lnmc_gallery_event',
            'meta_value'     => $event,
        ));

        return array_values(array_filter($photos, static function ($photo) {
            return wp_attachment_is_image($photo);
        }));
    }

    /**
     * Responsive <img> markup for a gallery tile.
     *
     * WordPress' own srcset builder is capped by the requested size and happily
     * mixes in the cropped sizes other plugins register (400x250, 400x516 …),
     * which produces duplicate width descriptors. Building the candidate list
     * here keeps the descriptors unique and the aspect ratios consistent.
     *
     * The leading tile is the largest thing below the fold, so it is fetched
     * eagerly with a high priority instead of lazily like the rest.
     */
    private static function image_markup(int $attachment_id, string $alt, string $sizes, bool $priority = false): string
    {
        $full = wp_get_attachment_metadata($attachment_id);
        $src  = wp_get_attachment_image_src($attachment_id, 'full');

        if (! $full || ! $src) {
            return '';
        }

        $ratio      = $full['width'] / max(1, $full['height']);
        $candidates = array($full['width'] => $src[0]);

        foreach (($full['sizes'] ?? array()) as $size) {
            if (empty($size['width']) || empty($size['height'])) {
                continue;
            }

            $size_ratio = $size['width'] / $size['height'];
            if (abs($size_ratio - $ratio) > 0.02) {
                continue; // a crop, not a resize of the same frame
            }

            $candidates[$size['width']] = trailingslashit(dirname($src[0])) . $size['file'];
        }

        ksort($candidates);

        $srcset = array();
        foreach ($candidates as $width => $url) {
            $srcset[] = esc_url($url) . ' ' . $width . 'w';
        }

        // The 1024px file is the best all-round fallback: sharp enough for
        // desktop, and the browser overrides it from srcset whenever it can.
        $fallback = $candidates[1024] ?? $src[0];

        return sprintf(
            '<img src="%1$s" srcset="%2$s" sizes="%3$s" width="%4$d" height="%5$d" alt="%6$s" ' .
                'class="lnmc-gallery__img" loading="%7$s" decoding="async" fetchpriority="%8$s" draggable="false" />',
            esc_url($fallback),
            esc_attr(implode(', ', $srcset)),
            esc_attr($sizes),
            (int) $full['width'],
            (int) $full['height'],
            esc_attr($alt),
            $priority ? 'eager' : 'lazy',
            $priority ? 'high' : 'low'
        );
    }

    /**
     * [lnmc_gallery event="felmausa-inauguration-2026"]
     */
    public static function render(array $atts = array()): string
    {
        $atts = shortcode_atts(array(
            'event' => 'felmausa-inauguration-2026',
            'intro' => '',
        ), $atts, 'lnmc_gallery');

        $event  = sanitize_title($atts['event']);
        $photos = self::get_event_photos($event);
        $meta   = self::EVENTS[$event] ?? null;

        if (! $photos) {
            return current_user_can('edit_posts')
                ? '<p class="lnmc-gallery__empty">No photos tagged for <code>' . esc_html($event) . '</code> yet.</p>'
                : '';
        }

        // Shortcodes also run off-screen on other pages; only load assets where the gallery is shown.
        $page = get_queried_object();
        if ($page instanceof WP_Post && has_shortcode($page->post_content, 'lnmc_gallery')) {
            wp_enqueue_style('lnmc-gallery');
            wp_enqueue_script('lnmc-gallery');
        }

        $items    = array();
        $feature  = array(0, 5, 10); // double-width tiles that break up the grid
        $index    = 0;

        foreach ($photos as $photo) {
            $full  = wp_get_attachment_image_url($photo->ID, 'full');
            $dims  = wp_get_attachment_image_src($photo->ID, 'full');
            $alt   = get_post_meta($photo->ID, '_wp_attachment_image_alt', true);
            $cap   = wp_get_attachment_caption($photo->ID);
            $alt   = $alt ?: wp_strip_all_tags($photo->post_title);
            $wide  = in_array($index, $feature, true);

            $sizes = $wide
                ? '(min-width: 1200px) 860px, (min-width: 900px) 66vw, 100vw'
                : '(min-width: 1200px) 420px, (min-width: 900px) 33vw, (min-width: 560px) 50vw, 100vw';

            $img = self::image_markup($photo->ID, $alt, $sizes, 0 === $index);

            $items[] = sprintf(
                '<li class="lnmc-gallery__item%1$s">' .
                    '<figure class="lnmc-gallery__figure">' .
                    '<a class="lnmc-gallery__link" href="%2$s" data-caption="%3$s" data-full="%2$s" aria-label="%4$s">' .
                    '%5$s' .
                    '<span class="lnmc-gallery__expand" aria-hidden="true"></span>' .
                    '</a>' .
                    '%6$s' .
                    '</figure>' .
                    '</li>',
                $wide ? ' lnmc-gallery__item--wide' : '',
                esc_url($full),
                esc_attr($cap ?: $alt),
                esc_attr(sprintf('View larger image: %s', $alt)),
                $img,
                $cap ? '<figcaption class="lnmc-gallery__caption">' . esc_html($cap) . '</figcaption>' : ''
            );

            $index++;
        }

        $heading  = $meta['title'] ?? ucwords(str_replace('-', ' ', $event));
        $intro    = $atts['intro'] ?: ($meta['description'] ?? '');
        $organiser = $meta['organizer'] ?? null;

        $summary = '';
        if ($meta) {
            $bits = array();
            $bits[] = sprintf('<span class="lnmc-gallery__meta-date">%s</span>', esc_html(self::format_date_range($meta['date'], $meta['date_end'])));
            $bits[] = sprintf('<span class="lnmc-gallery__meta-place">%s</span>', esc_html($meta['location']['name']));
            if ($organiser) {
                $bits[] = sprintf(
                    '<a class="lnmc-gallery__meta-org" href="%s">%s</a>',
                    esc_url($organiser['url']),
                    esc_html($organiser['short'])
                );
            }
            $summary = '<p class="lnmc-gallery__meta">' . implode('<span aria-hidden="true" class="lnmc-gallery__dot">·</span>', $bits) . '</p>';
        }

        $html  = sprintf(
            '<section class="lnmc-gallery" id="%1$s" data-lnmc-gallery data-event="%1$s" aria-labelledby="%1$s-title">',
            esc_attr($event)
        );
        $html .= '<div class="lnmc-gallery__inner">';

        if ($meta) {
            $html .= '<header class="lnmc-gallery__header">';
            if (! empty($meta['subtitle'])) {
                $html .= '<p class="lnmc-gallery__eyebrow">' . esc_html($meta['subtitle']) . '</p>';
            }
            $html .= sprintf('<h2 class="lnmc-gallery__title" id="%1$s-title">%2$s</h2>', esc_attr($event), esc_html($heading));
            if ($intro) {
                $html .= '<p class="lnmc-gallery__lede">' . esc_html($intro) . '</p>';
            }
            $html .= $summary;
            $html .= '</header>';
        } elseif ($intro) {
            $html .= '<p class="lnmc-gallery__lede">' . esc_html($intro) . '</p>';
        }

        $html .= '<ul class="lnmc-gallery__grid" role="list">' . implode('', $items) . '</ul>';
        $html .= sprintf(
            '<p class="lnmc-gallery__count">%d photographs. Select any photo to view it full size.</p>',
            count($photos)
        );
        $html .= '</div></section>';

        $html .= self::schema($event, $heading, $photos, $meta);

        return $html;
    }

    private static function format_date_range(?string $start, ?string $end): string
    {
        if (! $start) {
            return '';
        }

        $start_ts = strtotime($start);
        $end_ts   = $end ? strtotime($end) : null;

        if (! $end_ts || gmdate('Y-m', $start_ts) !== gmdate('Y-m', $end_ts)) {
            return $end_ts && gmdate('Y', $start_ts) === gmdate('Y', $end_ts)
                ? gmdate('j F', $start_ts) . ' – ' . gmdate('j F Y', $end_ts)
                : gmdate('j F Y', $start_ts);
        }

        return gmdate('j', $start_ts) . '–' . gmdate('j F Y', $end_ts);
    }

    /**
     * ImageGallery structured data. Emitted inline with the section so the
     * image list always matches what is actually rendered on the page.
     */
    private static function schema(string $event, string $title, array $photos, ?array $meta): string
    {
        $images = array();

        foreach ($photos as $photo) {
            $src  = wp_get_attachment_image_src($photo->ID, 'full');
            $dims = wp_get_attachment_image_src($photo->ID, 'large');

            if (! $src) {
                continue;
            }

            $images[] = array(
                '@type'      => 'ImageObject',
                'contentUrl' => $src[0],
                'name'       => wp_strip_all_tags($photo->post_title),
                'caption'    => wp_strip_all_tags(wp_get_attachment_caption($photo->ID)),
                'width'      => $src[1],
                'height'     => $src[2],
                'thumbnailUrl' => $dims[0] ?? $src[0],
            );
        }

        $schema = array(
            '@context'    => 'https://schema.org',
            '@type'       => 'ImageGallery',
            'name'        => $title . ' — Photo Gallery',
            'url'         => get_permalink() . '#' . $event,
            'imageCount'  => count($images),
            'image'       => $images,
        );

        if ($meta) {
            $schema['description'] = $meta['description'];
            $schema['about']       = array(
                '@type'     => 'Event',
                'name'      => $meta['title'],
                'startDate' => $meta['date'],
                'endDate'   => $meta['date_end'] ?? $meta['date'],
                'eventStatus'   => 'https://schema.org/EventScheduled',
                'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                'location'  => array(
                    '@type'   => 'Place',
                    'name'    => $meta['location']['name'],
                    'address' => array(
                        '@type'           => 'PostalAddress',
                        'streetAddress'   => $meta['location']['street'],
                        'addressLocality' => $meta['location']['city'],
                        'addressRegion'   => $meta['location']['region'],
                        'addressCountry'  => $meta['location']['country'],
                    ),
                ),
            );

            if (! empty($meta['organizer'])) {
                $schema['about']['organizer'] = array(
                    '@type'         => 'Organization',
                    'name'          => $meta['organizer']['name'],
                    'alternateName' => $meta['organizer']['short'],
                    'url'           => $meta['organizer']['url'],
                );
            }
        }

        return '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
    }
}

LNMC_Gallery::init();
