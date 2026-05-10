<?php

/**
 * Free Divi Child Theme by Pee-Aye Creative
 * Functions.php
 *
 * ===== NOTES ==================================================================
 * 
 * New to Divi? Take our full Divi course: https://www.peeayecreative.com/product/beyond-the-builder-the-ultimate-divi-website-course/
 * 
 * Learn cool tricks and features with our Divi tutorials: https://www.peeayecreative.com/blog/
 * 
 * Discover our premium Divi products: https://www.peeayecreative.com/shop/
 * 
 * =============================================================================== */

/**
 * LNMC AI Chat Assistant — REST endpoint + admin settings
 */
require_once get_stylesheet_directory() . '/includes/class-lnmc-ai-chat.php';

function divichild_enqueue_scripts()
{
    // Brand fonts: Amiri (headings) + Source Sans 3 (body) per client brief.
    // Includes Playfair Display + Space Grotesk as documented fallbacks for
    // any Divi module that explicitly references them. Owned by the THEME
    // (not the membership plugin) so font availability survives plugin
    // upgrades or replacement.
    wp_enqueue_style(
        'lnmc-fonts',
        'https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Source+Sans+3:wght@300;400;500;600;700&family=Playfair+Display:wght@400;600;700;900&family=Space+Grotesk:wght@300;400;500;600;700&display=swap',
        array(),
        null
    );
    // Cache-bust on every change to avoid serving stale CSS/JS while iterating.
    $asset_ver = filemtime(get_stylesheet_directory() . '/style.css');
    wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('lnmc-child-style', get_stylesheet_directory_uri() . '/style.css', array('parent-style'), $asset_ver);

    wp_enqueue_script('custom-js', get_stylesheet_directory_uri() . '/js/scripts.js', array('jquery'), filemtime(get_stylesheet_directory() . '/js/scripts.js'), true);
    wp_enqueue_script('lnmc-animations', get_stylesheet_directory_uri() . '/js/lnmc-animations.js', array(), filemtime(get_stylesheet_directory() . '/js/lnmc-animations.js'), true);

    // lnmc-design.js owns the homepage presentation layer that used to live
    // inside the membership plugin. We declare a soft dependency on the
    // plugin's data-only handle (`lnmc-design-data`) when registered — that
    // way the cultural-image swap has its data, and if the plugin is gone
    // the script still loads and silently no-ops the swap step.
    $design_deps = array();
    if (wp_script_is('lnmc-design-data', 'registered')) {
        $design_deps[] = 'lnmc-design-data';
    }
    wp_enqueue_script(
        'lnmc-design',
        get_stylesheet_directory_uri() . '/js/lnmc-design.js',
        $design_deps,
        filemtime(get_stylesheet_directory() . '/js/lnmc-design.js'),
        true
    );

    // Chat widget — enqueue only when enabled and we're not in the admin.
    // FAQ mode works without a key, so `configured` is always true when the
    // widget is on; the server decides whether to proxy to OpenAI or reply
    // from the local FAQ table.
    if (! is_admin() && get_option('lnmc_chat_enabled', '0') === '1') {
        wp_enqueue_script('lnmc-chat', get_stylesheet_directory_uri() . '/js/lnmc-chat.js', array(), filemtime(get_stylesheet_directory() . '/js/lnmc-chat.js'), true);
        wp_localize_script('lnmc-chat', 'lnmc_chat_obj', array(
            'root'       => esc_url_raw(rest_url()),
            'nonce'      => wp_create_nonce('wp_rest'),
            'whatsapp'   => get_option('lnmc_whatsapp_number', ''),
            'mode'       => get_option('lnmc_ai_mode', 'faq'),
            'configured' => true,
        ));
    }
}
// Priority 999 ensures the child theme stylesheet loads AFTER the Divi
// customizer's cached inline <style> block, so our overrides win at equal
// specificity. (Customizer cached styles are output around priority ~30.)
add_action('wp_enqueue_scripts', 'divichild_enqueue_scripts', 999);

/**
 * Defer the animation JS since it only decorates, never blocks
 */
add_filter('script_loader_tag', function ($tag, $handle) {
    // lnmc-design only decorates — defer so it never blocks first paint.
    if (in_array($handle, array('lnmc-animations', 'lnmc-chat', 'lnmc-design'), true)) {
        return str_replace(' src=', ' defer src=', $tag);
    }
    return $tag;
}, 10, 2);


