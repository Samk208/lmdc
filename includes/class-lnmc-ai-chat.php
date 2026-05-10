<?php

/**
 * LNMC Diaspora AI Chatbot — OpenAI proxy + FAQ fallback
 *
 * Two modes controlled by the `lnmc_ai_mode` option:
 *   - 'openai' : proxy user messages to OpenAI chat/completions (key required)
 *   - 'faq'    : zero-cost keyword-matched FAQ responder (no key required)
 *
 * Shared hardening (both modes):
 *   - Nonce verification (wp_rest)
 *   - 10 req/min/IP rate limit via transient
 *   - Honeypot field `lnmc_hp` silently absorbs bot submissions
 *   - Credit-card / SSN patterns short-circuit before any network call
 *   - Capped PII-scrubbed log in `lnmc_ai_log` (last 50 entries) for audit
 */

if (! defined('ABSPATH')) {
    exit;
}

class LNMC_AI_Chat
{

    const MODE_OPENAI = 'openai';
    const MODE_FAQ    = 'faq';
    const LOG_OPTION  = 'lnmc_ai_log';
    const LOG_MAX     = 50;

    private $api_url = 'https://api.openai.com/v1/chat/completions';

    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes()
    {
        register_rest_route('lnmc/v1', '/chat', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_chat_request'),
            'permission_callback' => '__return_true',
        ));
    }

    public function handle_chat_request($request)
    {

        // 1. Nonce check
        $nonce = $request->get_header('X-WP-Nonce');
        if (! wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('rest_forbidden', 'Invalid security token', array('status' => 403));
        }

        // 2. Rate limit — 10 requests per minute per IP via transient
        $ip  = isset($_SERVER['REMOTE_ADDR']) ? preg_replace('/[^0-9a-f:.]/i', '', $_SERVER['REMOTE_ADDR']) : 'unknown';
        $key = 'lnmc_chat_rl_' . md5($ip);
        $hits = (int) get_transient($key);
        if ($hits >= 10) {
            return new WP_Error('rate_limited', 'Please slow down — try again in a minute.', array('status' => 429));
        }
        set_transient($key, $hits + 1, MINUTE_IN_SECONDS);

        // 3. Parse body
        $params = $request->get_json_params();
        if (empty($params) || ! is_array($params)) {
            return new WP_Error('invalid_json', 'Invalid JSON body', array('status' => 400));
        }

        // 4. Honeypot — bots commonly fill every field. Humans never see it.
        if (! empty($params['lnmc_hp'])) {
            $this->log($ip, '[honeypot]', 'silent-accept', 'faq');
            return new WP_REST_Response(array('reply' => 'Thanks — we will be in touch.'), 200);
        }

        $message = isset($params['message']) ? sanitize_text_field($params['message']) : '';
        $history = isset($params['history']) && is_array($params['history']) ? $params['history'] : array();

        if (empty($message)) {
            return new WP_Error('no_message', 'Message is required', array('status' => 400));
        }

        // 5. Safety: reject payment-card / SSN-like patterns before any downstream
        if (
            preg_match('/\b(?:\d[ -]?){13,16}\b/', $message)
            || preg_match('/\b\d{3}-\d{2}-\d{4}\b/', $message)
        ) {
            $reply = 'For your security, please don\'t share payment or ID numbers in chat. Visit the Membership or Donate page for secure payment, or Contact us for anything else.';
            $this->log($ip, $message, $reply, 'safety');
            return new WP_REST_Response(array('reply' => $reply), 200);
        }

        // 6. Route by configured mode
        $mode = get_option('lnmc_ai_mode', self::MODE_FAQ);

        if ($mode === self::MODE_OPENAI) {
            return $this->handle_openai($message, $history, $ip);
        }

        // Default: FAQ mode (zero cost, no key needed)
        $reply = $this->faq_reply($message);
        $this->log($ip, $message, $reply, 'faq');
        return new WP_REST_Response(array('reply' => $reply), 200);
    }

    /**
     * OpenAI proxy path.
     */
    private function handle_openai($message, $history, $ip)
    {
        $api_key       = get_option('lnmc_ai_api_key');
        $system_prompt = get_option('lnmc_ai_system_prompt', $this->default_system_prompt());
        $model         = get_option('lnmc_ai_model', 'gpt-4o-mini');

        if (empty($api_key)) {
            // Gracefully fall back to FAQ so the widget never looks broken.
            $reply = $this->faq_reply($message);
            $this->log($ip, $message, $reply, 'faq-fallback-no-key');
            return new WP_REST_Response(array('reply' => $reply), 200);
        }

        $messages   = array();
        $messages[] = array('role' => 'system', 'content' => $system_prompt);

        foreach ($history as $msg) {
            if (isset($msg['role'], $msg['content'])) {
                $role = in_array($msg['role'], array('user', 'assistant'), true) ? $msg['role'] : 'user';
                $messages[] = array(
                    'role'    => $role,
                    'content' => sanitize_text_field($msg['content']),
                );
            }
        }
        $messages[] = array('role' => 'user', 'content' => $message);

        $body = array(
            'model'       => $model,
            'messages'    => $messages,
            'max_tokens'  => 400,
            'temperature' => 0.7,
        );

        $response = wp_remote_post($this->api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode($body),
            'timeout' => 20,
        ));

        if (is_wp_error($response)) {
            // Network failure — fall back to FAQ rather than showing an error.
            $reply = $this->faq_reply($message);
            $this->log($ip, $message, $reply, 'faq-fallback-network');
            return new WP_REST_Response(array('reply' => $reply), 200);
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($response_body['error'])) {
            $err = isset($response_body['error']['message']) ? $response_body['error']['message'] : 'OpenAI error';
            $this->log($ip, $message, '[openai error] ' . $err, 'openai-error');
            $reply = $this->faq_reply($message);
            return new WP_REST_Response(array('reply' => $reply), 200);
        }

        $bot_reply = isset($response_body['choices'][0]['message']['content'])
            ? $response_body['choices'][0]['message']['content']
            : $this->faq_reply($message);

        $this->log($ip, $message, $bot_reply, 'openai');
        return new WP_REST_Response(array('reply' => $bot_reply), 200);
    }

    /**
     * FAQ responder — lightweight keyword match against a seeded knowledge base.
     * Returns a useful reply for every input, falling through to a
     * "contact / WhatsApp" handover suggestion when nothing matches.
     */
    private function faq_reply($message)
    {
        $m = strtolower(trim($message));
        foreach ($this->faq_entries() as $entry) {
            foreach ($entry['keywords'] as $kw) {
                if (strpos($m, $kw) !== false) {
                    return $entry['answer'];
                }
            }
        }
        return "I can help with membership, chapters, events, donations, scholarships, and resources. Could you rephrase, or tap the WhatsApp button at the top of this window to talk to a person?";
    }

    /**
     * Curated knowledge base. Keywords are lowercase substrings — order
     * matters: the first matching entry wins, so place specific items
     * (e.g. "scholarship") before generic ones (e.g. "join").
     */
    private function faq_entries()
    {
        $site = home_url('/');
        return array(
            array(
                'keywords' => array('salam', 'salaam', 'assalam', 'asalam', 'as-salamu'),
                'answer'   => 'Wa alaykum as-salam wa rahmatullah. Welcome to LNMC Diaspora. How can I help you today?',
            ),
            array(
                'keywords' => array('hello', 'hi ', 'hey', 'good morning', 'good evening', 'good afternoon'),
                'answer'   => 'Hello and welcome to the LNMC Diaspora assistant. I can help with membership, chapters, events, donations, and resources — what would you like to explore?',
            ),
            array(
                'keywords' => array('price', 'pricing', 'cost', 'fee', 'tier', 'plan', 'how much'),
                'answer'   => "We have three membership tiers: Free Community, \$25/year Standard, and \$50/year Premium. See the full comparison at {$site}pricing/.",
            ),
            array(
                'keywords' => array('join', 'sign up', 'signup', 'register', 'become a member', 'how do i join'),
                'answer'   => "You can join the LNMC Diaspora community at {$site}membership/. The Free tier is open to everyone; Standard and Premium unlock member resources, event access, and global chapter networking.",
            ),
            array(
                'keywords' => array('donate', 'donation', 'contribute', 'give', 'support'),
                'answer'   => "Thank you for wanting to support our work. Please visit {$site}donate/ to make a secure contribution. 100% of donations fund education, heritage preservation, and community programs.",
            ),
            array(
                'keywords' => array('chapter', 'region', 'country', 'where are you', 'locations'),
                'answer'   => "LNMC has regional chapters across Africa, Europe, North America, Asia, Middle East, and Oceania. Explore them at {$site}community/.",
            ),
            array(
                'keywords' => array('event', 'gathering', 'meetup', 'conference', 'calendar'),
                'answer'   => "We host community gatherings, educational workshops, and cultural events throughout the year. The homepage lists what is coming up next, or ask on WhatsApp for chapter-specific events.",
            ),
            array(
                'keywords' => array('scholarship', 'grant', 'education fund', 'tuition'),
                'answer'   => "LNMC supports Liberian Muslim students through our education programs. Visit {$site}resources/ for the current scholarship application details, or contact us for eligibility questions.",
            ),
            array(
                'keywords' => array('mission', 'about you', 'who are you', 'what is lnmc', 'purpose'),
                'answer'   => "LNMC Diaspora was founded in 1993 to advance Islamic education, preserve Liberian Muslim heritage, and advocate for our community worldwide. Learn more at {$site}about/.",
            ),
            array(
                'keywords' => array('contact', 'email', 'phone', 'reach you', 'talk to someone', 'human', 'agent'),
                'answer'   => "You can reach us via {$site}contact/, or tap the WhatsApp button at the top of this chat window to talk with a real person.",
            ),
            array(
                'keywords' => array('privacy', 'data', 'gdpr', 'personal info'),
                'answer'   => "We respect your privacy. This chat does not store personal contact details and conversations are not used for model training. Full privacy notice at {$site}privacy-policy/.",
            ),
            array(
                'keywords' => array('volunteer', 'help out', 'get involved', 'partner'),
                'answer'   => "We would love your help. Community partners and volunteers can learn more at {$site}community-partners/, or send us a note via {$site}contact/.",
            ),
            array(
                'keywords' => array('login', 'log in', 'signin', 'member dashboard', 'my account'),
                'answer'   => "Members can sign in at {$site}wp-login.php and manage their profile at {$site}member-dashboard/.",
            ),
            array(
                'keywords' => array('cancel', 'refund', 'renew', 'billing'),
                'answer'   => "For subscription changes, renewals, or billing questions, please visit {$site}contact/ or tap WhatsApp at the top of this chat. An admin will help you directly.",
            ),
        );
    }

    /**
     * Append a capped log entry. Stores {ts, ip_hash, q, a, source}.
     * IP is hashed, message truncated to 240 chars, reply to 600 chars.
     */
    private function log($ip, $q, $a, $source)
    {
        $log = get_option(self::LOG_OPTION, array());
        if (! is_array($log)) $log = array();
        $log[] = array(
            'ts'     => time(),
            'ip'     => substr(md5((string) $ip), 0, 10),
            'q'      => mb_substr((string) $q, 0, 240),
            'a'      => mb_substr((string) $a, 0, 600),
            'source' => $source,
        );
        if (count($log) > self::LOG_MAX) {
            $log = array_slice($log, -self::LOG_MAX);
        }
        update_option(self::LOG_OPTION, $log, false);
    }

    private function default_system_prompt()
    {
        return "You are the LNMC Diaspora Community Assistant — the friendly inquiry agent for the Liberia National Muslim Council Diaspora Organization (LNMCDO).\n\n"
            . "Goal: Help visitors learn about membership tiers, regional chapters, cultural events, educational resources, mentorship, and scholarships. Connect people to relevant pages and invite them to join the global community.\n\n"
            . "Core rules:\n"
            . "1. Scope: Only answer questions about LNMCDO's mission, membership, resources, events, regional chapters (Africa, Europe, North America, Asia, Middle East, Oceania), and community programs.\n"
            . "2. Safety: NEVER ask for credit card numbers, passwords, or government ID. Direct payment questions to the Membership page.\n"
            . "3. Tone: Warm, professional, culturally respectful. Honor Islamic greetings if the user uses them.\n"
            . "4. Handover: If the user wants to talk to a human, suggest the Contact page and the WhatsApp button at the top of the chat window.\n"
            . "5. Conversion: When a user asks about joining, give a one-sentence overview of the three tiers (Free / \$25/yr Standard / \$50/yr Premium) and link to /membership/.\n\n"
            . "Knowledge base:\n"
            . "- Mission: Advance Islamic education, preserve Liberian Muslim heritage, advocate for community rights globally.\n"
            . "- Founded: 1993. Members: 12,000+ across 6 continents.\n"
            . "- Tiers: Free Community / \$25/yr Standard / \$50/yr Premium.\n"
            . "- Key pages: /about/, /membership/, /resources/, /community/, /projects/, /donate/, /contact/.";
    }
}

