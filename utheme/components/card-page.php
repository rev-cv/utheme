<?php

/**
 * Template part for displaying a page card.
 */
?>
<div class="card-page">
    <div class="card-page__title"><?php the_title(); ?></div>
    <img src="<?php echo get_the_post_thumbnail_url(get_the_ID(), 'large'); ?>" alt="<?php the_title_attribute(); ?>">
    <a href="<?php the_permalink(); ?>" class="card-page__link"></a>
</div>