/* ========================================
   ACCESSIBILITY & UX FIXES
   ======================================== */

/**
 * FIX: Remove Divi's viewport zoom restrictions
 * Divi adds maximum-scale=1.0 and user-scalable=0, which fails WCAG
 * This removes the Divi function and provides the correct viewport tag
 */
add_action('init', function () {
    remove_action('wp_head', 'et_add_viewport_meta');
});

add_action('wp_head', function () {
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=1" />' . "\n";
}, 0);

/**
 * Performance: preload the homepage hero still used by the CSS motion layer.
 * Scoped to the front page so inner pages do not download hero media.
 */
add_action('wp_head', function () {
    if (! is_front_page()) {
        return;
    }

    echo '<link rel="preload" as="image" href="' . esc_url(content_url('uploads/2026/04/heritage-preservation-project-1024x683.jpg')) . '" imagesrcset="' . esc_url(content_url('uploads/2026/04/heritage-preservation-project-768x512.jpg')) . ' 768w, ' . esc_url(content_url('uploads/2026/04/heritage-preservation-project-1024x683.jpg')) . ' 1024w" imagesizes="100vw" fetchpriority="high" />' . "\n";
}, 1);

/**
 * FIX: Add Skip-to-Content Link for keyboard navigation
 */
add_action('wp_body_open', function () {
    echo '<a href="#main-content" class="skip-link">Skip to main content</a>' . "\n";
});

/**
 * FIX: Add aria-label to search button
 */
add_filter('et_pb_module_shortcode_output', function ($output, $render_count) {
    if (strpos($output, 'et_pb_menu__search-button') !== false) {
        $output = str_replace(
            'class="et_pb_menu__search-button"',
            'class="et_pb_menu__search-button" aria-label="Search" title="Search the site"',
            $output
        );
    }
    return $output;
}, 10, 2);

/**
 * FIX: Ensure main landmark is present
 * Wraps main content in <main> tag for accessibility
 */
add_action('get_template_part_page', function () {
    ob_start();
}, 1);

add_action('et_before_main_content', function () {
    echo '<main id="main-content" class="main-content" role="main">' . "\n";
});

add_action('et_after_main_content', function () {
    echo '</main><!-- .main-content -->' . "\n";
});

/**
 * SEO: Organization + WebSite JSON-LD for every page
 * RankMath will emit its own schema too; this is an explicit Organization entity
 * that both RankMath and plain crawlers can discover without editor work.
 */
add_action('wp_head', function () {
    $site_url   = esc_url(home_url('/'));
    $site_name  = esc_html(get_bloginfo('name'));
    $site_desc  = esc_html(get_bloginfo('description'));
    $logo_url   = esc_url(has_custom_logo() ? wp_get_attachment_image_url(get_theme_mod('custom_logo'), 'full') : '');

    $org = array(
        '@context'    => 'https://schema.org',
        '@type'       => 'NGO',
        '@id'         => $site_url . '#org',
        'name'        => 'Liberia National Muslim Council Diaspora Organization',
        'alternateName' => array('LNMC Diaspora', 'LNMCDO'),
        'url'         => $site_url,
        'description' => $site_desc ?: 'Uniting Liberian Muslims Worldwide through education, heritage preservation, and community advocacy.',
        'foundingDate' => '1993',
        'areaServed'  => array(
            array('@type' => 'Continent', 'name' => 'Africa'),
            array('@type' => 'Continent', 'name' => 'Europe'),
            array('@type' => 'Continent', 'name' => 'North America'),
            array('@type' => 'Continent', 'name' => 'Asia'),
        ),
        'sameAs'      => array(
            'https://www.facebook.com/LNMCDispora',
        ),
    );
    if ($logo_url) {
        $org['logo'] = $logo_url;
        $org['image'] = $logo_url;
    }

    $website = array(
        '@context' => 'https://schema.org',
        '@type'    => 'WebSite',
        '@id'      => $site_url . '#website',
        'url'      => $site_url,
        'name'     => $site_name,
        'publisher' => array('@id' => $site_url . '#org'),
        'potentialAction' => array(
            '@type'       => 'SearchAction',
            'target'      => $site_url . '?s={search_term_string}',
            'query-input' => 'required name=search_term_string',
        ),
    );

    echo "\n<script type=\"application/ld+json\">" . wp_json_encode(array($org, $website)) . "</script>\n";
}, 5);

