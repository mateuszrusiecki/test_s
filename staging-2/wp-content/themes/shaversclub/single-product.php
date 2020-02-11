<?php
/**
 * Template name: Single product
 */

wp_enqueue_style( 'slick', get_template_directory_uri() . '/css/slick.css' );
add_thickbox();

get_header();
while ( have_posts() ):

    the_post();
    $product = new Product( get_the_ID() );
    $op = $product->get_other_recurring_product();

    $term_list = get_the_terms( $product->ID, 'product-tag' );
    $system;
    $system_diff = 0;

    foreach( $term_list as $term ) {
        $system = Product::query_one( array(
            'name' => $term->name,
            'tax_query' => [
                [ 'taxonomy' => 'product-category', 'field' => 'slug', 'terms' => 'scheersystemen' ],
            ],
        ) );
        if( $system ) {
            $system_diff = $product->get_price() - $system->get_price();
            break;
        }
    }
    ?>
    <div id="productpage-splash" class="banner-in">
        <figure class="<?php if($op) echo 'figure-for-other-recurring-product' ?>">
            <!-- <img src="<?php echo ( get_template_directory_uri() ) . '/' ?>images/productpagina-banner.jpg" alt="banner" width="1600" height="800"> -->
        </figure>
        <div class="productpagina-banner <?php if($op) echo 'with-other-recurring-product' ?>">
            <div class="container">
                <div class="productpagina-banner-main">
                    <div class="product-wrap d-flex flex-wrap">
                        <div class="product-lt col-md-6">
                            <div class="slider-for">
                                <?php

                                $shipping_price = $product->get_shipping();
                                $shipping_price_text = empty( $shipping_price ) ? 'Gratis thuisbezorgd' : HelperFunctions::format_price( $shipping_price );

                                $full = wp_get_attachment_image_src( get_post_thumbnail_id( $product->ID ), 'full' );

                                $image_gallery_ids = get_post_meta( $product->ID, 'image_gallery_ids', true );
                                $images = array_filter( explode( ',', $image_gallery_ids ) );
                                $thumbs = [];
                                foreach( $images as $id ) {

                                    $gallery_thumb = wp_get_attachment_image_src( $id, 'thumbnail' );
                                    $gallery_full = wp_get_attachment_image_src( $id, 'full' );
                                    if( ! $gallery_thumb || ! $gallery_full ) {
                                        continue;
                                    }
                                    $thumbs[ $gallery_thumb[0] ] = $gallery_full[0];
                                }
                                ?>
                                <div class="product-dtls">
                                    <a class="product-url" href="javascript:void(0)">
                                        <figure>
                                            <img src="<?php echo $full[0]; ?>">
                                        </figure>
                                    </a>

                                    <div>
                                        <?php /*echo do_shortcode( '[gallery ids="' . $image_gallery_ids . '" link="file" columns="0"]' );*/ ?>
                                        <ul class="slider-nav">
                                            <?php foreach( $thumbs as $thumb => $full ): ?>
                                                <li>
                                                    <a href="<?php echo $full; ?>" class="thickbox" rel="gallery">
                                                        <figure>
                                                            <img src="<?php echo $thumb; ?>" height="100">
                                                        </figure>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <div class="clearfix"></div>
                                    </div>
                                    <div class="product-rt d-md-none">
                                        <h2><?php the_title(); ?></h2>
                                        <h4><?php echo $product->meta( 'product_line' ); ?> <?php echo $product->meta( 'subline' ); ?></h4>

                                        <div class="homedelivary">
                                            <ul>
                                                <?php
                                                $is_serviceable = get_post_meta( $post->ID, 'is_serviceable', true );
                                                if($is_serviceable) { ?>
                                                    <li class="type-aankoop-click type-aankoop type-aankoop-nalever active">
                                                        <input type="radio" name="type-aankoop" value="nalever" id="type-aankoop-nalever-1" checked>
                                                        <i class="fas fa-check-circle"></i>
                                                        <i class="fal fa-circle"></i>

                                                        <label for="type-aankoop-nalever">
                                                            <b>Met ShaversClub Naleverservice</b>
                                                            <br/>Bepaal zelf wanneer en om de hoeveel tijd jouw nieuwe mesjes geleverd worden.
                                                            <br/>Je zit nergens aan vast.
                                                        </label>
                                                        <span>
                                                    €<?php echo $product->get_price( false ); ?>
                                                </span>

                                                    </li>
                                                    <li class="type-aankoop-click type-aankoop type-aankoop-eenmalig">
                                                        <input type="radio" name="type-aankoop" value="eenmalig" id="type-aankoop-eenmalig-1">
                                                        <i class="fas fa-check-circle"></i>
                                                        <i class="fal fa-circle"></i>

                                                        <label for="type-aankoop-eenmalig"><b>Eenmalig bestellen</b></label>

                                                        <span>
                                                    €<?php echo $product->get_price( false) + 2; ?>
                                                </span>
                                                    </li>
                                                <?php } ?>
                                                <?php
                                                //if( $op ) {
                                                //echo '<li class="like-h4">' . $product->meta( 'product_line' ) . ' Navullingen:</li>';
                                                //echo '<li><a href="' . get_permalink( $op->ID ) . '" class="set meest">' . get_the_title( $op->ID ) . ' - (' . $op->get_price( true ) . ')</a></li>';
                                                //} ?>
                                            </ul>
                                        </div>

                                        <div class="bestel-directly">
                                            <ul class="d-flex flex-wrap">
                                                <li><input value="1" name="quantity" class="handleCounter"></li>
                                                <!--<li><a href="javascript:;" data-id="<?php echo $product->ID; ?>" class="set add-to-cart">Bestel direct!</a></li>-->
                                                <li><a href="javascript:;" data-id="<?php echo $product->ID; ?>" class="set new-add-to-cart">Bestel direct!</a></li>
                                                <li class="shipping-price-label"><span class="grey-light"><?php echo $shipping_price_text; ?></span></li>
                                            </ul>
                                        </div>

                                        <?php
                                        if($op) {
                                            echo '<div class="other-recurring-product d-flex flex-wrap">';
                                            $opImages = wp_get_attachment_image_src( get_post_thumbnail_id( $op->ID ) );
                                            $opImg = '';
                                            if(isset($opImages[0])) {
                                                $opImg = $opImages[0];
                                            }
                                            //var_dump($op);
                                            $title = get_the_title( $op->ID );

                                            echo '<input type="hidden" name="op-id" id="op-id" value="'. $op->ID .'" />';

                                            echo "<div class='op-img'><img style='min-height: 1px' src='$opImg' alt='$title' /></div>";
                                            //$op->meta( 'subline' )
                                            echo '<div class="op-desc">';
                                            echo '<span><b>Dit is de ' .$op->meta( 'product_line' ). ' navulling</b></span>';
                                            echo '<span class="grey-light">'. $title .' voor €'. $op->get_price() .'</span>';
                                            echo '<span class="grey-light"><a href=" '. get_permalink( $op->ID ) .'" class="">Klik hier</a> om ze direct te bestellen </span>';
                                            echo '</div>';
                                            echo '</div>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="product-rt d-none d-md-block col-md-6">
                            <h2><?php the_title(); ?></h2>
                            <h4><?php echo $product->meta( 'product_line' ); ?> <span><?php echo $product->meta( 'subline' ); ?></span></h4>
                            <div class="homedelivary">
                                <ul>
                                    <?php
                                    $is_serviceable = get_post_meta( $post->ID, 'is_serviceable', true );
                                    if($is_serviceable) { ?>
                                        <li class="type-aankoop type-aankoop-nalever active">
                                            <input type="radio" name="type-aankoop" value="nalever" id="type-aankoop-nalever-2" checked>
                                            <i class="fas fa-check-circle"></i>
                                            <i class="fal fa-circle"></i>

                                            <label for="type-aankoop-nalever">
                                                <b>Met ShaversClub Naleverservice</b>
                                                <br/>Bepaal zelf wanneer en om de hoeveel tijd jouw nieuwe mesjes geleverd worden.
                                                <br/>Je zit nergens aan vast.
                                            </label>
                                            <span>
                                                    €<?php echo $product->get_price( false ); ?>
                                                </span>

                                        </li>
                                        <li class="type-aankoop type-aankoop-eenmalig">
                                            <input type="radio" name="type-aankoop" value="eenmalig" id="type-aankoop-eenmalig-2">
                                            <i class="fas fa-check-circle"></i>
                                            <i class="fal fa-circle"></i>

                                            <label for="type-aankoop-eenmalig"><b>Eenmalig bestellen</b></label>

                                            <span>
                                                    €<?php echo $product->get_price( false) + 2; ?>
                                                </span>
                                        </li>
                                    <?php } ?>
                                    <?php
                                    //if( $op ) {
                                    //echo '<li class="like-h4">' . $product->meta( 'product_line' ) . ' Navullingen:</li>';
                                    //echo '<li><a href="' . get_permalink( $op->ID ) . '" class="set meest">' . get_the_title( $op->ID ) . ' - (' . $op->get_price( true ) . ')</a></li>';
                                    //} ?>
                                </ul>
                            </div>
                            <div class="bestel-directly">
                                <ul class="d-flex flex-wrap">
                                    <li><input value="1" name="quantity" class="handleCounter"></li>
                                    <!--<li><a href="javascript:;" data-id="<?php echo $product->ID; ?>" class="set add-to-cart">Bestel direct!</a></li>-->
                                    <li><a href="javascript:;" data-id="<?php echo $product->ID; ?>" class="set new-add-to-cart">Bestel direct!</a></li>
                                    <li class="shipping-price-label"><span class="grey-light"><?php echo $shipping_price_text; ?></span></li>
                                </ul>
                            </div>

                            <?php
                            if($op) {
                                echo '<div class="other-recurring-product d-flex flex-wrap">';
                                $opImages = wp_get_attachment_image_src( get_post_thumbnail_id( $op->ID ) );
                                $opImg = '';
                                if(isset($opImages[0])) {
                                    $opImg = $opImages[0];
                                }
                                //var_dump($op);
                                $title = get_the_title( $op->ID );

                                echo '<input type="hidden" name="op-id" id="op-id" value="'. $op->ID .'" />';

                                echo "<div class='op-img'><img style='min-height: 1px' src='$opImg' alt='$title' /></div>";
                                //$op->meta( 'subline' )
                                echo '<div class="op-desc">';
                                echo '<span><b>Dit is de ' .$op->meta( 'product_line' ). ' navulling</b></span>';
                                echo '<span class="grey-light">'. $title .' voor €'. $op->get_price() .'</span>';
                                echo '<span class="grey-light"><a href=" '. get_permalink( $op->ID ) .'" class="">Klik hier</a> om ze direct te bestellen </span>';
                                echo '</div>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                        <div class="bestel-directly d-md-none" style="display: none">
                            <ul class="d-flex flex-wrap">
                                <li><input value="1" name="quantity" class="handleCounter"></li>
                                <li class=""><a href="javascript:;" data-id="<?php echo $product->ID; ?>" class="set add-to-cart">Bestel direct!</a></li>
                                <?php /*if( $system ): ?>
									<li><a href="<?php echo esc_url( get_permalink( $system->ID ) ); ?>" class="wt"><span>Bestel met</span> starterkit</a></li>
								<?php endif;*/ ?>
                            </ul>

                            <?php /*if( $system_diff > 0 ): ?>
								<p class="text-right">en bespaar <b><?php echo HelperFunctions::format_price( $system_diff ) ; ?></b></p>
							<?php endif;*/ ?>
                        </div>

                    </div>
                    <div class="mesjes-out">
                        <ul class="row">
                            <?php foreach( get_post_meta( $product->ID, 'usp' ) as $usp ) {
                                echo "<li class=\"col-lg-4 col-md-4\"><p>$usp</p></li>";
                            } ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript">
      document.querySelector( "body > .wrapper > .banner" ).appendChild( document.getElementById( "productpage-splash" ) );
    </script>

    <!-- gamechanger starts -->
    <div class="gamechanger">
        <div class="container">
            <div class="gamechanger-main d-flex flex-wrap">
                <?php the_content(); ?>
            </div>
        </div>
    </div>
    <!-- gamechanger ends -->
    <!-- product-main starts -->
    <div class="product-main product-main2">
        <div class="product-content product-content2">
            <div class="container">
                <div class="row flex-column">
                    <div class="product-content-head text-center">
                        <h2>Klanten<br> kochten ook...</h2>
                        <!-- <p>Je blijft altijd in controle; pas je service aan of<br> pauzeer in je account waneer je wilt.</p> -->
                    </div>
                    <ul class="d-flex flex-wrap">
                        <?php
                        $other_products  = Product::query( [
                            'posts_per_page' => 4,
                            'post__not_in' => [ $product->ID ],
                            'tax_query' => [
                                [ 'taxonomy' => 'product-category', 'field' => 'slug', 'terms' => [ 'accessoires', 'verzorging', 'scheermesjes' ] ],
                            ],
                            'orderby' => 'rand',
                            // 'order' => 'ASC',
                        ] );
                        foreach( $other_products as $i => $op ):

                            $thumb_id = get_post_thumbnail_id( $op->ID );

                            $big_thumb = wp_get_attachment_image_src( $thumb_id, 'medium' );
                            if( ! $big_thumb ) {
                                $big_thumb = wp_get_attachment_image_src( $thumb_id, 'full' );
                            }
                            $small_thumb = wp_get_attachment_image_src( $thumb_id );
                            if( ! $small_thumb ) {
                                $small_thumb = $big_thumb;
                            }
                            $show_class = ( $i == 0 ) ? ' show1' : '' ;
                            ?>
                            <li class="col-md-4 col-6<?php echo $show_class; ?>">
                                <div class="soap-in product-content-1">
                                    <figure class="img-height1">
                                        <img src="<?php echo $big_thumb[0]; ?>" class="hide3">
                                        <img src="<?php echo $big_thumb[0]; ?>" class="show1">
                                    </figure>
                                    <div class="overlay">
                                        <h6><?php echo $op->meta( 'product_line' ); ?></h6>
                                        <h4><?php echo $op->meta( 'frontend_title' ); ?></h4>
                                        <ul class="d-flex flex-wrap">
                                            <li class="col"><span><?php echo $op->meta( 'contents' ); ?></span></li>
                                            <li class="col"><span><?php echo $op->get_price( true ); ?></span></li>
                                        </ul>
                                        <a href="<?php echo get_permalink( $op->ID ); ?>" class="set">Bestel direct</a>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <!-- product-main ends -->
    <?php
    echo do_shortcode( '[shaversclub_countries][shaversclub_zeggen]' );
    add_action( 'wp_footer', function() { ?>
        <script type="text/javascript">
          $( ".type-aankoop" ).on('click', function() {
            $( ".type-aankoop.active" ).removeClass('active');
            $(this).addClass('active');
            console.log('++');
            $(this).find('input').prop('checked', true)
          });

          ( function( $ ) {
            // return;
            $( document ).ready( function( e ) {

              $( ".slider-nav" ).slick( {
                slidesToShow: 3,
                slidesToScroll: 1,
                arrows: true,
                autoplay:true,
                speed: 300,
                autoplaySpeed: 4700,
                adaptiveHeight:false,
                pauseOnHover:false,
                pauseOnFocus:false,
                focusOnSelect: true,
                draggable: true,
                infinite: true,
              } );


            } );
          } )( jQuery );
        </script>
        <?php
    } );
endwhile;
get_footer();
