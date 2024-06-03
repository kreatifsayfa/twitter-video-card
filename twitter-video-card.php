<?php
/*
Plugin Name: Twitter Video Card
Description: Adds Twitter Video Card meta tags to your WordPress posts and pages, including support for YouTube videos.
Version: 1.1
Author: kreatifsayfa
*/

function tvc_add_meta_tags() {
    if (is_single() || is_page()) {
        global $post;
        $video_url = get_post_meta($post->ID, 'twitter_video_url', true);
        if ($video_url) {
            echo '<meta name="twitter:card" content="player">' . "\n";
            echo '<meta name="twitter:site" content="@YourSiteTwitterHandle">' . "\n";
            echo '<meta name="twitter:title" content="' . get_the_title() . '">' . "\n";
            echo '<meta name="twitter:description" content="' . get_the_excerpt() . '">' . "\n";
            echo '<meta name="twitter:image" content="' . get_the_post_thumbnail_url($post, 'full') . '">' . "\n";
            
            if (strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false) {
                // YouTube video meta tags
                $youtube_id = tvc_get_youtube_id($video_url);
                $player_url = 'https://www.youtube.com/embed/' . $youtube_id;
                echo '<meta name="twitter:player" content="' . esc_url($player_url) . '">' . "\n";
                echo '<meta name="twitter:player:width" content="1280">' . "\n";
                echo '<meta name="twitter:player:height" content="720">' . "\n";
                echo '<meta name="twitter:player:stream" content="' . esc_url($player_url) . '">' . "\n";
                echo '<meta name="twitter:player:stream:content_type" content="text/html">' . "\n";
            } else {
                // Default video meta tags
                echo '<meta name="twitter:player" content="' . esc_url($video_url) . '">' . "\n";
                echo '<meta name="twitter:player:width" content="1280">' . "\n";
                echo '<meta name="twitter:player:height" content="720">' . "\n";
                echo '<meta name="twitter:player:stream" content="' . esc_url($video_url) . '">' . "\n";
                echo '<meta name="twitter:player:stream:content_type" content="video/mp4">' . "\n";
            }
        }
    }
}
add_action('wp_head', 'tvc_add_meta_tags');

function tvc_get_youtube_id($url) {
    preg_match('/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches);
    return isset($matches[1]) ? $matches[1] : false;
}

function tvc_add_meta_box() {
    add_meta_box(
        'tvc_meta_box',
        'Twitter Video Card',
        'tvc_meta_box_callback',
        ['post', 'page'],
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'tvc_add_meta_box');

function tvc_meta_box_callback($post) {
    wp_nonce_field('tvc_save_meta_box_data', 'tvc_meta_box_nonce');
    $value = get_post_meta($post->ID, 'twitter_video_url', true);
    echo '<label for="tvc_video_url">Video URL: </label>';
    echo '<input type="text" id="tvc_video_url" name="tvc_video_url" value="' . esc_attr($value) . '" size="25" />';
}

function tvc_save_meta_box_data($post_id) {
    if (!isset($_POST['tvc_meta_box_nonce'])) {
        return;
    }
    if (!wp_verify_nonce($_POST['tvc_meta_box_nonce'], 'tvc_save_meta_box_data')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    if (!isset($_POST['tvc_video_url'])) {
        return;
    }
    $video_url = sanitize_text_field($_POST['tvc_video_url']);
    update_post_meta($post_id, 'twitter_video_url', $video_url);
}
add_action('save_post', 'tvc_save_meta_box_data');