/**
 * SEO: Output social OG tags when RankMath hasn't set them.
 * Harmless fallback — RankMath's versions win on pages where it sets them.
 */
add_action('wp_head', function () {
    if (is_admin() || defined('REST_REQUEST')) return;
    if (defined('RANK_MATH_VERSION') || function_exists('rank_math') || class_exists('RankMath')) {
        return;
    }
    global $post;
    $title = wp_get_document_title();
    $desc  = is_singular() && $post ? wp_strip_all_tags(wp_trim_words($post->post_content, 30)) : get_bloginfo('description');
    $url   = is_singular() ? get_permalink() : home_url(add_query_arg(null, null));
    echo "<meta property=\"og:site_name\" content=\"" . esc_attr(get_bloginfo('name')) . "\" />\n";
    echo "<meta property=\"og:title\" content=\"" . esc_attr($title) . "\" />\n";
    echo "<meta property=\"og:description\" content=\"" . esc_attr($desc) . "\" />\n";
    echo "<meta property=\"og:url\" content=\"" . esc_url($url) . "\" />\n";
    echo "<meta property=\"og:type\" content=\"" . (is_singular('post') ? 'article' : 'website') . "\" />\n";
}, 6);

/**
 * Cultural-image substitution filter.
 *
 * The site has a handful of legacy AI-generated stock photos uploaded
 * during early prototyping that depict white people with West African
 * Muslim names attached (e.g. blonde woman labelled "Fatima Jalloh,
 * Community Leader"). The site is for the Liberian Mandingo Muslim
 * diaspora — those images violate the cultural guidelines.
 *
 * Rather than ask the editor to find every Divi module that references
 * each old image, we rewrite the URLs at render time. The map is the
 * single source of truth; once the editor updates Divi modules to point
 * at the new files directly, entries can be deleted from the map.
 *
 * Also covers /about/ where one generic "networking-professionals"
 * group photo was being reused for three different individual leader
 * portraits — clear case of duplicate use of the same image.
 */
function lnmc_get_image_replacements(): array
{
    $base = home_url('/wp-content/uploads/2026/05/');
    return array(
        // Inappropriate AI portraits → distinct West African Muslim portraits
        'new-portrait-12-800x800-1-1-800x800.jpg' => $base . 'lnmc-fatima-jalloh-leader.jpg',
        'new-portrait-12-800x800-1-3-800x800.jpg' => $base . 'lnmc-fatima-jalloh-leader.jpg',
        'new-portrait-8-800x800-1-2-800x800.jpg'  => $base . 'lnmc-ahmed-kamara-director.jpg',
        'new-portrait-8-800x800-1-4-800x800.jpg'  => $base . 'lnmc-ahmed-kamara-director.jpg',
    );
}

/**
 * Rewrite image URLs inside post/page content as Divi renders shortcodes.
 * Runs at priority 99 so it sees the final HTML after Divi has built it.
 *
 * Single-pass full-URL rewrite — earlier two-pass version had a bug where
 * the basename pass clobbered the path before the full-URL pass could see
 * the original needle.
 */
add_filter('the_content', function ($html) {
    $rewrites = array(
        // Inappropriate AI portraits → distinct West African Muslim portraits
        'wp-content/uploads/2025/08/new-portrait-12-800x800-1-1-800x800.jpg'
        => 'wp-content/uploads/2026/05/lnmc-fatima-jalloh-leader.jpg',
        'wp-content/uploads/2025/08/new-portrait-12-800x800-1-3-800x800.jpg'
        => 'wp-content/uploads/2026/05/lnmc-fatima-jalloh-leader.jpg',
        'wp-content/uploads/2025/08/new-portrait-8-800x800-1-2-800x800.jpg'
        => 'wp-content/uploads/2026/05/lnmc-ahmed-kamara-director.jpg',
        'wp-content/uploads/2025/08/new-portrait-8-800x800-1-4-800x800.jpg'
        => 'wp-content/uploads/2026/05/lnmc-ahmed-kamara-director.jpg',
    );
    foreach ($rewrites as $old => $new) {
        $html = str_replace($old, $new, $html);
    }
    return $html;
}, 99);

