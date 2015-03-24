<?php
/**
 * ArchivePost
 * 
 * @package wp_posts_anywhere
 * @author Stuart Laverick
 */
namespace PostsAnywhere;

 class ArchivePost
 {
     /**
      * The Global WP_Post object
      *
      * @var object
      **/
     private $global_post;
     /**
      * Default Post options
      *
      * @var array
      **/
     private $default_options = array(
            'show_thumb' => 1,
            'tmb_type' => 'thumbnail',
            'tmb_vertical_position' => 'top',
            'tmb_horizontal_position' => 'left',
            'show_title' => 1,
            'show_excerpt' => 1,
            'excerpt_length' => 24,
            'max_chars' => 0,
            'show_tags' => 1,
            'show_categories' => 1,
            'show_comments' => 1,
            'show_date' => 1,
            'show_author' => 0,
            'square' => 0,
            'class' => ''
        );

     /**
      * Default Post Excerpt length
      *
      * @var int
      **/
     private $default_excerpt_length;

     /**
      * The Post options that will be applied
      *
      * @var array
      **/
     private $options;

     /**
      * Local WP_Post object
      *
      * @var object
      **/
     private $post;

     /**
     * Constructor
     * 
     * @global WP_Post $post The global post object
     * @param WP_Post $the_post A local post object
     * @param array $options The display options
     * @param bool $return Whether to display or return the html
     * @return void
     * @author Stuart Laverick
     */
     function __construct($the_post, $options = array())
     {
        // Store the current post object
        global $post;
        $this->global_post = $post;

        // Replace the global post with our local post object
        $this->post = $the_post;
        $post = $this->post;

        $this->options = array_merge($this->default_options, $options);
        $this->updateOptions();
     }

     /**
      * Update the global WP Options with the locally assigned ones
      *
      * @return void
      * @author Stuart Laverick
      **/
     private function updateOptions()
     {
        $this->default_excerpt_length = get_option('excerpt_length', '55');
        if (isset($this->options['excerpt_length']) && $this->default_excerpt_length != $this->options['excerpt_length']) {
            update_option('excerpt_length', $this->options['excerpt_length']);
        }

        if ($this->options['max_chars']) {
            update_option('excerpt_max_chars', $this->options['max_chars']);
        }
     }

     /**
      * Return the global WP Options to the manner we found them
      *
      * @return void
      * @author Stuart Laverick
      **/
     private function resetOptions()
     {
        global $post;
        $post = $this->global_post;
        wp_reset_postdata();

        if (isset($this->options['excerpt_length']) && $this->default_excerpt_length != $this->options['excerpt_length']) {
            update_option('excerpt_length', $this->default_excerpt_length);
        }
        if($this->options['max_chars']) {
            delete_option('excerpt_max_chars');
        }
     }

     /**
      * Creates the required media block of html based on the options
      *
      * @return string HTML of media block for image
      * @author 
      **/
     private function buildMediaBlock($post_id)
     {
        $tmb_id = get_post_thumbnail_id($post_id);

        if (!$tmb_id) {
          return '';
        }

        $attr = [
            'class' => 'media-object',
            'alt'=> trim(strip_tags(get_post_meta($tmb_id, '_wp_attachment_image_alt', true)))
        ];
        // get the img tag
        $tmb = wp_get_attachment_image($tmb_id, $this->options['tmb_type'], false, $attr);
        $media = ['<div class="media-' . $this->options['tmb_horizontal_position'] . ' media-' . $this->options['tmb_vertical_position'] . '">'];
        $media[] = '<a href="' . get_permalink($post_id) . '">' . $tmb . '</a>';
        $media[] = '</div>';

        return implode("\n", $media);
     }

     /**
      * The Wordpress function 'the_excerpt()' cannot be relied upon
      * Filters in the theme or other plugins can force it to render the current posts excerpt
      * rather than the archive post excerpt.
      * This method recreates the excerpt but for supplied text rather than the global $post
      *
      * @return string The created excerpt
      * @author Stuart Laverick
      **/
     private function createExcerpt($text)
     {
        $raw_excerpt = $text;
        $text = strip_shortcodes($text);
        $text = apply_filters('the_content', $text);
        $text = str_replace(']]>', ']]&gt;', $text);
        $text = strip_tags($text);
        $excerpt_length = apply_filters('excerpt_length', 55);
        $excerpt_more = apply_filters('excerpt_more', ' ' . '[...]');
        $text = wp_trim_words($text, $excerpt_length, $excerpt_more);

        return apply_filters('wp_trim_excerpt', $text, $raw_excerpt);
        }

     /**
      * Return the archive post html
      *
      * @return string HTML of the post
      * @author Stuart Laverick
      **/
     public function getArchivePost()
     {
        global $post;

        ob_start();
        ?>
        <div class="block post archive-post <?php echo $this->options['class']; ?>">
        <?php if ($this->options['tmb_type'] && $this->options['tmb_horizontal_position'] == 'left') echo $this->buildMediaBlock($post->ID); ?>
            <div class="body">
        <?php if ($this->options['show_title']): ?>
                <h4 class="heading"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
        <?php endif; ?>
        <?php if ($this->options['show_excerpt']): ?>
                <div class="excerpt">
                <?php echo $post->post_excerpt? $post->post_excerpt : $this->createExcerpt($post->post_content); ?>
                </div>
        <?php endif; ?>
                <div class="meta">
        <?php if ($this->options['show_author']): ?>
                    <p class="author">
                        <span class="user-picture"><?php echo get_avatar(get_the_author_meta('ID'), 96, '', get_the_author_meta('display_name')); ?></span>
                        <span class="display-name"><?php echo get_the_author_meta('first_name') . ' ' . get_the_author_meta('last_name') ?></span>
                    </p>
        <?php endif; ?>
        <?php if ($this->options['show_date']): ?>
                    <p class="date"><span class="name">Date:</span> <?php the_date(); ?></p>
        <?php endif; ?>
        <?php if ($this->options['show_tags']): ?>
                    <p class="tags"><span class="name">Tags:</span> <?php the_tags('', ', ', ''); ?></p>
        <?php endif; ?>
        <?php if ($this->options['show_categories']): ?>
                    <p class="categories"><span class="name">Posted in:</span> <?php the_category(', '); ?></p>
        <?php endif; ?>
        <?php if (comments_open($post->ID) && $this->options['show_comments']): ?>
                    <p class="comments"><span class="name">Comments:</span> <?php comments_popup_link('No Comments &#187;', '1 Comment &#187;', '% Comments &#187;'); ?></p>
        <?php endif; ?>
                </div>
            </div>
        <?php if ($this->options['tmb_type'] && $this->options['tmb_horizontal_position'] == 'right') echo $this->buildMediaBlock($post->ID); ?>
        </div>
        <?php
        $this->resetOptions();
        return ob_get_clean();
     }

     /**
      * Renders the archive post
      *
      * @return void
      * @author Stuart Laverick
      **/
     public function theArchivePost()
     {
        echo $this->getArchivePost();
     }
 }