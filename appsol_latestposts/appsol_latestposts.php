<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class appsolLatestPostsWidget extends WP_Widget {

    function __construct() {
        parent::__construct(
                'appsol_latest_posts', 'Latest Posts', array('description' => __('Shows a list of post extracts from a selected category in a Media Box style'))
        );
    }

    /**
     * Initiation point for the plugin
     */
    public static function init() {
        if (is_admin()) {
            add_action('load-post.php', array('appsolLatestPostsWidget', 'addPostMeta'));
        }
        add_action("widgets_init", array('appsolLatestPostsWidget', 'register'));
    }

    /**
     * Initiate the hooks required to add the post meta
     */
    public static function addPostMeta() {
        add_action('add_meta_boxes', array('appsolLatestPostsWidget', 'add_meta_box'));
        add_action('save_post', array('appsolLatestPostsWidget', 'save'));
    }

    /**
     * Adds the meta box container.
     */
    public static function add_meta_box() {
        add_meta_box(
                'featured_post', __('Featured', 'appsol'), array('appsolLatestPostsWidget', 'display_meta_box'), 'post', 'side', 'high');
    }

    /**
     * Save the meta when the post is saved.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public static function save($post_id) {
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
     */
    public function display_meta_box($post) {

        // Add an nonce field so we can check for it later.
        wp_nonce_field('appsol_featured_post', 'appsol_featured_post_nonce');

        // Use get_post_meta to retrieve an existing value from the database.
        $value = get_post_meta($post->ID, '_appsol_featured_post', true);

        // Display the form, using the current value.
        echo '<label for="featured_post">';
        echo '<input type="checkbox" id="featured_post" name="featured_post" value="1"' . ($value ? ' checked="checked" ' : '') . ' />';
        _e('Promote this Post', 'appsol');
        echo '</label> ';
    }

    function register() {
        register_sidebar(array(
            'name' => __("Latest Posts Interval"),
            'id' => "latest_posts",
            'description' => __("Sidebar that exists within the Latest Posts list"),
            'before_widget' => '<div id="%1$s" class="block widget %2$s">',
            'after_widget' => "\n</div>\n",
            'before_title' => "<h3 class=\"hd\">",
            'after_title' => "</h3>\n",
        ));
        register_widget('appsolLatestPostsWidget');
    }

    /**
     * widget - displays the output
     * @param array $args Meta data for this Widget instance
     * @param array $instance Parameters selected for this instance
     * @return null Echos content
     */
    function widget($args, $instance) {
        extract($args, EXTR_SKIP);
        $title = apply_filters('widget_title', $instance['title']);

        if ($instance['home_only'] == 'yes' && !is_front_page())
            return;
        $params = array(
            'numberposts' => $instance['post_qty'] ? $instance['post_qty'] : get_option('posts_per_page'),
            'post_type' => array('post')
        );
        /**
         * Post Category selection
         * Options:
         * All (default): all categories
         * categories: specified categories
         * current: only the current category and it's children
         * except: all except the current category and it's children
         */
        
        $tax_query = array(
                'taxonomy' => 'category',
                'field' => 'id'
            );
        // Specified categories
        if ($instance['posts_context'] == 'categories') {
            $tax_query['terms'] = (array) $instance['cat_ids'];
//            $params['category'] = implode(',', (array) $instance['cat_ids']);
        }
        // In relation to the current category context
        if ($instance['posts_context'] == 'current' || $instance['posts_context'] == 'except') {
            $categories = array();
            if (is_category()) {
                $categories[] = get_query_var('cat');
//                $params['category'] = $catid;
            } elseif (is_single()) {
                global $post;
                $post_categories = get_the_category($post->ID);
                foreach ($post_categories as $cat)
                    $categories[] = $cat->term_id;
//                $params['category'] = implode(',', $categories);
            }
            $tax_query['terms'] = $categories;
            if ($instance['posts_context'] == 'except')
                $tax_query['operator'] = 'NOT IN';
        }
        if ($instance['posts_context'] != 'all')
            $params['tax_query'] = array($tax_query);
        
        /**
         * Featured Posts
         * Either only featured or all except featured
         */
        if (!empty($instance['featured'])) {
            $featured_options = array(
                'key' => '_appsol_featured_post',
                'value' => 1,
            );
            $featured_options['compare'] = $instance['featured'] > 1 ? '!=' : '=';
            $params['meta_query'] = array($featured_options);
        }
        
        $latest_posts = get_posts($params);
        if (!empty($latest_posts)) {
            echo $before_widget;
            if (!empty($title)) {
                echo $before_title;
                echo $title;
                echo $after_title;
            }
            // Temporarily change the Excerpt Length
            $excerpt_length = get_option('excerpt_length');
            update_option('excerpt_length', 20);
            ?>
            <div class="bd latest-posts clearfix">
                <?php
                $index = 0;
                $zebra = true;
                $options = array();
                if (!$instance['image_size'])
                    $options['show_thumb'] = 0;
                else
                    $options['thumb_type'] = $instance['image_size'];
                foreach ($latest_posts as $the_post):

                    $index++;
                    $zebra = !$zebra;
                    $options['class'] = $zebra ? 'even' : 'odd';
                    // Start to output the post
                    ?>
                    <?php if (function_exists('dynamic_sidebar') && is_active_sidebar('latest_posts') && $instance['sidebar_pos'] == $index) : ?>
                        <div class="block interval">
                            <?php dynamic_sidebar('latest_posts'); ?>
                        </div>
                    <?php endif; ?>
                    <?php appsolEnvironment::the_archive_post($the_post, $options); ?>
                <?php endforeach;
                ?>
            </div>
            <?php
            echo $after_widget;
            update_option('excerpt_length', $excerpt_length);
        }
    }

    function update($new_instance, $old_instance) {
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
                $image_sizes = appsolImages::get_thumbnail_sizes();
                foreach ($image_sizes as $image_size => $sizes):
                    ?>
                    <option value="<?php echo $image_size; ?>"<?php if ($image_size == $instance['image_size']) echo ' selected="selected"'; ?>><?php echo $image_size . ' (' . implode('x', $sizes) . ')'; ?></option>
                <?php endforeach; ?>
            </select></p>
        <?php
    }

}

appsolLatestPostsWidget::init();