/**
 * Same rewrite for any rendered <img> the theme outputs outside of the
 * main content (e.g. featured images, blurb thumbnails).
 */
add_filter('wp_get_attachment_image_src', function ($image, $attachment_id, $size, $icon) {
    if (! is_array($image) || empty($image[0])) return $image;
    $rewrites = array(
        '/uploads/2025/08/new-portrait-12-800x800-1-1-800x800.jpg' => '/uploads/2026/05/lnmc-fatima-jalloh-leader.jpg',
        '/uploads/2025/08/new-portrait-12-800x800-1-3-800x800.jpg' => '/uploads/2026/05/lnmc-fatima-jalloh-leader.jpg',
        '/uploads/2025/08/new-portrait-8-800x800-1-2-800x800.jpg'  => '/uploads/2026/05/lnmc-ahmed-kamara-director.jpg',
        '/uploads/2025/08/new-portrait-8-800x800-1-4-800x800.jpg'  => '/uploads/2026/05/lnmc-ahmed-kamara-director.jpg',
    );
    foreach ($rewrites as $old => $new) {
        if (strpos($image[0], $old) !== false) {
            $image[0] = str_replace($old, $new, $image[0]);
            break;
        }
    }
    return $image;
}, 10, 4);

function lnmc_image_alt_from_src(string $src): string
{
    $name = strtolower(basename(strtok($src, '?') ?: $src));

    $alts = array(
        'fatima-johnson-abuja'              => 'Fatima Johnson, LNMC diaspora community member in Abuja',
        'ahmed-kamara-kufi'                 => 'Ahmed Kamara wearing a kufi at an LNMC diaspora gathering',
        'aisha-conteh-smiling'              => 'Aisha Conteh smiling during an LNMC community event',
        'zainab-koroma-portrait'            => 'Zainab Koroma, LNMC diaspora member portrait',
        'mohammed-sesay-mosque'             => 'Mohammed Sesay outside a mosque after community prayers',
        'dr-brima-sylla-prayer-beads'       => 'Dr. Brima Sylla holding prayer beads',
        'dr-omar-abdallah-dukuly-dakar'     => 'Dr. Omar Abdallah Dukuly in Dakar',
        'dr-ibrahim-fofana-quran'           => 'Dr. Ibrahim Fofana reading the Quran',
        'fatima-jalloh-graduation'          => 'Fatima Jalloh at a graduation ceremony',
        'abdul-kamara-community'            => 'Abdul Kamara at an LNMC community gathering',
        'mariam-conteh-classroom'           => 'Mariam Conteh in a classroom learning environment',
        'youth-education-initiative'        => 'Youth education initiative led by the LNMC diaspora',
        'heritage-preservation-project'     => 'Heritage preservation project with Liberian Muslim community members',
        'economic-empowerment-program'      => 'Economic empowerment program for LNMC diaspora families',
        'lnmc-fatima-jalloh-leader'         => 'Fatima Jalloh, LNMC community leader',
        'lnmc-ahmed-kamara-director'        => 'Ahmed Kamara, LNMC diaspora director',
        'lnmc-dr-brima-sylla'               => 'Dr. Brima Sylla, LNMC diaspora leader',
        'lnmc-dr-omar-dukuly'               => 'Dr. Omar Abdallah Dukuly, LNMC diaspora leader',
        'lnmc-dr-ibrahim-fofana'            => 'Dr. Ibrahim Fofana, LNMC diaspora leader',
    );

    foreach ($alts as $needle => $alt) {
        if (strpos($name, $needle) !== false) {
            return $alt;
        }
    }

    if (preg_match('/^0-[a-f0-9-]+-/', $name)) {
        return 'LNMC diaspora community program photo';
    }

    return 'LNMC Diaspora community image';
}

add_filter('wp_get_attachment_image_attributes', function ($attr, $attachment, $size) {
    if (! empty($attr['alt']) || empty($attr['src'])) {
        return $attr;
    }

    $attr['alt'] = lnmc_image_alt_from_src($attr['src']);
    return $attr;
}, 10, 3);

