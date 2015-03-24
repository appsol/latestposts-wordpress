<?php
/**
 * Template Functions
 * 
 * @package wp_posts_anywhere
 * @author Stuart Laverick
 */
namespace PostsAnywhere;

/**
 * Template function to create a post list
 * 
 * Options:
 * post_qty => Integer: Number of posts to show
 * posts_context => String: one of 'all', 'current', 'except', 'categories'
 * featured => Integer: show all posts (0) only featured (1) or no featured (2)
 * cat_ids => Array: category ids to use
 * sidebar_pos => Integer: the index after which to insert the sidebar, or no sidebar (0)
 * image_size => String: the featured image size, leave empty for no image
 * archive_post => Array: control the Archive Post rendering (see ArchivePost:default_options)
 *
 * @return String the rendered html for the posts collection
 * @author Stuart Laverick
 **/
function wp_posts_anywhere($options = [])
{
    $latestPosts = PostsAnywhereWidget::getInstance();

    $default_options = [
            'post_qty' => get_option('posts_per_page'),
            'posts_context' => 'all',
            'featured' => 0,
            'cat_ids' => [],
            'sidebar_pos' => 0,
            'image_size' => 'thumbnail',
            'archive_post' => []
        ];

        if (isset($options['image_size'])) {
            $options['image_size'] = $latestPosts->checkImageSize($options['image_size']);
        }

        if (isset($options['posts_context'])) {
            if(!in_array($options['posts_context'], ['all', 'current', 'except', 'categories'])) {
                $options['posts_context'] == 'all';
            }
        }

        $options = array_merge($default_options, $options);

        echo $latestPosts->buildLatestPosts($options);
}