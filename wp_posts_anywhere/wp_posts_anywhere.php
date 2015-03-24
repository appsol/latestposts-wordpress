<?php
/**
 * Plugin Name: WP Posts Anywhere
 * Plugin URI: http://www.appropriatesolutions.co.uk/
 * Description: Shows the latest posts based on options in any widget location
 * Version: 0.3.0
 * Author: Stuart Laverick
 * Author URI: http://www.appropriatesolutions.co.uk/
 * Text Domain: Optional. wp_posts_anywhere
 * License: GPL2
 *
 * @package wp_posts_anywhere
 * @todo Refactor to allow shortcode and function call
 */
/*  Copyright 2015  Stuart Laverick  (email : stuart@appropriatesolutions.co.uk)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
namespace PostsAnywhere;

defined('ABSPATH') or die( 'No script kiddies please!' );

require_once 'the_archive_post.php';
require_once 'functions.php';

class PostsAnywhereWidget extends \WP_Widget
{

    /**
     * Singleton class instance
     *
     * @var object LatestPostsWidget
     **/
    private static $instance = null;

    /**
     * Constructor for LatestPostsWidget
     *
     * @return void
     * @author Stuart Laverick
     **/
    public function __construct()
    {
        if (is_admin()) {
            add_action('load-post.php', [$this, 'addPostMeta']);
        }
        add_action("widgets_init", [$this, 'register']);

        add_shortcode('posts_here', [$this, 'shortcodeHandler']);

        parent::__construct(
            'posts_anywhere',
            __('Posts Anywhere', 'wp_posts_anywhere'),
            ['description' => __('Shows a list of post extracts from a selected category in a Media Box style')]
        );
    }

    /**
     * Creates or returns an instance of this class
     *
     * @return A single instance of this class
     * @author Stuart Laverick
     **/
    public static function getInstance()
    {
        if (null == self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Register the Sidebar and Widget
     * The sidebar occurs within the list of posts, allowing 
     * placement of adverts, promotions and similar
     *
     * @return void
     * @author Stuart Laverick
     * @todo Assign a sidebar for each widget using the widget ID
     **/
    public function register()
    {
        register_sidebar(array(
            'name' => __("Posts Anywhere Interval"),
            'id' => "wp_posts_anywhere",
            'description' => __("Sidebar that exists within the Posts Anywhere list"),
            'before_widget' => '<div id="%1$s" class="widget-area posts-anywhere-interval %2$s">',
            'after_widget' => "\n</div>\n",
            'before_title' => "<h3 class=\"hd\">",
            'after_title' => "</h3>\n",
        ));
        register_widget('PostsAnywhere\PostsAnywhereWidget');
    }

    /**
     * Handler for shortcode calls
     *
     * Options:
     * post_qty => Integer: Number of posts to show
     * posts_context => String: one of 'all', 'current', 'except', 'categories'
     * featured => Integer: show all posts (0) only featured (1) or no featured (2)
     * cat_ids => String: comma seperated category ids to use
     * sidebar_pos => Integer: the index after which to insert the sidebar, or no sidebar (0)
     * image_size => String: the featured image size, leave empty for no image
     * archive_post => Array: control the Archive Post rendering (see ArchivePost:default_options)
     *
     * @return string HTML of the posts collection
     * @author Stuart Laverick
     **/
    public function shortcodeHandler($attributes)
    {
        $default_options = [
            'post_qty' => get_option('posts_per_page'),
            'posts_context' => 'all',
            'featured' => 0,
            'cat_ids' => [],
            'sidebar_pos' => 0,
            'image_size' => 'thumbnail',
            'archive_post' => []
        ];

        if (isset($attributes['cat_ids'])) {
            $attributes['cat_ids'] = array_map('intval', explode(',', $attributes['cat_ids']));
        }

        if (isset($attributes['image_size'])) {
            $attributes['image_size'] = $this->checkImageSize($attributes['image_size']);
        }

        if (isset($attributes['posts_context'])) {
            if(!in_array($attributes['posts_context'], ['all', 'current', 'except', 'categories'])) {
                $attributes['posts_context'] == 'all';
            }
        }

        if (isset($attributes['archive_post'])) {
            $archive_post_config = [];
            $success = array_walk(explode(',', $attributes['archive_post']), function($keyvalue)
                {
                    $setting = explode(':', $keyvalue);
                    if (count($setting) > 1) {
                        $archive_post_config[$setting[0]] = $setting[1];
                    }
                });
            $attributes['archive_post'] = $success? $archive_post_config : [];
        }

        $options = shortcode_atts($default_options, $attributes);

        return $this->buildLatestPosts($options);
    }

    /**
     * Initiate the hooks required to add the post meta
     *
     * @return null
     * @author Stuart Laverick
     */
    public function addPostMeta()
    {
        add_action('add_meta_boxes', array($this, 'addMetaBox'));
        add_action('save_post', array($this, 'savePostMeta'));
    }

    /**
     * Adds the meta box container.
     *
     * @return null
     * @author Stuart Laverick
     */
    public function addMetaBox()
    {
        add_meta_box(
            'featured_post',
            __('Featured', 'wp_posts_anywhere'),
            array($this, 'displayMetaBox'),
            'post',
            'side',
            'high'
        );
    }

    /**
     * Save the meta when the post is saved.
     *
     * @param int $post_id The ID of the post being saved.
     * @return null | int the Post ID on failure
     * @author Stuart Laverick
     * @todo Replace the Post Meta API with the tagging API 
     */
    public function savePostMeta($post_id)
    {
        /*
         * We need to verify this came from the our screen and with proper authorization,
         * because save_post can be triggered at other times.
         */

        // Check if our nonce is set.
        if (!isset($_POST['appsol_featured_post_nonce']))
            return $post_id;

        $nonce = $_POST['appsol_featured_post_nonce'];

        // Verify that the nonce is valid.
        if (!wp_verify_nonce($nonce, 'appsol_featured_post'))
            return $post_id;

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            return $post_id;

        // Check the user's permissions.
        if ('page' == $_POST['post_type']) {

            if (!current_user_can('edit_page', $post_id))
                return $post_id;
        } else {

            if (!current_user_can('edit_post', $post_id))
                return $post_id;
        }

        /* OK, its safe for us to save the data now. */

        // Sanitize the user input.
        $featured = 0;
        if (isset($_POST['featured_post']))
            $featured = sanitize_text_field($_POST['featured_post']) ? 1 : 0;

        // Update the meta field.
        update_post_meta($post_id, '_appsol_featured_post', $featured);
    }

    /**
     * Display Meta Box content.
     *
     * @param WP_Post $post The post object.
     * @return null
     * @author Stuart Laverick
     */
    public function displayMetaBox($post)
    {

        // Add an nonce field so we can check for it later.
        wp_nonce_field('wp_posts_anywhere_featured_post', 'wp_posts_anywhere_featured_post_nonce');

        // Use get_post_meta to retrieve an existing value from the database.
        $value = get_post_meta($post->ID, '_wp_posts_anywhere_featured_post', true);

        // Display the form, using the current value.
        $html = ['<label for="featured_post">',
                '<input type="checkbox" id="featured_post" name="featured_post" value="1"' . ($value ? ' checked="checked" ' : '') . ' />',
                __('Promote this Post', 'wp_posts_anywhere'),
                '</label> '];
        print implode("\n", $html);
    }

    /**
     * Returns the array of latest posts based on the instance parameters
     * 
     * Post Category selection options:
     * All (default): all categories
     * categories: specified categories
     * current: only the current category and it's children
     * except: all except the current category and it's children
     *
     * @param array The widget instance
     * @return array The latest posts collection
     * @author Stuart Laverick
     **/
    private function getLatestPosts($instance)
    {
        $params = array(
            'numberposts' => $instance['post_qty'] ? $instance['post_qty'] : get_option('posts_per_page'),
            'post_type' => array('post')
        );

        if ($instance['posts_context'] != 'all') {
            $params['tax_query'] = array($this->buildTaxonomyQuery($instance['posts_context'], $instance['cat_ids']));
        }

        if (!empty($instance['featured'])) {
            $params['meta_query'] = array($this->buildFeaturedQuery($instance['featured']));
        }

        return get_posts($params);
    }

    /**
     * Checks the proposed image size exists
     *
     * @return String acceptable image size
     * @author Stuart Laverick
     **/
    public function checkImageSize($image_size)
    {
        if ($image_size) {
            $image_size_names = array_keys($this->getThumbnailSizes());
            if (!in_array($image_size, $image_size_names)) {
                $image_size = 'thumbnail';
            }
        }
        return $image_size;
    }

    /**
     * Builds the post taxonomy query from the instance parameters
     *
     * @return array The taxonomy query for WP_Query
     * @author Stuart Laverick
     **/
    private function buildTaxonomyQuery($context = 'all', $cat_ids = [])
    {
        $tax_query = array(
                'taxonomy' => 'category',
                'field' => 'id'
            );
        // Specified categories
        if ($context == 'categories') {
            $tax_query['terms'] = (array) $cat_ids;
        }
        // In relation to the current category context
        if ($context == 'current' || $context == 'except') {
            $categories = array();
            if (is_category()) {
                $categories[] = get_query_var('cat');
            } elseif (is_single()) {
                global $post;
                $post_categories = get_the_category($post->ID);
                foreach ($post_categories as $cat) {
                    $categories[] = $cat->term_id;
                }
            }
            $tax_query['terms'] = $categories;
            if ($context == 'except') {
                $tax_query['operator'] = 'NOT IN';
            }
        }

        return $tax_query;
    }

    /**
     * Build the post_meta 'featured' query
     * Can request either only featured or all except featured
     *
     * @return 
     * @author Stuart Laverick
     **/
    private function buildFeaturedQuery($featured)
    {
        $featured_options = array(
            'key' => '_wp_posts_anywhere_featured_post',
            'value' => 1,
        );
        $featured_options['compare'] = $featured > 1 ? '!=' : '=';
        return $featured_options;
    }

    /**
     * Returns the registered image size names and dimensions
     * 
     * @global type $_wp_additional_image_sizes
     * @return array Registered image sizes
     */
    private function getThumbnailSizes() {
        global $_wp_additional_image_sizes;

        $sizes = array();
        foreach (get_intermediate_image_sizes() as $s) {
            $sizes[$s] = array(0, 0);
            if (in_array($s, array('thumbnail', 'medium', 'large'))) {
                $sizes[$s][0] = get_option($s . '_size_w');
                $sizes[$s][1] = get_option($s . '_size_h');
                continue;
            }
            if (isset($_wp_additional_image_sizes) && isset($_wp_additional_image_sizes[$s])) {
                $sizes[$s] = array($_wp_additional_image_sizes[$s]['width'], $_wp_additional_image_sizes[$s]['height'],);
            }
        }

        return $sizes;
    }

    /**
     * Build the full html string of the latest posts
     *
     * @return string HTML of latest posts
     * @author Stuart Laverick
     **/
    public function buildLatestPosts($instance)
    {
        $index = 0;
        $options = isset($instance['archive_post'])? $instance['archive_post'] : array();
        $options['tmb_type'] = $instance['image_size'];
        $latest_posts = $this->getLatestPosts($instance);

        $html = ['<div class="posts-anywhere">'];

        foreach ($latest_posts as $the_post) {
            $index++;
            // Start to output the post
            if (is_active_sidebar('wp_posts_anywhere') && $instance['sidebar_pos'] == $index) {
                $html[] = '<div class="block interval">';
                ob_start();
                dynamic_sidebar('wp_posts_anywhere');
                $html[] = ob_get_clean();
                $html[] = '</div>';
            }
            $the_archive_post = new ArchivePost($the_post, $options);
            $html[] = $the_archive_post->getArchivePost();
        }
        $html[] = '</div>';
        return implode("\n", $html);
    }

    /**
     * Front-end display of widget.
     *
     * @see WP_Widget::widget()
     * @param array $args Widget arguments
     * @param array $instance Saved parameters for this instance
     * @return null Echos content
     */
    public function widget($args, $instance)
    {
        // If only to display on the front page and this isn't the fron page, return
        if ($instance['home_only'] == 'yes' && !is_front_page()) {
            return;
        }

        extract($args, EXTR_SKIP);
        $title = apply_filters('widget_title', $instance['title']);

        echo $before_widget;
        if (!empty($title)) {
            echo $before_title;
            echo $title;
            echo $after_title;
        }
        echo $this->buildLatestPosts($instance);
        echo $after_widget;
    }

    function update($new_instance, $old_instance)
    {
        $instance = $old_instance;
        $instance['title'] = strip_tags($new_instance['title']);
        $instance['posts_context'] = strip_tags($new_instance['posts_context']);
        $instance['cat_ids'] = (array) $new_instance['cat_ids'];
        $instance['post_qty'] = strip_tags($new_instance['post_qty']);
        $instance['home_only'] = strip_tags($new_instance['home_only']);
        $instance['sidebar_pos'] = strip_tags($new_instance['sidebar_pos']);
        $instance['featured'] = strip_tags($new_instance['featured']);
        $instance['image_size'] = strip_tags($new_instance['image_size']);
        return $instance;
    }

    function form($instance) {
        $instance = wp_parse_args((array) $instance, array(
            'title' => '',
            'posts_context' => 'all',
            'cat_ids' => array(),
            'post_qty' => '',
            'home_only' => '',
            'sidebar_pos' => 0,
            'featured' => 0,
            'image_size' => 'thumbnail'
        ));
        ?>
        <p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title'); ?>:
                <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($instance['title']); ?>" /></label></p>
        <p class="description">Choose how post selection should operate</p>
        <p id="<?php echo $this->id; ?>_context_options"><label for="<?php echo $this->get_field_id('posts_context'); ?>_all">
                <input type="radio" id="<?php echo $this->get_field_id('posts_context'); ?>_all" name="<?php echo $this->get_field_name('posts_context'); ?>" value="all" <?php if ($instance['posts_context'] == 'all') echo 'checked="checked" '; ?>/> <?php _e('All Categories'); ?></label><br />
            <label for="<?php echo $this->get_field_id('posts_context'); ?>_category">
                <input type="radio" id="<?php echo $this->get_field_id('posts_context'); ?>_category" name="<?php echo $this->get_field_name('posts_context'); ?>" value="current" <?php if ($instance['posts_context'] == 'current') echo 'checked="checked" '; ?>/> <?php _e('Current Category'); ?></label><br />
            <label for="<?php echo $this->get_field_id('posts_context'); ?>_except">
                <input type="radio" id="<?php echo $this->get_field_id('posts_context'); ?>_except" name="<?php echo $this->get_field_name('posts_context'); ?>" value="except" <?php if ($instance['posts_context'] == 'except') echo 'checked="checked" '; ?>/> <?php _e('All Except the Current Category'); ?></label><br />
            <label for="<?php echo $this->get_field_id('posts_context'); ?>_categories">
                <input type="radio" id="<?php echo $this->get_field_id('posts_context'); ?>_categories" name="<?php echo $this->get_field_name('posts_context'); ?>" value="categories" <?php if ($instance['posts_context'] == 'categories') echo 'checked="checked" '; ?>/> <?php _e('Select Categories'); ?></label></p>
        <p class="description">Select the categories to show posts from</p>
        <p id="<?php echo $this->id; ?>_categories_select">
            <?php
            $categories = get_categories(array(
                'type' => 'post',
                'parent' => 0
            ));
            $i = 1;
            foreach ($categories as $category):
                ?>
                <label for="<?php echo $this->get_field_id('cat_ids') . '_' . $i; ?>">
                    <input type="checkbox" <?php if (in_array($category->term_id, (array) $instance['cat_ids'])) echo 'checked="checked" '; ?>id="<?php echo $this->get_field_id('cat_ids') . '_' . $i; ?>" name="<?php echo $this->get_field_name('cat_ids') . '[]'; ?>" value="<?php echo $category->term_id; ?>" <?php if ($instance['posts_context'] != 'categories') echo 'disabled="disabled" '; ?>/> <?php echo $category->cat_name . ' (' . $category->category_count . ')'; ?></label><br />
            <?php endforeach; ?>
        </p>
        <script>
            jQuery(document).ready(function($) {
                $("#<?php echo $this->id; ?>_context_options input").click(function() {
                    if ($('#<?php echo $this->get_field_id('posts_context'); ?>_categories').prop('checked'))
                        $('#<?php echo $this->id; ?>_categories_select input').prop('disabled', false);
                    else
                        $('#<?php echo $this->id; ?>_categories_select input').prop('disabled', true);
                });
            });
        </script>
        <p><label for="<?php echo $this->get_field_id('featured'); ?>_all">
                <input id="<?php echo $this->get_field_id('featured'); ?>_all" name="<?php echo $this->get_field_name('featured'); ?>" type="radio" value="0" <?php if (esc_attr($instance['featured']) == '0') echo 'checked="checked"'; ?> /> <?php _e('All Posts'); ?></label><br />
            <label for="<?php echo $this->get_field_id('featured'); ?>_only">
                <input id="<?php echo $this->get_field_id('featured'); ?>_only" name="<?php echo $this->get_field_name('featured'); ?>" type="radio" value="1" <?php if (esc_attr($instance['featured']) == '1') echo 'checked="checked"'; ?> /> <?php _e('Featured Posts only'); ?></label><br />
            <label for="<?php echo $this->get_field_id('featured'); ?>_none">
                <input id="<?php echo $this->get_field_id('featured'); ?>_none" name="<?php echo $this->get_field_name('featured'); ?>" type="radio" value="2" <?php if (esc_attr($instance['featured']) == '2') echo 'checked="checked"'; ?> /> <?php _e('No Featured Posts'); ?></label><br />
        </p>
        <p><label for="<?php echo $this->get_field_id('post_qty'); ?>"><?php _e('Number of Posts'); ?>:</label>
            <select id="<?php echo $this->get_field_id('post_qty'); ?>" name="<?php echo $this->get_field_name('post_qty'); ?>">
                <option value="0"><?php echo _e('Max Posts'); ?></option>
                <?php
                for ($i = 1; $i < 10; $i++):
                    ?>
                    <option value="<?php echo $i; ?>"<?php if ($i == $instance['post_qty']) echo ' selected="selected"'; ?>><?php echo $i; ?></option>
                <?php endfor; ?>
            </select></p>
        <p><label for="<?php echo $this->get_field_id('sidebar_pos'); ?>"><?php _e('Sidebar Position'); ?>:</label>
            <select id="<?php echo $this->get_field_id('sidebar_pos'); ?>" name="<?php echo $this->get_field_name('sidebar_pos'); ?>">
                <option value="0"><?php echo _e('No Sidebar'); ?></option>
                <?php
                $max_posts = $instance['post_qty'] ? $instance['post_qty'] : get_option('posts_per_page');
                for ($i = 1; $i < $max_posts; $i++):
                    ?>
                    <option value="<?php echo $i; ?>"<?php if ($i == $instance['sidebar_pos']) echo ' selected="selected"'; ?>><?php echo $i; ?></option>
                <?php endfor; ?>
            </select></p>
        <p><input class="checkbox" id="<?php echo $this->get_field_id('home_only'); ?>" name="<?php echo $this->get_field_name('home_only'); ?>" type="checkbox" value="yes" <?php if (esc_attr($instance['home_only']) == 'yes') echo 'checked="checked"'; ?> />
            <label for="<?php echo $this->get_field_id('home_only'); ?>"><?php _e('Display on Home page only'); ?></label></p>
        <p><label for="<?php echo $this->get_field_id('image_size'); ?>"><?php _e('Image Size'); ?>:</label>
            <select id="<?php echo $this->get_field_id('image_size'); ?>" name="<?php echo $this->get_field_name('image_size'); ?>">
                <option value="0"><?php echo _e('No Image'); ?></option>
                <?php
                $image_sizes = $this->getThumbnailSizes();
                foreach ($image_sizes as $image_size => $sizes):
                    ?>
                    <option value="<?php echo $image_size; ?>"<?php if ($image_size == $instance['image_size']) echo ' selected="selected"'; ?>><?php echo $image_size . ' (' . implode('x', $sizes) . ')'; ?></option>
                <?php endforeach; ?>
            </select></p>
        <?php
    }
}

$latestPosts = PostsAnywhereWidget::getInstance();