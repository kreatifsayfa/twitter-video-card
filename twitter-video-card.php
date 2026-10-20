<?php
/*
Plugin Name: Twitter Video Card
Description: Adds Twitter (X) Player Card and Open Graph video meta tags to posts and pages. Supports YouTube, Vimeo and direct MP4 files.
Version: 2.0
Author: kreatifsayfa
Text Domain: twitter-video-card
License: GPLv2 or later
*/

if (!defined('ABSPATH')) {
    exit;
}

define('TVC_META_KEY', 'twitter_video_url');
define('TVC_OPTION', 'tvc_settings');

/* ---------- Helpers ---------- */

function tvc_get_settings() {
    return wp_parse_args(get_option(TVC_OPTION, []), [
        'site_handle' => '',
        'width'       => 1280,
        'height'      => 720,
        'post_types'  => ['post', 'page'],
    ]);
}

function tvc_get_youtube_id($url) {
    if (preg_match('/(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?|shorts|live)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
        return $m[1];
    }
    return false;
}

function tvc_get_vimeo_id($url) {
    if (preg_match('/vimeo\.com\/(?:video\/|channels\/[^\/]+\/|groups\/[^\/]+\/videos\/)?(\d+)/', $url, $m)) {
        return $m[1];
    }
    return false;
}

/**
 * Returns [type, player_url, thumbnail_or_empty] for a video URL.
 */
function tvc_resolve_video($post_id, $video_url) {
    if ($id = tvc_get_youtube_id($video_url)) {
        return ['youtube', 'https://www.youtube.com/embed/' . $id, 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg'];
    }
    if ($id = tvc_get_vimeo_id($video_url)) {
        return ['vimeo', 'https://player.vimeo.com/video/' . $id, ''];
    }
    // Direct file: Twitter needs an HTML page as player, so we serve our own.
    return ['file', add_query_arg('tvc_player', (int) $post_id, home_url('/')), ''];
}

/* ---------- Front-end meta tags ---------- */

function tvc_meta($name, $content, $attr = 'name') {
    if ($content === '' || $content === null || $content === false) {
        return;
    }
    printf('<meta %s="%s" content="%s" />' . "\n", $attr, esc_attr($name), esc_attr($content));
}

function tvc_add_meta_tags() {
    if (!is_singular()) {
        return;
    }
    $post     = get_queried_object();
    $settings = tvc_get_settings();
    if (!$post || !in_array($post->post_type, (array) $settings['post_types'], true)) {
        return;
    }
    $video_url = get_post_meta($post->ID, TVC_META_KEY, true);
    if (!$video_url) {
        return;
    }

    list($type, $player, $thumb) = tvc_resolve_video($post->ID, $video_url);
    $image = get_the_post_thumbnail_url($post, 'full') ?: $thumb;
    $desc  = wp_strip_all_tags(get_the_excerpt($post));
    $title = wp_strip_all_tags(get_the_title($post));
    $w     = (int) $settings['width'];
    $h     = (int) $settings['height'];

    echo "\n<!-- Twitter Video Card -->\n";
    tvc_meta('twitter:card', 'player');
    if ($settings['site_handle']) {
        tvc_meta('twitter:site', '@' . ltrim($settings['site_handle'], '@'));
    }
    tvc_meta('twitter:title', $title);
    tvc_meta('twitter:description', $desc);
    tvc_meta('twitter:image', $image);
    tvc_meta('twitter:player', $player);
    tvc_meta('twitter:player:width', $w);
    tvc_meta('twitter:player:height', $h);
    if ($type === 'file') {
        tvc_meta('twitter:player:stream', $video_url);
        tvc_meta('twitter:player:stream:content_type', 'video/mp4');
    }

    // Open Graph (Facebook, LinkedIn, WhatsApp, Discord...)
    tvc_meta('og:type', 'video.other', 'property');
    tvc_meta('og:title', $title, 'property');
    tvc_meta('og:description', $desc, 'property');
    tvc_meta('og:url', get_permalink($post), 'property');
    tvc_meta('og:image', $image, 'property');
    tvc_meta('og:video', $type === 'file' ? $video_url : $player, 'property');
    tvc_meta('og:video:type', $type === 'file' ? 'video/mp4' : 'text/html', 'property');
    tvc_meta('og:video:width', $w, 'property');
    tvc_meta('og:video:height', $h, 'property');
    echo "<!-- /Twitter Video Card -->\n";
}
add_action('wp_head', 'tvc_add_meta_tags', 5);

/* ---------- Player page for direct video files ---------- */

function tvc_render_player() {
    if (!isset($_GET['tvc_player'])) {
        return;
    }
    $post_id = absint($_GET['tvc_player']);
    $post    = get_post($post_id);
    if (!$post || $post->post_status !== 'publish' || post_password_required($post)) {
        status_header(404);
        exit;
    }
    $video = get_post_meta($post_id, TVC_META_KEY, true);
    if (!$video) {
        status_header(404);
        exit;
    }
    $poster = get_the_post_thumbnail_url($post, 'full');
    header('Content-Type: text/html; charset=utf-8');
    header_remove('X-Frame-Options');
    ?><!DOCTYPE html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html(get_the_title($post)); ?></title>
<style>html,body{margin:0;height:100%;background:#000}video{width:100%;height:100%;object-fit:contain}</style>
</head><body>
<video src="<?php echo esc_url($video); ?>"<?php if ($poster) { echo ' poster="' . esc_url($poster) . '"'; } ?> controls playsinline preload="metadata"></video>
</body></html><?php
    exit;
}
add_action('template_redirect', 'tvc_render_player', 0);

/* ---------- Meta box ---------- */

function tvc_add_meta_box() {
    $settings = tvc_get_settings();
    add_meta_box('tvc_meta_box', __('Twitter Video Card', 'twitter-video-card'), 'tvc_meta_box_callback', (array) $settings['post_types'], 'side', 'default');
}
add_action('add_meta_boxes', 'tvc_add_meta_box');

function tvc_meta_box_callback($post) {
    wp_nonce_field('tvc_save_meta_box_data', 'tvc_meta_box_nonce');
    $value = get_post_meta($post->ID, TVC_META_KEY, true);
    echo '<p><label for="tvc_video_url">' . esc_html__('Video URL (YouTube, Vimeo or .mp4)', 'twitter-video-card') . '</label></p>';
    echo '<input type="url" id="tvc_video_url" name="tvc_video_url" value="' . esc_attr($value) . '" style="width:100%" placeholder="https://" />';
    if ($value && strpos($value, 'https://') !== 0) {
        echo '<p style="color:#b32d2e">' . esc_html__('Twitter requires HTTPS video URLs.', 'twitter-video-card') . '</p>';
    }
}

function tvc_save_meta_box_data($post_id) {
    if (!isset($_POST['tvc_meta_box_nonce']) || !wp_verify_nonce(sanitize_key($_POST['tvc_meta_box_nonce']), 'tvc_save_meta_box_data')) {
        return;
    }
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) {
        return;
    }
    if (!isset($_POST['tvc_video_url'])) {
        return;
    }
    $url = esc_url_raw(trim(wp_unslash($_POST['tvc_video_url'])), ['http', 'https']);
    if ($url) {
        update_post_meta($post_id, TVC_META_KEY, $url);
    } else {
        delete_post_meta($post_id, TVC_META_KEY);
    }
}
add_action('save_post', 'tvc_save_meta_box_data');

/* ---------- Settings page ---------- */

function tvc_register_settings() {
    register_setting('tvc_settings_group', TVC_OPTION, ['sanitize_callback' => 'tvc_sanitize_settings']);
}
add_action('admin_init', 'tvc_register_settings');

function tvc_sanitize_settings($in) {
    $public = get_post_types(['public' => true]);
    return [
        'site_handle' => preg_replace('/[^A-Za-z0-9_]/', '', (string) ($in['site_handle'] ?? '')),
        'width'       => max(1, absint($in['width'] ?? 1280)),
        'height'      => max(1, absint($in['height'] ?? 720)),
        'post_types'  => array_values(array_intersect((array) ($in['post_types'] ?? []), $public)),
    ];
}

function tvc_add_settings_page() {
    add_options_page(__('Twitter Video Card', 'twitter-video-card'), __('Twitter Video Card', 'twitter-video-card'), 'manage_options', 'twitter-video-card', 'tvc_settings_page');
}
add_action('admin_menu', 'tvc_add_settings_page');

function tvc_settings_page() {
    $s = tvc_get_settings();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Twitter Video Card', 'twitter-video-card'); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('tvc_settings_group'); ?>
            <table class="form-table">
                <tr><th><label for="tvc_handle"><?php esc_html_e('Site X/Twitter handle', 'twitter-video-card'); ?></label></th>
                    <td>@<input id="tvc_handle" type="text" name="<?php echo TVC_OPTION; ?>[site_handle]" value="<?php echo esc_attr($s['site_handle']); ?>" /></td></tr>
                <tr><th><?php esc_html_e('Player size', 'twitter-video-card'); ?></th>
                    <td><input type="number" name="<?php echo TVC_OPTION; ?>[width]" value="<?php echo (int) $s['width']; ?>" style="width:90px" /> ×
                        <input type="number" name="<?php echo TVC_OPTION; ?>[height]" value="<?php echo (int) $s['height']; ?>" style="width:90px" /></td></tr>
                <tr><th><?php esc_html_e('Post types', 'twitter-video-card'); ?></th>
                    <td><?php foreach (get_post_types(['public' => true], 'objects') as $pt) {
                        if ($pt->name === 'attachment') { continue; }
                        printf('<label style="margin-right:12px"><input type="checkbox" name="%s[post_types][]" value="%s" %s /> %s</label>',
                            TVC_OPTION, esc_attr($pt->name), checked(in_array($pt->name, (array) $s['post_types'], true), true, false), esc_html($pt->labels->singular_name));
                    } ?></td></tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <p><?php esc_html_e('Validate your card by sharing a post URL in a tweet draft.', 'twitter-video-card'); ?></p>
    </div>
    <?php
}

function tvc_settings_link($links) {
    array_unshift($links, '<a href="' . esc_url(admin_url('options-general.php?page=twitter-video-card')) . '">' . esc_html__('Settings', 'twitter-video-card') . '</a>');
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'tvc_settings_link');
