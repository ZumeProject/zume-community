<?php
/**
 * Template part for displaying a single post
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class( '' ); ?> role="article" itemscope itemtype="http://schema.org/BlogPosting">

    <header class="article-header center margin-top-2">
        <?php the_post_thumbnail(  ); ?>
        <h2 class="entry-title single-title vertical-padding" itemprop="headline"><?php the_title(); ?></h2>
    </header> <!-- end article header -->

    <section class="entry-content" itemprop="text">

        <?php the_content(); ?>
    </section> <!-- end article section -->

    <footer class="article-footer">
        <?php wp_link_pages( array(
            'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'zume' ),
            'after'  => '</div>'
        ) ); ?>

        <p><?php echo get_the_date() ?></p>
        <p class="tags"><?php the_tags( '<span class="tags-title">' . __( 'Tags:', 'zume' ) . '</span> ', ', ', '' ); ?></p>
    </footer> <!-- end article footer -->

</article> <!-- end article -->
