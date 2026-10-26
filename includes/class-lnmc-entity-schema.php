<?php

/**
 * Entity schema JSON-LD output for Liberia reference pages.
 *
 * Reads the `_lnmc_entity` post meta on singular pages and emits a single
 * schema.org JSON-LD node describing the entity (government body, law, party,
 * organization, ethnic group or religious body).
 */

if (! defined('ABSPATH')) {
    exit;
}

class LNMC_Entity_Schema
{
    /**
     * Map non-standard schema.org types to valid ones.
     * Note: "EthnicGroup" is not a Schema.org type; we map it to "Thing".
     */
    const TYPE_MAP = array(
        'EthnicGroup' => 'Thing',
    );

    /**
     * Hook into wp_head.
     */
    public static function init(): void
    {
        add_action('wp_head', array(__CLASS__, 'render_schema'), 20);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_filter('body_class', array(__CLASS__, 'body_class'));
        add_action('wp', array(__CLASS__, 'disable_autop'));
    }

    /**
     * Reference pages are hand-authored HTML, often formatted with line
     * breaks inside paragraphs. wpautop would turn each of those into <br>.
     */
    public static function disable_autop(): void
    {
        if (self::is_entity_page()) {
            remove_filter('the_content', 'wpautop');
        }
    }

    /**
     * Reference pages are the only posts that carry _lnmc_entity, so the
     * meta doubles as the "is this a reference page" switch for styling.
     */
    private static function is_entity_page(): bool
    {
        return is_singular() && '' !== (string) get_post_meta(get_queried_object_id(), '_lnmc_entity', true);
    }

    public static function enqueue_assets(): void
    {
        if (! self::is_entity_page()) {
            return;
        }

        wp_enqueue_style(
            'lnmc-entity',
            get_stylesheet_directory_uri() . '/assets/css/lnmc-entity.css',
            array('lnmc-child-style'),
            filemtime(get_stylesheet_directory() . '/assets/css/lnmc-entity.css')
        );
    }

    public static function body_class(array $classes): array
    {
        if (self::is_entity_page()) {
            $classes[] = 'lnmc-entity-page';
        }

        return $classes;
    }

    /**
     * Print the JSON-LD script block if the current post has valid _lnmc_entity meta.
     */
    public static function render_schema(): void
    {
        if (! is_singular() || post_password_required()) {
            return;
        }

        $post_id = get_the_ID();
        if (! $post_id) {
            return;
        }

        $raw_meta = get_post_meta($post_id, '_lnmc_entity', true);
        if (empty($raw_meta)) {
            return;
        }

        $entity = is_array($raw_meta) ? $raw_meta : json_decode($raw_meta, true);
        if (! is_array($entity)) {
            return;
        }

        $type = isset($entity['type']) ? trim((string) $entity['type']) : '';
        $name = isset($entity['name']) ? trim((string) $entity['name']) : '';

        if ('' === $type || '' === $name) {
            return;
        }

        if (isset(self::TYPE_MAP[$type])) {
            $type = self::TYPE_MAP[$type];
        }

        $permalink = get_permalink($post_id);

        $node = array(
            '@context' => 'https://schema.org',
            '@type'    => $type,
            '@id'      => $permalink . '#entity',
            'name'     => $name,
        );

        if (! empty($entity['alternateName'])) {
            $alt = is_array($entity['alternateName'])
                ? array_values(array_filter(array_map('trim', $entity['alternateName'])))
                : array(trim((string) $entity['alternateName']));
            if (! empty($alt)) {
                $node['alternateName'] = (count($alt) === 1) ? $alt[0] : $alt;
            }
        }

        if (! empty($entity['sameAs'])) {
            $same_as = is_array($entity['sameAs']) ? $entity['sameAs'] : array($entity['sameAs']);
            $valid_same_as = array();
            foreach ($same_as as $url) {
                $url = trim((string) $url);
                if (0 === strpos($url, 'https://')) {
                    $valid_same_as[] = $url;
                }
            }
            if (! empty($valid_same_as)) {
                $node['sameAs'] = $valid_same_as;
            }
        }

        if (! empty($entity['foundingDate'])) {
            $node['foundingDate'] = trim((string) $entity['foundingDate']);
        }

        $node['subjectOf'] = array(
            '@type' => 'WebPage',
            '@id'   => $permalink,
        );

        $json = wp_json_encode($node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        if (! $json) {
            return;
        }

        echo "\n<!-- LNMC Entity Schema -->\n";
        echo '<script type="application/ld+json">' . $json . "</script>\n";
    }
}

LNMC_Entity_Schema::init();
