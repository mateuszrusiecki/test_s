<!DOCTYPE html>
<html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>"/>
        <meta name="theme-color" content="#AD5025">
        <meta name="detectify-verification" content="e20c4a7be230805104cd7d2fdfb98720" />
        <title><?php wp_title('|', true, 'left'); ?></title>
        <?php wp_head(); ?>
        <script type="text/javascript">var $ = jQuery;</script>
        <link rel="shortcut icon" type="image/x-icon" href="<?php echo ( get_template_directory_uri() ) . '/' ?>images/favicon.ico">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1">
        <link rel="stylesheet" type="text/css" href="<?php bloginfo('stylesheet_url'); ?>"/>
        <link rel="stylesheet" type="text/css" href="<?php echo get_template_directory_uri().'/css/adept.css'; ?>"/>
        <script id="mcjs">!function(c,h,i,m,p){m=c.createElement(h),p=c.getElementsByTagName(h)[0],m.async=1,m.src=i,p.parentNode.insertBefore(m,p)}(document,"script","https://chimpstatic.com/mcjs-connected/js/users/364f9223cfbffa89b6b7ef474/40cb6bd4757a5e661f3ddea5f.js");</script>
        <!--[if lt IE 9]>
            <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js">
                nav ul li a {fo}
            </script>
        <![endif]-->
    </head>
    <body <?php global $post; body_class( $post->post_name ); ?>>
        <!-- wrapper starts -->
        <div class="wrapper">
            <!-- header starts -->
            <div class="banner">
                <header>
                    <nav class="navbar navbar-expand-md">
                        <div class="container-fluid p-0">
                            <button class="navbar-toggler nav-icon navbar-toggler-right" type="button" data-toggle="collapse" data-target="#navb-mobile">
                                <span></span><span></span><span></span>
                            </button>

                            <a id="logo-mobile" class="navbar-brand logo align-self-start" href="<?php echo site_url(); ?>"><figure><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/logo.svg" alt="logo"></figure></a>
                            <div class="collapse navbar-collapse" id="navb-mobile">
                            <?php
                                $regex = '/<li(.*?)class="(.*?)"><a(.*?)>(.*?)<\/a><\/li>/';
                                $replace_callback = function( $matches ) {
                                    $active_string = strstr( $matches[2], 'current-menu-item' ) === false ? '' : ' active';
                                    return '<li' . $matches[1] . 'class="' . $matches[2] . ' nav-item"><a class="nav-link' . $active_string . '"' . $matches[3] . '>' . mb_strtoupper( $matches[4] ) . '</a></li>';
                                };
                                $ul_left = preg_replace_callback( $regex, $replace_callback, wp_nav_menu( [ 'theme_location' => 'left-top', 'container' => 'ul', 'menu_class' => 'navbar-nav mr-auto', 'echo' => false ] ) );

                                $replace_callback = function( $matches ) {
                                    $active_string = strstr( $matches[2], 'current-menu-item' ) === false ? '' : ' active';

                                    $li_class = 'dsply-blck ';
                                    if( stristr( $matches[4], '__none__' ) !== false ) {
                                        $matches[4] = str_replace( '__none__', '', $matches[4] );
                                        $li_class = 'dsply-none ';
                                    }
                                    if( stristr( $matches[4], 'account' ) !== false ) {
                                        // $active_string = ' active mijn-account';
                                        if( ! is_user_logged_in() ) {
                                            $matches[4] = 'Login';
                                            $active_string = ' login';
                                        }
                                    }

                                    if( stristr( $matches[4], '__cart__' ) !== false ) {
                                        $matches[4] = str_replace( '__cart__', '', $matches[4] );
                                        $active_string .= ' cart shop';
                                        return '<li' . $matches[1] . 'class="' . $li_class . $matches[2] . ' nav-item"><a class="nav-link' . $active_string . '" href="javascript:;">' . mb_strtoupper( $matches[4] ) . '</a></li>';
                                    }


                                    return '<li' . $matches[1] . 'class="' . $li_class . $matches[2] . ' nav-item"><a class="nav-link' . $active_string . '"' . $matches[3] . '>' . mb_strtoupper( $matches[4] ) . '</a></li>';
                                };
                                $ul_right = preg_replace_callback( $regex, $replace_callback, wp_nav_menu( [ 'theme_location' => 'right-top', 'container' => 'ul', 'menu_class' => 'navbar-nav my-2 my-md-0 p-0', 'echo' => false ] ) );
                                echo $ul_left . $ul_right;
                            ?>
                            </div>

                            <div id="navb"><?php echo $ul_left . '<a class="logo align-self-start" href="' . site_url() . '"><figure><img src="' . get_stylesheet_directory_uri() . '/images/logo.svg" alt="logo"></figure></a>' . $ul_right; ?></div>
                            <!--<a href="javascript:;" class="shop">
                                <i class="far fa-shopping-cart" aria-hidden="true"></i>
                            </a>-->
                            <a class="shop-new">
                                <i class="far fa-shopping-cart" aria-hidden="true"></i>
                            </a>
                        </div>
                    </nav>
                </header>
                <div id="dropdown-cart">
	                <h4 class="pl-3 pt-3" style="display: inline-block;">Jouw winkelmand</h4>
	                <p class="pl-3" style="display: inline-block; font-size: 14px;"><span id="drop-aantal"></span></p>
	                <hr />
				    <div class="dropdown-products">
					    
				    </div>
				    <hr />
				    <div class="row">
					    <div class="col-8">
						    <p class="pl-3">Verzending</p>
					    </div>
					    <div class="col-4">
					    	<p style="float: right;margin-right: 15px;">Gratis</p>
					    </div>
				    </div>
				    <br />
				    <div class="row">
					    <div class="col-8">
						    <p class="pl-3" style="font-family: 'TheWave-Bd';">Totaal te betalen</p>
					    </div>
					    <div class="col-4">
					    	<p class="dropdown-price" style="font-family: 'TheWave-Bd'; color: #d45a1d; float: right;margin-right: 15px;">&euro;<span id="dropdown-price"></span></p>
					    </div>
					    <div class="col-12 text-center">
<!--						    <a href="http://shop.shaversclub.nl/cart" class="set">Naar winkelmand</a>-->
<!--                            <a href="http://localhost:8075/staging-2/winkelwagen" class="set">Naar winkelmand</a>-->
                            <a href="http://localhost:8075/staging-2/?page_id=9109717" class="set">Naar winkelmand</a>
					    </div>
				    </div>
			    </div>
            </div>