add_filter('the_content', function ($html) {
    if (stripos($html, '<img') === false) {
        return $html;
    }

    $approved = array(
        'fatima-johnson-abuja.jpg',
        'aisha-conteh-smiling.jpg',
        'zainab-koroma-portrait.jpg',
        'ahmed-kamara-kufi.jpg',
        'mohammed-sesay-mosque.jpg',
        'dr-brima-sylla-prayer-beads.jpg',
        'dr-omar-abdallah-dukuly-dakar.jpg',
        'dr-ibrahim-fofana-quran.jpg',
        'fatima-jalloh-graduation.jpg',
        'abdul-kamara-community.jpg',
        'mariam-conteh-classroom.jpg',
        'youth-education-initiative.jpg',
        'heritage-preservation-project.jpg',
        'economic-empowerment-program.jpg',
    );
    $seen = array();

    return preg_replace_callback('/<img\b[^>]*>/i', function ($m) use (&$seen, $approved) {
        $tag = $m[0];
        $src = '';

        if (preg_match('/\ssrc=(["\'])(.*?)\1/i', $tag, $sm)) {
            $src = $sm[2];
        }

        if (strpos($src, 'data:image/svg+xml') === 0) {
            $tag = preg_match('/\salt=/i', $tag) ? $tag : preg_replace('/<img\b/i', '<img alt=""', $tag, 1);
            $tag = preg_match('/\saria-hidden=/i', $tag) ? $tag : preg_replace('/<img\b/i', '<img aria-hidden="true"', $tag, 1);
            return preg_match('/\srole=/i', $tag) ? $tag : preg_replace('/<img\b/i', '<img role="presentation"', $tag, 1);
        }

        $basename = basename(strtok($src, '?') ?: $src);
        if ($basename && in_array($basename, $approved, true)) {
            if (isset($seen[$basename])) {
                foreach ($approved as $candidate) {
                    if (! isset($seen[$candidate])) {
                        $replacement = content_url('uploads/2026/04/' . $candidate);
                        $tag = preg_replace('/\ssrc=(["\'])(.*?)\1/i', ' src="' . esc_url($replacement) . '"', $tag, 1);
                        $tag = preg_replace('/\ssrcset=(["\'])(.*?)\1/i', '', $tag);
                        $tag = preg_replace('/\ssizes=(["\'])(.*?)\1/i', '', $tag);
                        $src = $replacement;
                        $basename = $candidate;
                        break;
                    }
                }
            }
            $seen[$basename] = true;
        }

        $alt = esc_attr(lnmc_image_alt_from_src($src ?: $basename));
        if (preg_match('/\salt=(["\'])(.*?)\1/i', $tag, $am)) {
            if (trim($am[2]) === '') {
                return preg_replace('/\salt=(["\'])(.*?)\1/i', ' alt="' . $alt . '"', $tag, 1);
            }
            return $tag;
        }

        return preg_replace('/<img\b/i', '<img alt="' . $alt . '"', $tag, 1);
    }, $html);
}, 100);

/**
 * /about/ page leaders: the same group photo was attached to three
 * different leader cards. Use a separate JS-side swap because the leader
 * names live in the same DOM as the image and we need to match them to
 * the right portrait by alt/heading text. This filter rewrites every
 * occurrence of the duplicated group photo to a stable interim URL the
 * client-side script then routes per-leader.
 *
 * The actual per-leader assignment happens in lnmc-design.js so the JS
 * can read the alt text / nearby heading and pick the matching unique
 * portrait. No two leaders end up with the same photo.
 */
/**
 * Google Analytics 4 — optional, off by default.
 * Define LNMC_GA4_ID in wp-config.php (e.g. 'G-XXXXXXXXXX') to activate.
 * Respects logged-in admins (skips tracking) and honours Do-Not-Track.
 */
add_action('wp_head', function () {
    if (! defined('LNMC_GA4_ID') || empty(LNMC_GA4_ID)) return;
    if (current_user_can('manage_options')) return;
    if (! empty($_SERVER['HTTP_DNT']) && $_SERVER['HTTP_DNT'] === '1') return;
    $id = esc_js(LNMC_GA4_ID);
?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $id; ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());
        gtag('config', '<?php echo $id; ?>', {
            anonymize_ip: true
        });
    </script>
<?php
}, 99);

//you can add custom functions below this line:
