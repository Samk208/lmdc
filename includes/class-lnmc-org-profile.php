<?php

/**
 * Sister-organization contact panel.
 *
 * The lnmc-community-partners plugin registers `website`, `email`, `facebook`,
 * `twitter`, `linkedin`, `youtube`, `whatsapp` and `telegram` meta for every
 * organization, and schema.php passes them to `sameAs` — but nothing ever
 * prints them. The directory card only offers "Visit Website", so a reader who
 * wants to reach an organization has no route except guessing.
 *
 * This appends one panel to the bottom of each organization page. It reads
 * only the plugin's own meta keys, so the plugin stays untouched and the panel
 * inherits whatever the editor saves in the admin metabox.
 */

if (! defined('ABSPATH')) {
    exit;
}

class LNMC_Org_Profile
{
    /**
     * Link meta in display order. `kind` decides how the value is turned into
     * an href and what the secondary line says.
     */
    const LINKS = array(
        'website'  => array('label' => 'Website', 'icon' => '🌐', 'kind' => 'url'),
        'email'    => array('label' => 'Email', 'icon' => '📧', 'kind' => 'email'),
        'facebook' => array('label' => 'Facebook', 'icon' => '👥', 'kind' => 'url'),
        'twitter'  => array('label' => 'X / Twitter', 'icon' => '💬', 'kind' => 'url'),
        'linkedin' => array('label' => 'LinkedIn', 'icon' => '💼', 'kind' => 'url'),
        'youtube'  => array('label' => 'YouTube', 'icon' => '▶️', 'kind' => 'url'),
        'whatsapp' => array('label' => 'WhatsApp', 'icon' => '📱', 'kind' => 'url'),
        'telegram' => array('label' => 'Telegram', 'icon' => '✈️', 'kind' => 'url'),
    );

    /**
     * Mirrors the whitelist in the plugin's meta.php so the panel and the
     * schema block describe the organization the same way.
     */
    const LEGAL_STATUS = array(
        'registered'   => 'Registered organization',
        'non-profit'   => 'Non-profit',
        'charity'      => 'Registered charity',
        'association'  => 'Association',
        'foundation'   => 'Foundation',
        'corporation'  => 'Corporation',
        'unregistered' => 'Unregistered',
        'other'        => 'Organization',
    );

    public static function init(): void
    {
        // Conditional and cheap, so the stylesheet can load in <head> instead
        // of being enqueued too late from the filter and causing a flash of
        // unstyled content.
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_filter('the_content', array(__CLASS__, 'append_panel'), 12);
    }

    public static function enqueue_assets(): void
    {
        if (! is_singular('organization')) {
            return;
        }

        wp_enqueue_style(
            'lnmc-org-profile',
            get_stylesheet_directory_uri() . '/assets/css/lnmc-org-profile.css',
            array(),
            filemtime(get_stylesheet_directory() . '/assets/css/lnmc-org-profile.css')
        );
    }

    /**
     * Append the panel once, to the main organization body only.
     *
     * @param string $content Post content.
     * @return string
     */
    public static function append_panel($content): string
    {
        if (
            ! is_singular('organization')
            || ! in_the_loop()
            || ! is_main_query()
            || doing_filter('et_builder_render_layout')
        ) {
            return $content;
        }

        $panel = self::render(get_the_ID());

        if ('' === $panel) {
            return $content;
        }

        return $content . $panel;
    }

