<?php
/**
 * Template Name: SITEMAP
 */

if ( ! class_exists( 'Custom_Sitemap_Walker' ) ) {
    class Custom_Sitemap_Walker extends Walker_Page {
        function start_el( &$output, $page, $depth = 0, $args = [], $current_page = 0 ) {
            $indent = str_repeat( "\t", $depth );
            
            $output .= $indent . '<li>';
            $output .= '<a href="' . get_permalink( $page->ID ) . '">' . apply_filters( 'the_title', $page->post_title, $page->ID ) . '</a>';

            if ( $depth > 0 ) {
                $date = get_the_modified_date( '', $page->ID );
                $output .= ' <time>' . $date . '</time>';
            }
        }
    }
}

get_header();
$current_page_id = get_the_ID();
$title_pages = function_exists( 'get_site_translation' ) ? get_site_translation( 'pages' ) : 'Pages';
$title_posts = function_exists( 'get_site_translation' ) ? get_site_translation( 'news' ) : 'News';
?>

<main id="site-main" class="site-main">
    <div class="sitemap-container">
        
        <header class="sitemap-header">
            <h1><?php the_title(); ?></h1>
        </header>

        <div class="sitemap-content">
            <?php the_content(); ?>

            <section class="sitemap-section">
                <h2><?php echo esc_html( $title_pages ); ?></h2>
                <ul>
                    <?php
                    wp_list_pages( [
                        'title_li' => '',
                        'exclude'  => $current_page_id,
                        'walker'   => new Custom_Sitemap_Walker()
                    ] );
                    ?>
                </ul>
            </section>

            <section class="sitemap-section">
                <h2><?php echo esc_html( $title_posts ); ?></h2>
                <ul>
                    <?php
                    $archive_query = new WP_Query([
                        'post_type'      => 'post',
                        'posts_per_page' => -1,
                        'post_status'    => 'publish'
                    ]);

                    if ( $archive_query->have_posts() ) :
                        while ( $archive_query->have_posts() ) : $archive_query->the_post(); ?>
                            <li>
                                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                <time><?php echo get_the_modified_date(); ?></time>
                            </li>
                        <?php endwhile;
                        wp_reset_postdata();
                    endif;
                    ?>
                </ul>
            </section>
        </div>
    </div>
</main>

<?php get_footer(); ?>