new LNMC_AI_Chat();

/**
 * Admin settings page — Settings → LNMC Chat
 */
add_action('admin_menu', function () {
    add_options_page(
        'LNMC Chat Assistant',
        'LNMC Chat',
        'manage_options',
        'lnmc-chat',
        'lnmc_chat_settings_page'
    );
});

add_action('admin_init', function () {
    register_setting('lnmc_chat_settings', 'lnmc_ai_api_key', array('sanitize_callback' => 'sanitize_text_field'));
    register_setting('lnmc_chat_settings', 'lnmc_ai_model', array('sanitize_callback' => 'sanitize_text_field'));
    register_setting('lnmc_chat_settings', 'lnmc_ai_mode', array('sanitize_callback' => function ($v) {
        return in_array($v, array('openai', 'faq'), true) ? $v : 'faq';
    }));
    register_setting('lnmc_chat_settings', 'lnmc_ai_system_prompt', array('sanitize_callback' => 'sanitize_textarea_field'));
    register_setting('lnmc_chat_settings', 'lnmc_whatsapp_number', array('sanitize_callback' => 'sanitize_text_field'));
    register_setting('lnmc_chat_settings', 'lnmc_chat_enabled', array('sanitize_callback' => function ($v) {
        return $v === '1' ? '1' : '0';
    }));
});