    /**
     * Build the panel. Returns an empty string when the record has neither a
     * link nor a locatable fact, which keeps bare listings clean.
     *
     * @param int $post_id Organization post ID.
     */
    public static function render(int $post_id): string
    {
        $links = self::collect_links($post_id);
        $facts = self::collect_facts($post_id);

        if (empty($links) && empty($facts)) {
            return '';
        }

        $out = '<section class="lnmc-org-contact" aria-labelledby="lnmc-org-contact-title">';
        $out .= '<h2 class="lnmc-org-contact__title" id="lnmc-org-contact-title">Contact &amp; links</h2>';

        if (! empty($links)) {
            $out .= '<ul class="lnmc-org-contact__list">';

            foreach ($links as $link) {
                $out .= sprintf(
                    '<li class="lnmc-org-contact__item"><span class="lnmc-org-contact__icon" aria-hidden="true">%1$s</span>'
                        . '<a class="lnmc-org-contact__link" href="%2$s"%3$s>'
                        . '<span class="lnmc-org-contact__label">%4$s</span>'
                        . '<span class="lnmc-org-contact__value">%5$s</span>'
                        . '</a></li>',
                    $link['icon'],
                    esc_url($link['href']),
                    'mailto:' === substr($link['href'], 0, 7) ? '' : ' target="_blank" rel="noopener noreferrer"',
                    esc_html($link['label']),
                    esc_html($link['value'])
                );
            }

            $out .= '</ul>';
        }

        if (! empty($facts)) {
            $out .= '<dl class="lnmc-org-facts">';

            foreach ($facts as $label => $value) {
                $out .= '<div class="lnmc-org-facts__row">'
                    . '<dt class="lnmc-org-facts__term">' . esc_html($label) . '</dt>'
                    . '<dd class="lnmc-org-facts__value">' . esc_html($value) . '</dd>'
                    . '</div>';
            }

            $out .= '</dl>';
        }

        $out .= '<p class="lnmc-org-contact__note">Directory entry last reviewed October 2026. '
            . 'Confirm programmes, leadership and contact details with the organization directly before relying on them.</p>';
        $out .= '</section>';

        return $out;
    }

    /**
     * Turn the stored meta into a de-duplicated list of printable links.
     *
     * @param int $post_id Organization post ID.
     * @return array<int, array<string, string>>
     */
    private static function collect_links(int $post_id): array
    {
        $links = array();
        $seen  = array();

        foreach (self::LINKS as $key => $config) {
            $raw = trim((string) get_post_meta($post_id, $key, true));

            if ('' === $raw) {
                continue;
            }

            if ('email' === $config['kind']) {
                $href  = 'mailto:' . sanitize_email($raw);
                $value = $raw;
            } else {
                $href  = esc_url_raw($raw);
                $value = self::short_url($href);
            }

            if ('' === $href || isset($seen[$href])) {
                continue;
            }

            $seen[$href] = true;

            $links[] = array(
                'href'  => $href,
                'label' => self::link_label($config['label'], $href),
                'value' => $value,
                'icon'  => $config['icon'],
            );
        }

        return $links;
    }

    /**
     * The stored city/country/founding/status facts, in the order a reader
     * would look for them.
     *
     * @param int $post_id Organization post ID.
     * @return array<string, string>
     */
    private static function collect_facts(int $post_id): array
    {
        $facts = array();

        $city    = trim((string) get_post_meta($post_id, 'city', true));
        $country = trim((string) get_post_meta($post_id, 'country', true));
        $place   = implode(', ', array_filter(array($city, $country)));

        if ('' !== $place) {
            $facts['Location'] = $place;
        }

        $founded = trim((string) get_post_meta($post_id, 'founded_year', true));

        if ('' !== $founded) {
            $facts['Founded'] = $founded;
        }

        $status = trim((string) get_post_meta($post_id, 'legal_status', true));

        if ('' !== $status && isset(self::LEGAL_STATUS[$status])) {
            $facts['Legal status'] = self::LEGAL_STATUS[$status];
        }

        $acronym = trim((string) get_post_meta($post_id, 'acronym', true));

        if ('' !== $acronym) {
            $facts['Also known as'] = $acronym;
        }

        return $facts;
    }

    /**
     * A group membership link and a page link are different things, and the
     * reader can see which one they are about to open.
     */
    private static function link_label(string $default, string $href): string
    {
        if ('Facebook' !== $default) {
            return $default;
        }

        return false !== strpos($href, '/groups/') ? 'Facebook group' : 'Facebook page';
    }

    /**
     * Reduce a URL to the part a reader recognizes: host without `www` plus
     * the profile handle, which is the bit that distinguishes two pages on the
     * same network.
     */
    private static function short_url(string $url): string
    {
        $host = wp_parse_url($url, PHP_URL_HOST);

        if (empty($host)) {
            return $url;
        }

        $host = preg_replace('/^www\./', '', $host);
        $path = trim((string) wp_parse_url($url, PHP_URL_PATH), '/');

        if ('' === $path) {
            return $host;
        }

        // A numeric profile ID is meaningless to a reader and wraps mid-number
        // in the card. The label already names the network, so keep the host.
        if (preg_match('/^\d+$/', $path)) {
            return $host;
        }

        return $host . '/' . $path;
    }
}

LNMC_Org_Profile::init();