/**
 * Admin-post handler: clear the chat log.
 */
add_action('admin_post_lnmc_chat_clear_log', function () {
    if (! current_user_can('manage_options')) wp_die('Forbidden');
    check_admin_referer('lnmc_chat_clear_log');
    delete_option(LNMC_AI_Chat::LOG_OPTION);
    wp_safe_redirect(admin_url('options-general.php?page=lnmc-chat&log=cleared'));
    exit;
});

function lnmc_chat_settings_page()
{
    if (! current_user_can('manage_options')) return;
    $mode     = get_option('lnmc_ai_mode', 'faq');
    $model    = get_option('lnmc_ai_model', 'gpt-4o-mini');
    $enabled  = get_option('lnmc_chat_enabled', '0');
    $whatsapp = get_option('lnmc_whatsapp_number', '');
    $prompt   = get_option('lnmc_ai_system_prompt', '');
    $api_key  = get_option('lnmc_ai_api_key', '');
    $log      = get_option(LNMC_AI_Chat::LOG_OPTION, array());
    if (! is_array($log)) $log = array();
    $log_recent = array_reverse(array_slice($log, -25));
?>
    <div class="wrap">
        <h1>LNMC Chat Assistant</h1>
        <p>Configure the AI chat widget that appears on every front-end page.</p>

        <?php if (isset($_GET['log']) && $_GET['log'] === 'cleared') : ?>
            <div class="notice notice-success is-dismissible">
                <p>Chat log cleared.</p>
            </div>
        <?php endif; ?>

        <?php if ($mode === 'openai' && empty($api_key)) : ?>
            <div class="notice notice-warning">
                <p><strong>OpenAI mode is selected but no API key is set.</strong> The widget will gracefully fall back to FAQ answers until a key is added below.</p>
            </div>
        <?php endif; ?>

        <form method="post" action="options.php">
            <?php settings_fields('lnmc_chat_settings'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Enable chat widget</th>
                    <td>
                        <label>
                            <input type="checkbox" name="lnmc_chat_enabled" value="1" <?php checked($enabled, '1'); ?> />
                            Show the chat launcher on the front end
                        </label>
                        <p class="description">Uncheck to hide the widget site-wide without removing the code.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Reply mode</th>
                    <td>
                        <fieldset>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="radio" name="lnmc_ai_mode" value="faq" <?php checked($mode, 'faq'); ?> />
                                <strong>FAQ mode</strong> — keyword-matched answers, no network calls, $0 cost. Ideal for launch.
                            </label>
                            <label style="display:block;">
                                <input type="radio" name="lnmc_ai_mode" value="openai" <?php checked($mode, 'openai'); ?> />
                                <strong>OpenAI mode</strong> — full AI answers via <code>chat/completions</code>. Requires API key below. Falls back to FAQ on error.
                            </label>
                        </fieldset>
                    </td>
                </tr>

                <tr>
                    <th scope="row">OpenAI API key</th>
                    <td>
                        <input type="password" name="lnmc_ai_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" autocomplete="off" />
                        <p class="description">Stored in <code>wp_options.lnmc_ai_api_key</code>. Only visible to admins. Leave blank to disable OpenAI calls (FAQ mode still works).</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Model</th>
                    <td>
                        <select name="lnmc_ai_model">
                            <option value="gpt-4o-mini" <?php selected($model, 'gpt-4o-mini'); ?>>gpt-4o-mini — recommended (~$0.45/mo typical)</option>
                            <option value="gpt-4o" <?php selected($model, 'gpt-4o'); ?>>gpt-4o — premium (~$7.50/mo typical)</option>
                            <option value="gpt-3.5-turbo" <?php selected($model, 'gpt-3.5-turbo'); ?>>gpt-3.5-turbo — legacy</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">WhatsApp handover number</th>
                    <td>
                        <input type="text" name="lnmc_whatsapp_number" value="<?php echo esc_attr($whatsapp); ?>" class="regular-text" placeholder="e.g. 12312345678 (country code + number, no symbols)" />
                        <p class="description">Used for the "Talk to a human" button in the chat header. Leave blank to hide the button.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">System prompt (OpenAI mode only)</th>
                    <td>
                        <textarea name="lnmc_ai_system_prompt" rows="10" class="large-text code"><?php echo esc_textarea($prompt); ?></textarea>
                        <p class="description">Leave blank to use the default LNMC prompt. Only used when Reply mode is OpenAI.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>

        <hr>
        <h2>Recent conversations (last 25)</h2>
        <p class="description">IP addresses are hashed. Messages are truncated. Used for first-week monitoring and spam triage.</p>
        <?php if (empty($log_recent)) : ?>
            <p><em>No conversations logged yet.</em></p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th style="width:140px;">When</th>
                        <th style="width:90px;">Source</th>
                        <th style="width:90px;">IP hash</th>
                        <th>Question</th>
                        <th>Reply</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($log_recent as $row) : ?>
                        <tr>
                            <td><?php echo esc_html(date_i18n('M j, H:i', (int) $row['ts'])); ?></td>
                            <td><code><?php echo esc_html($row['source'] ?? ''); ?></code></td>
                            <td><code><?php echo esc_html($row['ip'] ?? ''); ?></code></td>
                            <td><?php echo esc_html($row['q'] ?? ''); ?></td>
                            <td><?php echo esc_html($row['a'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px;">
                <?php wp_nonce_field('lnmc_chat_clear_log'); ?>
                <input type="hidden" name="action" value="lnmc_chat_clear_log" />
                <?php submit_button('Clear log', 'secondary', 'submit', false); ?>
            </form>
        <?php endif; ?>
    </div>
<?php
}
