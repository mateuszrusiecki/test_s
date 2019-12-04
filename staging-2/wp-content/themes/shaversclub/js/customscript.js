
jQuery.fn.selectText = function(){
    var doc = document
        , element = this[0]
        , range, selection
        ;

    if( doc.body.createTextRange ) {
        range = document.body.createTextRange();
        range.moveToElementText( element );
        range.select();
    } else if( window.getSelection ) {
        selection = window.getSelection();
        range = document.createRange();
        range.selectNodeContents( element );
        selection.removeAllRanges();
        selection.addRange( range );
    }
};


// http://stackoverflow.com/a/6663901
jQuery.cookieEnabled = function() {
	if (navigator.cookieEnabled) {
		return true;
	}

	document.cookie = "cookietest=1";
	var ret = document.cookie.indexOf("cookietest=") != -1;
	document.cookie = "cookietest=1; expires=Thu, 01-Jan-1970 00:00:01 GMT";

	return ret;
};

jQuery( document ).on( 'heartbeat-send', function ( event, data ) {
    data.ss_check_session = 1;
} );

jQuery( document ).on( 'heartbeat-tick', function ( event, data ) {
    if( data.ss_refresh ) {
        window.location.reload( true );
    }
} );

// jQuery(".shop-new").on("click", function(){
// 	jQuery("#dropdown-cart").fadeToggle(750);
// });

// $('body').on('click','.btn-remove-drop',function(){
// 	//remove_from_cart_action( id );
// 	var id = $(this).data().id;
// 	console.log("remove: " + id);
// 	remove_from_cart_action_dropdown( id );
// });

//not used anymore
function process_dropdown_cart(){
    return;
	$.post( urls.ajax, { action: 'ss_get_cart_status' }, function( response ) {
        
        console.log("process_dropdown_cart");
        $( '.dropdown-products' ).html("");

        var i, o, item_html, mobile_selection = false;
		console.log(response);
		
		
		var i = 0;
		for( id in response.products ) {
        	i++;
        }
                
		console.log("aantal producten: " + i);
		if(i == 0){
		//	$("#winkelwagen-titel").text("De winkelwagen is leeg");
		} else {
		//	$("#winkelwagen-titel").text("Winkelwagen");
		}
		


        if( response && response.products ) {
			var i = 0;
            for( id in response.products ) {
	            
                o = response.products[ id ];

                if( ! mobile_selection ) {
                    $('#step-12 .selection img').attr( 'src', o.img );
                    $('#step-12 .selection h3').html( 'Jouw selectie <span>' +  o.title + '</span>' );
                    $('#step-12 .remove-from-cart').show();
                    $( '.wizard_steps' ).data( 'id', id );
                    mobile_selection = true;
                }
                var product_class="los-product-even";
                console.log(o);
                if(i % 2 === 0){
	                product_class="los-product-oneven";
                }
                
                //product_class names zijn omgekeerd, maar was lui om dit aan te passen
                
                item_html_new = ''
+'					<div class="drop-prod" data-price="'+ o.price +'" data-id="'+id+'">'
+'							<div class="row">'
+'								<div class="col-lg-12">'
+'									<div class="row">'
+'										<div class="col-3">'
+'											<img src="' + o.img + '" class="img-responsive" />'
+'				 						</div>'
+'										<div class="col-9 align-self-center">'
+'											<div><p style="font-family: \'TheWave-DBd\';"><span>' + o.title + '</span></p></div>'
//+'												<a href="#">+ Bespaar &euro;1,50 met ShaversClub Naleverservice</a>'
+' 											<div class="second-row" style="display:flex;">'
+'												<div class="col-9 p-0" style="display:flex;">'
+'													<div class="handle-counter align-self-center" style="border: 0px;">'
+ '                                    					<button class="counter-minus btn btn-primary">-</button>'
+ '                                    					<input type="text" id="quantity-'+id+'" value="' + o.quantity + '">'
+ '                                    					<button class="counter-plus btn btn-primary">+</button>'
+	 '                                				</div>'
+'													<p style="padding: 8px; font-size: 20px;"><span style="color: #d45a1d;">&euro;</span><span class="item-price" style="font-family: \'TheWave-DBd\';color: #d45a1d">' + o.price + '</span></p>'
+'												</div>'
+'												<div class="col-3">'
+'													<a data-id="'+id+'" class="btn-remove-drop" style="float: right;"><i class="fa fa-trash"></i></a></div>'
+'												</div>'
+'										</div>'	
+'									</div>'
+'								</div>'
+'							</div>'
+'						</div>'
					;

					console.log(item_html);

                //$( '.product-summary-wrap' ).append( item_html );
                $( '.dropdown-products' ).append( item_html_new );
                //$( '#loose-items-' + id ).data( 'id', id );
                
				i++;
            }
            $( '.product-summary-wrap' ).show();
        }

        $( '.article-count' ).html( $( '.subscription-summary-wrap .loose-items-in, .product-summary-wrap .loose-items-in' ).length );

        //$( '.subscription-summary-wrap .handle-counter, .product-summary-wrap .handle-counter' ).each( function( k, v ) {
	    $( '.dropdown-products .handle-counter' ).each( function( k, v ) {
            $( v ).handleCounter( {
                writable: true,
                onChange: calculateDropTotal,
                minimum: 0,
            } );
        } );

        $( '.subscription-summary-wrap .write-pencil i' ).on( 'click', function( e ) {
            toStart();
            serviceableAction( true );
        } );
        calculateDropTotal();

        $( '#overlay-loader' ).hide();

    }, 'json' );
}

function show_cart( pre_add ) {
    $('.wrapper > :not(.banner), .wrapper > .banner > :not(header), #fb-root').addClass( 'cart-hide' );
    $( '.navbar-toggler.open' ).click();
    $('#step1').fadeIn();
    setTimeout( function( e ) {

        var cart_empty = $( '.subscription-summary-wrap .loose-items-in, .product-summary-wrap .loose-items-in' ).length == 0;

        if( $( '.wizard_step.active' ).length == 0 ) {
            $('.shaver-in').addClass('active');
            if( ! $('.wizard_steps').data( 'id' ) ) {

                if( cart_empty && ( ! pre_add ) ) {
                    emptyCartAction();
                } else {
                    nextStepAction();
                }
            }
        } else {
            if( cart_empty && ( ! pre_add ) ) {
                emptyCartAction();
            }
        }
    }, 0 );
}

function hide_cart() {
    $('.wrapper > :not(.banner), .wrapper > .banner > :not(header), #fb-root').removeClass( 'cart-hide' );
    $('#step1').fadeOut();
    // setTimeout( function( e ) { $('.shaver-in').addClass('active'); }, 0 );
}

function select_interval( e ) {

    var card = $( e.target ).closest( '.shaver-main' );

    $( '.shaver-main.active' ).removeClass( 'active' );
    card.addClass( 'active' );

    $( '#overlay-loader' ).show();
    $.post( urls.ajax, {
        action: 'select_interval',
        interval: card.data( 'interval' ),
    }, function( response ) {
        $( '#overlay-loader' ).hide();
        if( response.status == 'error' ) {
            popup_alert( response.message );
        } else {
            process_cart_status();
            // console.log(response);

        }
    }, 'json' );
}

function serviceableAction( skip_cart_action ) {
    console.log('serviceableAction');
    console.log($('#step-12').css('display'));
    var ws = $( '.wizard_step' ).eq( 0 );
    if( $( '.wizard_steps' ).data( 'is_serviceable' ) ) {

        ws.find( 'ul' ).show()
            .find( '.recurring_price' )
            .html( $('#step-12 .selection' ).data( 'recurring_price' ) );

        ws.find( '> p:first' ).html( 'Wij kunnen onze <span class="product-name">' + $('#step-12 .selection' ).data( 'recurring_product_name' ) + '</span> periodiek aan je versturen, zodat je nooit zonder zit.' );
        ws.find( 'h3' ).html( 'Hoe vaak scheer je je?' );
        ws.find( '> p:last' ).html( 'Je blijft altijd in controle; pas je service aan of<br>pauzeer in je account waneer je wilt.' );
        ws.find( '.order-once, .order-recurring' ).show();
    } else {

        if( ! skip_cart_action ) {
            add_to_cart_action( false, true );
        }

        $( '.para-none' ).show();
        $( '.shaver-bar' ).addClass( 'step2-active' );
        $( '.shaver-end ul li' ).eq( 1 ).addClass( 'active' );
        step_current = 1;
        goToStep( step_current );
        ws.find( 'ul' ).hide();
        ws.find( '> p:first' ).html( '' );
        ws.find( 'h3' ).html( 'Eenmalige bestelling' );
        ws.find( '> p:last' ).html( 'Dit product kunnen we niet als periodieke serivce leveren.' );
        ws.find( '.order-once' ).hide();
        ws.find( '.order-recurring' ).show();
    }

    $( '.shaver-end' ).show();
}

function add_to_cart_action( next, once ) {
    var id = $( '.wizard_steps' ).data( 'id' ),
        once = once || ( ! $( '.wizard_steps' ).data( 'is_serviceable' ) );

    console.log('add_to_cart_action' + id);
    console.log($('#step-12').css('display'));

    if( ! id ) {
        popup_alert( 'Selecteer eerst een product' );
        return false;
    }

    $( '#overlay-loader' ).show();
    if( once ) {
        $.post( urls.ajax, { action: 'ss_add_to_cart', id: id, quantity: $( '.wizard_steps' ).data( 'quantity' ) }, function( response ) {
            $( '#overlay-loader' ).hide();
            if( response && ( response.status == 'success' ) && next ) {
                nextStepAction();
            }
        }, 'json' );

    } else {

        $( '#step-12 .shaver-main.active' ).click();

        $.post( urls.ajax, { 'action': 'select_product', 'id': id, quantity: $( '.wizard_steps' ).data( 'quantity' ) }, function( response ) {
            $( '#overlay-loader' ).hide();
            if( response && ( response.status == 'success' ) && next ) {
                nextStepAction();
            }
        }, 'json' );
    }

    process_cart_status();

    return;
}

function add_to_cart( e ) {

    e.preventDefault();

    var quantity = $( this ).closest( '.bestel-directly' ).find( '[name="quantity"]' ).val(),
        data = {
            action: 'get_product_info',
            id: $( this ).data( 'id' ),
        };

    console.log('add_to_cart' + data.id);
    console.log($('#step-12').css('display'));

    if( data.id ) {
        $( '#overlay-loader' ).show();
        $.post( urls.ajax, data, function( response ) {
            $( '#overlay-loader' ).hide();
            if( response.status == 'error' ) {
                popup_alert( 'Kon het product niet selecteren.' );
            } else {

                interval_selection_view( response.id );

                $( '.wizard_steps' ).data( 'id', response.id );
                $( '.wizard_steps' ).data( 'quantity', quantity ? quantity : 1 );
                $( '.wizard_steps' ).data( 'is_serviceable', response.is_serviceable );

                if( response.img ) {
                    $('#step-12 .selection img').attr( 'src', response.img );
                }
                $('#step-12 .selection h3').html( 'Jouw selectie <span>' +  response.title + '</span>' );
                $('#step-12 .remove-from-cart').show();
                $('#step-12 .selection' ).data( 'recurring_product_name', response.recurring_product_name );
                $('#step-12 .selection' ).data( 'recurring_price', response.recurring_price );

                if( response.recurring_product_name ) {
                    $( '.step-1 .product-name' ).html( response.recurring_product_name );
                }

                // process_cart_status( function() { $( '.wizard_steps' ).data( 'id', data.id ); } );
                toStart();
                serviceableAction();
                show_cart( true );

            }
        }, 'json' );
    }

}

function new_add_to_cart(e){
	e.preventDefault();
	$( '#overlay-loader' ).show();
	var purchase_type = $("input[name='type-aankoop']:checked").val();
	console.log(purchase_type);

	var id = $( this ).data( 'id' );
	var quantity = $( this ).closest( '.bestel-directly' ).find( '[name="quantity"]' ).val();
	
	if(purchase_type == "nalever"){		
		$.post( urls.ajax, { 'action': 'select_product', 'id': id, quantity: quantity, purchase_type: purchase_type }, function( response ) {
            //$( '#overlay-loader' ).hide();
            //if( response && ( response.status == 'success' ) && next ) {
            //    nextStepAction();
            //}
            console.log(response);
            $( '#overlay-loader' ).hide();
            window.location.href="https://www.shaversclub.nl/staging-2/winkelwagen";
        }, 'json' );
	} else {
		//alert("nalever action");
		$.post( urls.ajax, { action: 'ss_add_to_cart', id: id , quantity: quantity, purchase_type: purchase_type }, function( response ) {
            $( '#overlay-loader' ).hide();
            if( response && ( response.status == 'success' )) {
                //nextStepAction();

                $( '#overlay-loader' ).hide();
                process_dropdown_cart()
			    $("#dropdown-cart").fadeIn(750);
            }
        }, 'json' );

	}
}

function interval_selection_view( id ) {
    var beard_man_selector = ( $( window ).width() < 400 ) ? '#step-12 .shaver-main figure, #step-12 .shaver-main img' : '#this-will-never-be-an-id';
    if( [ 229, 233, 19680, 19681 ].indexOf( id ) < 0 ) {
        $( '.cartridges-text, #step-12 .meest' ).hide();
        $( beard_man_selector ).css( { height: 49 } );
    } else {
        $( '.cartridges-text, #step-12 .meest' ).show();
        $( beard_man_selector ).css( { height: 69 } );
    }
}

function remove_from_cart_action_dropdown(id){
	 console.log('remove_from_cart_action' + id);
    //console.log($('#step-12').css('display'));
/*
    if( ! id ) {
        popup_alert( 'Selecteer eerst een product' );
        return false;
    }
*/

    $( '#overlay-loader' ).show();
    $.post( urls.ajax, { action: 'ss_remove_from_cart', id: id }, function( response ) {
        if( response && response.status == 'success' ) {
			process_dropdown_cart();   
        }
        $( '#overlay-loader' ).hide();
    }, 'json' );


    return;
}

function remove_from_cart_action( id ) {
    console.log('remove_from_cart_action' + id);
    console.log($('#step-12').css('display'));
    if( ! id ) {
        popup_alert( 'Selecteer eerst een product' );
        return false;
    }

    $( '#overlay-loader' ).show();
    $.post( urls.ajax, { action: 'ss_remove_from_cart', id: id }, function( response ) {
        if( response && response.status == 'success' ) {
            process_cart_status( function() {
                if( $('.loose-items-in').length == 0 ) {
                    emptyCartAction();
                }
            } );
        }
        $( '#overlay-loader' ).hide();
    }, 'json' );


    return;
}

function remove_from_cart( e ) {
    e.preventDefault();
    console.log('remove_from_cart');
    console.log($('#step-12').css('display'));

    remove_from_cart_action( $( '.wizard_steps' ).data( 'id' ) ); // ( 'sub' );

    // hide_cart();
    // $('#step-12 .selection img').attr( 'src', '' );
    // $('#step-12 .selection h3').html( '' );
    // $('#step-12 .remove-from-cart').hide();
    // $( '.wizard_steps' ).data( 'id', '' );

}

function uqe_event( e ) {
    $( 'body' ).off( 'update-quantities-event', uqe_event );
    clearTimeout( updateQuantitiesTimeout );
    updateQuantitiesTimeout = null;
    place_order();
}

function place_order( e ) {

    if( $( '.terms-conditions.accepted' ).length == 0 ) {
        popup_alert( 'De algemene voorwaarden moeten worden geaccepteerd om door te gaan.' );
        return;
    }

    if( updateQuantitiesTimeout ) {
        $( 'body' ).on( 'update-quantities-event', uqe_event );
        return;
    }

    var wrapper = $( '.almost-finished' ),
        form = wrapper.find( 'form' ),
        data = {
            action: 'place_order',
            first_name: form.find( '[name="first_name"]' ).val(),
            last_name: form.find( '[name="last_name"]' ).val(),
            billing_postcode: form.find( '[name="postcode"]' ).val(),
            billing_house_number: form.find( '[name="house_number"]' ).val(),
            billing_house_number_suffix: form.find( '[name="house_number_suffix"]' ).val(),
            billing_extra_line: form.find( '[name="extra_line"]' ).val(),
            billing_street_name: form.find( '[name="street_name"]' ).val(),
            billing_city: form.find( '[name="city"]' ).val(),
            coupon: $( '.coupon [name="coupon"]' ).val(),
            payment: $( '.pay .pay-in.selected' ).data( 'payment' ),
            shipping: $( '.shipping .pay-in.selected' ).data( 'shipping' ),
            // skip_initial: topdeal,
        };

        $( '#overlay-loader' ).show();
        $.post( urls.ajax, data, function( response ) {
            if( response.status == 'success' && response.url ) {

                if( window.location.assign ) {
                    window.location.assign( response.url );
                } else {
                    window.location.href = response.url;
                }

                // $('#mollie_payment_link').attr( 'href', response.url );
                // setTimeout( function() { document.getElementById('mollie_payment_link').click(); }, 2000 );

            } else {
                $( '#overlay-loader' ).hide();
                popup_alert( response.message );
            }

        }, 'json' );

}

function goToStep( stepNo, back ) {
    console.log('goToStep');
    console.log($('#step-12').css('display'));
    $( '.wizard_step.active' ).removeClass( 'active' );
    console.log($('#step-12').css('display'));
    var ws = $( '.wizard_step' ).eq( stepNo );
    if( ws.hasClass( 'logged_in' ) ) {
        if( back ) {
            prevStepAction();
        } else {
            nextStepAction();
        }
    } else {
        ws.addClass( 'active' );
    }
    console.log($('#step-12').css('display'));
}

var step_current = 0;
var step_count = 0;
var logged_in = false;

function nextStepAction() {

    if( step_current == 0 ) {
        $( '.para-none' ).show();
        $( '.shaver-bar' ).addClass( 'step2-active' );
        $( '.shaver-end ul li' ).eq( 1 ).addClass( 'active' );
    }

    if( step_current == 1 ) {
        $( '#step-12' ).fadeOut();
    }

    $('.wizard_step').removeClass( 'active' );
    step_current = step_current + 1;
    goToStep( step_current );
}

function toStart() {
    console.log('toStart');
    console.log($('#step-12').css('display'));
    $( '.para-none' ).hide();
    $( '.shaver-bar' ).removeClass( 'step2-active' )
    $( '.shaver-end ul li' ).eq( 1 ).removeClass( 'active' );
    $( '#step-12' ).show();
    step_current = 0;
    console.log($('#step-12').css('display'));
    goToStep( step_current );
    console.log($('#step-12').css('display'));
}
function emptyCartAction() {
    // hide_cart();
    console.log('emptyCartAction');
    console.log($('#step-12').css('display'));
    var ws = $( '.wizard_step' ).eq( 0 );
    ws.data( 'id', '' );
    ws.data( 'quantity', 1 );
    ws.data( 'is_serviceable', '' );

    $('#step-12 .selection img').attr( 'src', '' );
    $('#step-12 .selection h3').html( '' );
    $('#step-12 .remove-from-cart').hide();
    $('#step-12 .shaver-end').hide();

    ws.find( 'ul' ).hide();
    ws.find( 'h3' ).html( 'De winkelwagen is leeg' );
    ws.find( '> p' ).html( '' );
    ws.find( '.order-once, .order-recurring, .shaver-end' ).hide();

    toStart();
}

function prevStepAction() {

    if( step_current == 1 ) {
        $( '.para-none' ).hide();
        $( '.shaver-bar' ).removeClass( 'step2-active' )
        $( '.shaver-end ul li' ).eq( 1 ).removeClass( 'active' );
    }

    if( step_current == 2 ) {
        $( '#step-12' ).show();
    }

    if( step_current > 0 ) {
        $( '.wizard_step' ).removeClass( 'active' );
        step_current --;
        goToStep( step_current, true  );
    }
}

function to_quantity( element ) {
    var a = $( element ),
        wrap = a.closest( '.product-cart-wrap' ),
        pid = wrap.data( 'id' );

    a.hide();
    a.before( '<div class="handle-counter align-self-center"><button class="counter-minus btn btn-primary">-</button><input type="text" name="quantity" id="quantity-' + pid + '" value="1"><button class="counter-plus btn btn-primary">+</button></div>' );

    wrap.find( '.handle-counter' ).handleCounter( {
        writable: true,
        minimum: 0,
        maximize: 100
    } );
}
var updateQuantitiesTimeout = null;

function calculateTotal(e) {
    console.log('calculateTotal' + typeof(e));
    //console.log($('#step-12').css('display'));
    clearTimeout( updateQuantitiesTimeout );
    updateQuantitiesTimeout = null;

    var total = $( '.shipping .pay-in.selected' ).data( 'shipping' ) == 'express' ? 3.5 : 0,
        quantities = {},
        discount = parseFloat( $( '.step-4 .discount.visible .price' ).html() ),
        preventQuantitiesUpdate = ( typeof( e ) == 'object' ) && e.preventQuantitiesUpdate;

    $( '.subscription-summary-wrap .loose-items-in, .product-summary-wrap .loose-items-in' ).each( function( k, v ) {
        var item = $( v ),
            id = item.data( 'id' ),
            price = item.data( 'price' ),//.replace( /[^0-9,.]/g, '' ),
            quantity = item.find( '.handle-counter input' ).val(),
            subtotal = price * quantity;

        if( id == 'sub' ) {
            $( '.wizard_steps' ).data( 'quantity', quantity );
        }

        if( quantity > 0 ) {

            quantities[ id ] = quantity;
            total += subtotal;

        } else {
            remove_from_cart_action( id );
        }

    } );

    if( ! isNaN( discount ) && discount ) {
        total -= discount;
    }

    $( '.step-4 .total .price' ).html( total );


    if( ! preventQuantitiesUpdate && ! $.isEmptyObject( quantities ) ) {
        updateQuantitiesTimeout = setTimeout( function() {
            $( '#overlay-loader' ).show();
            $.post( urls.ajax, { action: 'ss_update_quantities', quantities: quantities }, function( response ) {
                clearTimeout( updateQuantitiesTimeout );
                updateQuantitiesTimeout = null;
                $( '#overlay-loader' ).hide();
                $( 'body' ).trigger( 'update-quantities-event' );
            } );
        }, 2000 );
    }

}

function calculateDropTotal(e) {
    console.log('calculateTotal' + typeof(e));
    //console.log($('#step-12').css('display'));
    clearTimeout( updateQuantitiesTimeout );
    updateQuantitiesTimeout = null;
	
	var i = 0;
	
    var total = $( '.shipping .pay-in.selected' ).data( 'shipping' ) == 'express' ? 3.5 : 0,
        quantities = {},
        discount = parseFloat( $( '.step-4 .discount.visible .price' ).html() ),
        preventQuantitiesUpdate = ( typeof( e ) == 'object' ) && e.preventQuantitiesUpdate;
		
    $( '.dropdown-products .drop-prod' ).each( function( k, v ) {
	    
        var item = $( v ),
            id = item.data( 'id' ),
            price = item.data( 'price' ),//.replace( /[^0-9,.]/g, '' ),
            quantity = item.find( '.handle-counter input' ).val(),
            subtotal = price * quantity;
		
		console.log(price);
        if( id == 'sub' ) {
            $( '.wizard_steps' ).data( 'quantity', quantity );
        }

        if( quantity > 0 ) {

            quantities[ id ] = quantity;
            total += subtotal;

        } else {
            remove_from_cart_action( id );
        }
        
        i = parseInt(i) + parseInt(quantity);

    } );

    if( ! isNaN( discount ) && discount ) {
        total -= discount;
    }
	
	if(i == 0){
		$( '#drop-aantal' ).html("Leeg");
	} else if( i == 1 ){
		$( '#drop-aantal' ).html(i + " artikel");
	} else if( i > 1 ){
		$( '#drop-aantal' ).html(i + " artikelen");
	}
	
    $( '#dropdown-price' ).html( total );


    if( ! preventQuantitiesUpdate && ! $.isEmptyObject( quantities ) ) {
        updateQuantitiesTimeout = setTimeout( function() {
            $( '#overlay-loader' ).show();
            $.post( urls.ajax, { action: 'ss_update_quantities', quantities: quantities }, function( response ) {
                clearTimeout( updateQuantitiesTimeout );
                updateQuantitiesTimeout = null;
                $( '#overlay-loader' ).hide();
                $( 'body' ).trigger( 'update-quantities-event' );
            } );
        }, 2000 );
    }

}

function process_cart_status( callback ) {
    console.log('process_cart_status' + typeof(callback));
    console.log($('#step-12').css('display'));
    $( '#overlay-loader' ).show();
    $.post( urls.ajax, { action: 'ss_get_cart_status' }, function( response ) {
        console.log('$.post-' + $('#step-12').css('display'));
        $( '.subscription-summary-wrap, .product-summary-wrap' ).hide();
        $( '.subscription-summary-wrap .loose-items-in, .product-summary-wrap .loose-items-in' ).remove();

        var i, o, item_html, mobile_selection = false;


        if( response && response.subscriptions ) {

            for( i = 0; i < response.subscriptions.length; i++ ) {
                o = response.subscriptions[i];

                item_html = ''
+ '            <div class="loose-items-in" data-price="' + o.initial_price + '">';
                if( o.initial_title ) {

                    if( ! mobile_selection ) {
                        $('#step-12 .selection img').attr( 'src', o.initial_img );
                        $('#step-12 .selection h3').html( 'Jouw selectie <span>' +  o.initial_title + '</span>' );
                        $('#step-12 .remove-from-cart').show();
                        // $( '.wizard_steps' ).data( 'id', o.id );
                        $( '.wizard_steps' ).data( 'id','sub' );
                        interval_selection_view( o.id );
                        $( '.wizard_steps' ).data( 'is_serviceable', true );
                        $( '.step-1 .product-name' ).html( o.recurring_product_name );
                        $( '#step-12 .selection' ).data( 'recurring_product_name', o.recurring_product_name );
                        mobile_selection = true;
                    }

                    item_html += ''
+ '                <div class="loose-items-top d-flex flex-wrap">'
+ '                    <div class="loose-items-left col-sm-3">'
+ '                        <figure><img class="m-auto" src="' + o.initial_img + '"></figure>'
+ '                    </div>'
+ '                    <div class="loose-items-right col-sm-9">'
+ '                        <span>' + o.initial_product_line + '</span>'
+ '                        <h6>' + o.initial_title + '</h6>'
+ '                        <div class="d-flex flex-wrap justify-content-between">'
+ '                            <small class="align-self-center">Eerste levering: €' + o.initial_price + '</small>'
+ '                        </div>'
+ '                    </div>'
+ '                    <div class="loose-items-label">Levering hierna:</div>'
+ '                </div>';
                }

                if( ! mobile_selection ) {
                    $('#step-12 .selection img').attr( 'src', o.img );
                    $('#step-12 .selection h3').html( 'Jouw selectie <span>' +  o.title + '</span>' );
                    $('#step-12 .remove-from-cart').show();
                    // $( '.wizard_steps' ).data( 'id', o.id );
                    $( '.wizard_steps' ).data( 'id','sub' );
                    interval_selection_view( o.id );
                    $( '.wizard_steps' ).data( 'is_serviceable', true );
                    $( '.step-1 .product-name' ).html( o.recurring_product_name );
                    $( '#step-12 .selection' ).data( 'recurring_product_name', o.recurring_product_name );
                    mobile_selection = true;
                }

                item_html += ''
+ '                <div class="loose-items-top d-flex flex-wrap">'
+ '                    <div class="loose-items-left col-sm-3">'
+ '                        <figure><img class="m-auto" src="' + o.img + '"></figure>'
+ '                    </div>'
+ '                    <div class="loose-items-right col-sm-9">'
+ '                        <span>' + o.product_line + '</span>'
+ '                        <h6>' + o.title + '</h6>'
+ '                        <div class="d-flex flex-wrap justify-content-between">'
+ '                            <small class="price align-self-center">€ ' + ( o.price / o.quantity ) + '<br><em>Gratis thuisbezorgd</em></small>'
+ '                            <div class="handle-counter align-self-center">'
+ '                                <button class="counter-minus btn btn-primary">-</button>'
+ '                                <input type="text" value="' + o.quantity + '">'
+ '                                <button class="counter-plus btn btn-primary">+</button>'
+ '                            </div>'
+ '                        </div>'
+ '                    </div>'
+ '                </div>'
+ '                <div class="loose-items-bottom d-flex flex-wrap justify-content-between">'
+ '                    <div class="first-delivery">'
+ '                        <ul class="row">'
+ '                            <li>'
+ '                                <span>Eerste levering</span>'
+ '                                <span class="date"><i class="far fa-calendar-alt"></i> ' + o.shipping_date + '</span>'
+ '                            </li>'
+ '                            <li>'
+ '                                <span>Frequentie</span>'
+ '                                <span class="date af-none" data-interval="' + o.interval + '">' + o.interval_label + '</a>'
+ '                            </li>'
+ '                '
+ '                        </ul>'
+ '                    </div>'
+ '                    <div class="write-pencil"><i class="far fa-pen-square"></i></div>'
+ '                </div>'
+ '            </div>'
                ;

                $( '.subscription-summary-wrap' ).append( item_html );
                $( '.subscription-summary-wrap .loose-items-in' ).data( 'id', 'sub' );

            }

            $( '.subscription-summary-wrap' ).show();
        }

        if( response && response.products ) {

            for( id in response.products ) {
                o = response.products[ id ];

                if( ! mobile_selection ) {
                    $('#step-12 .selection img').attr( 'src', o.img );
                    $('#step-12 .selection h3').html( 'Jouw selectie <span>' +  o.title + '</span>' );
                    $('#step-12 .remove-from-cart').show();
                    $( '.wizard_steps' ).data( 'id', id );
                    mobile_selection = true;
                }

                item_html = ''
+ '                <div class="loose-items-in" id="loose-items-' + id + '" data-price="' + o.price + '">'
+ '                    <div class="loose-items-top d-flex flex-wrap">'
+ '                        <div class="loose-items-left col-sm-3 p-0 align-self-center">'
+ '                            <figure><img class="m-auto" src="' + o.img + '"></figure>'
+ '                        </div>'
+ '                        <div class="loose-items-right col-sm-9">'
+ '                            <span>' + o.product_line + '</span>'
+ '                            <h6>' + o.title + '</h6>'
+ '                            <div class="d-flex flex-wrap justify-content-between">'
+ '                                <small class="price align-self-center">€ ' + o.price + '</small>'
+ '                                <div class="handle-counter align-self-center">'
+ '                                    <button class="counter-minus btn btn-primary">-</button>'
+ '                                    <input type="text" value="' + o.quantity + '">'
+ '                                    <button class="counter-plus btn btn-primary">+</button>'
+ '                                </div>'
+ '                            </div>'
+ '                        </div>'
+ '                    </div>'
+ '                </div>'
                ;

                $( '.product-summary-wrap' ).append( item_html );
                $( '#loose-items-' + id ).data( 'id', id );

            }

            $( '.product-summary-wrap' ).show();
        }

        $( '.article-count' ).html( $( '.subscription-summary-wrap .loose-items-in, .product-summary-wrap .loose-items-in' ).length );

        $( '.subscription-summary-wrap .handle-counter, .product-summary-wrap .handle-counter' ).each( function( k, v ) {
            $( v ).handleCounter( {
                writable: true,
                onChange: calculateTotal,
                minimum: 0,
            } );
        } );

        $( '.subscription-summary-wrap .write-pencil i' ).on( 'click', function( e ) {
            toStart();
            serviceableAction( true );
        } );
/*
        function( e ) {

            var i = $( this ),
                wrapper = i.closest( '.loose-items-in' ),
                id = wrapper.data( 'id' ),
                interval = wrapper.find( '.date' ).data( 'interval' ),
                popup = $( '.sub_edit_popup' ),
                dummy;

            popup.find( 'h4' ).html( wrapper.find( 'h6' ).html() );
            popup.find( '[name="interval"]' ).val( interval );

            popup.lightbox_me( { } );

        }
*/
        console.log($('#step-12').css('display'));
        calculateTotal();
        if( callback ) {
            callback();
        }
        $( '#overlay-loader' ).hide();

    }, 'json' );
}

function popup_alert( message, title ) {
    var popup = $( '.alert_popup' ).clone().addClass( 'popup_alert_clone' );
    $( 'body' ).append( popup );
    if( ! title ) {
        title = 'Melding';
    }
    popup.find( 'h4' ).html( title );
    popup.find( 'p' ).html( message );
    popup.find( '.pop_close' ).on( 'click', function( e ) { $( this ).closest( '.alert_popup' ).trigger( 'close' ); } );

    popup.lightbox_me( { destroyOnClose: true } );
}


function popup_confirm( message, title, callback ) {
    var popup = $( '.confirm_popup' ).clone().addClass( 'popup_confirm_clone' );
    $( 'body' ).append( popup );
    if( ! title ) {
        title = 'Bevestigen';
    }
    popup.find( 'h4' ).html( title );
    popup.find( 'p' ).html( message );
    popup.find( '.pop_close, .btn' ).on( 'click', function( e ) { $( this ).closest( '.confirm_popup' ).trigger( 'close' ); } );
    popup.find( '.confirm' ).on( 'click', callback );
    popup.lightbox_me( { destroyOnClose: true } );
}

$( document ).ready( function() {

    step_current = 0;
    step_count = $( '.wizard_step' ).length

    logged_in = $( '#step1 .step-2.logged_in' ).length == 1;

    if( ! $.cookieEnabled() ) {
        popup_alert( 'Cookies worden niet ondersteund in je browser, zonder cookies werkt de checkout niet.' );
        step_check[1] = step_check[2] = step_check[3] = step_check[4] = step_check[5] = function() {
            popup_alert( 'Cookies worden niet ondersteund in je browser, zonder cookies werkt de checkout niet.' );
            return false;
        };
    }

    process_cart_status( function () {
        serviceableAction( true );
    } );

    $( '.almost-finished-right .pay-in' ).on( 'click', function( e ) {
        $( this ).siblings().removeClass( 'selected' );
        $( this ).addClass( 'selected' );
    } );


    $( '.shipping .pay-in' ).on( 'click', calculateTotal );


    $( '.step-2 .login-in-cart, .step-2 .register-in-cart' ).on( 'click', function( e ) {
        e.preventDefault();
        $( this ).closest( '.step-2' ).find( '.wizard_next' ).click();
    } )

    $( '#step1 .wizard_next' ).click( function( e ) {

        if( step_current == 0 ) {
            add_to_cart_action( true, $( this ).hasClass( 'order-once' ) );
        } else if( step_current == 1 ) {

            var form = $( '.step-2 form:visible' ),
                data = {
                    action: form.data( 'action' ),
                    email: form.find( '[name="email"]' ).val(),
                    password: form.find( '[name="password"]' ).val(),
                    security: form.find( '[name="security"]' ).val(),
                    referrer: form.find( '[name="_wp_http_referer"]' ).val(),
                };

            if( data.action == 'ss_register' ) {
                if( data.password != form.find( '[name="cpassword"]' ).val() ) {
                    popup_alert( 'Twee verschillende wachtwoorden opgegeven' );
                    return false;
                }

                data.first_name = form.find( '[name="fname"]' ).val();
                data.last_name = form.find( '[name="lname"]' ).val();
            }
            $( '#overlay-loader' ).show();
            $.post( urls.ajax, data, function( response ) {
                $( '#overlay-loader' ).hide();
                if( response.status == 'success' ) {
	                //ToDo: Associate session
	                associate_session(data.email);
                    nextStepAction();
                    $( '#step1 .step-2' ).addClass( 'logged_in' );
                    logged_in = true;

                    if( response.user ) {
                        var cm = $( '.almost-finished .contact-main' ),
                            k, i, ks = [ 'postcode', 'house_number', 'house_number_suffix', 'street_name', 'extra_line', 'city', 'country' ],
                            u = response.user;

                            if( u.is_recurring ) {
                                $( '.is-not-recurring' ).removeClass( 'is-not-recurring' ).addClass( 'is-recurring' );
                            } else {
                                $( '.is-recurring' ).removeClass( 'is-recurring' ).addClass( 'is-not-recurring' );
                            }

                            cm.find( '[name="first_name"]' ).val( u.first_name );
                            cm.find( '[name="last_name"]' ).val( u.last_name );

                            for( k in ks ) {
                                i = 'billing_' + ks[ k ];
                                if( u[ i ] ) {
                                    cm.find( '[name="' + ks[ k ] + '"]' ).val( u[ i ] );
                                }
                            }
                    }

                    $('.nav-link.login').html( 'MIJN ACCOUNT' ).attr( 'href', response.url );

                } else if( response.message ) {
                    popup_alert( response.message );
                } else if( response.error_messages && ! $.isEmptyObject( response.error_messages ) ) {
                    var i, m = '';
                    for( i in response.error_messages ) {
                        m += response.error_messages[ i ] + "<br>\n";
                    }
                    popup_alert( m );
                } else {
                    popup_alert( 'Inloggen is mislukt.' );
                }

            }, 'json' );
        } else if( step_current == 2 ) {
            var data = {
                    action: 'ss_add_to_cart',
                    products: {},
                };

            $( '.product-cart-wrap' ).each( function( k, v ) {

                var wrap = $( v ),
                    id = wrap.data( 'id' ),
                    quantity_el = wrap.find( '[name="quantity"]' ),
                    quantity = 0


                if( quantity_el.length == 1 ) {
                    quantity = parseInt( quantity_el.val() );
                }

                if( isNaN( quantity ) || ( ! typeof( quantity ) == 'number' ) || ( quantity <= 0 ) ) {
                    return;
                }

                data.products[ id ] = quantity;

            } );

            if( $.isEmptyObject( data.products ) ) {
                nextStepAction();
                return;
            }

            $( '#overlay-loader' ).show();
            $.post( urls.ajax, data, function( response ) {
                process_cart_status();
                $( '#overlay-loader' ).hide();
                if( response && ( response.status == 'success' ) ) {
                     nextStepAction();
                } else {
                    popup_alert( 'Kon 1 of meer producten niet aan het winkelwagentje toevoegen.' );
                }
            }, 'json' );

        } else if( step_current < ( step_count - 1 ) ) {
            nextStepAction();
        }

    } );

    $( '#step1 .wizard_prev' ).click( function( e ) {

        if( step_current > 0 ) {
            prevStepAction();
        }

    } );

    $( '.to-quantity' ).on( 'click', function( e ) {
        to_quantity( this );
    } );

    $( 'input.handleCounter' ).each( function( k, v ) {
        $( v ).before( '<div class="handle-counter align-self-center"><button class="counter-minus btn btn-primary">-</button><input type="text" name="' + v.name + '" id="' + v.id + '" class="' + v.className + '" value="' + v.value + '"><button class="counter-plus btn btn-primary">+</button></div>' );
        $( v ).remove();
    } );

    $( '.handle-counter' ).handleCounter( {
        writable: true,
        minimum: 1,
        maximize: 100
    } );

    $( '.ss_ref + button' ).on( 'click', function( e ) {
        jQuery( '.ss_ref' ).selectText();
        if( document.execCommand('copy') ) {
            popup_alert( 'Link gekopieerd' );
        } else {
            popup_alert( 'Kopieren mislukt' );
        }

    } );


    $( '.sub_edit_popup .shaver-main' ).on( 'click', function( e ) {
        var card = $( this ),
            interval = card.data( 'interval' );

        $( '#step-12 .shaver-main[data-interval="' + interval + '"]').trigger( 'click' );
        card.closest( '.sub_edit_popup' ).find( '.pop_close' ).click();
    } );


    $( '.add-to-cart' ).on( 'click', add_to_cart );
    //$( '.new-add-to-cart' ).on( 'click', new_add_to_cart );
    
    // $( '.shop' ).on( 'click', function( e ) {
    //     $( '.cart-hide' ).length ? hide_cart() : show_cart();
    // } );

    $( '.change-selection' ).on( 'click', hide_cart );
    $( '.remove-from-cart' ).on( 'click', remove_from_cart );

    $( '#step-12 .shaver-main' ).on( 'click', select_interval );

    $( '.place-order' ).on( 'click', place_order );

    $( '.check-coupon' ).on( 'click', function( e ) {

        $( '#overlay-loader' ).show();
        var input = $( this ).closest( '.coupon' ).find( '[name="coupon"]' );

        $.post( urls.ajax, { action: 'ss_check_coupon', coupon: input.val() }, function( response ) {

            $( '#overlay-loader' ).hide();
            var p = input.parent(),
                discount = $( '.step-4 .discount' );

            p.removeClass( 'success' ).removeClass( 'error' );
            p.find('label').remove();

            discount.removeClass( 'visible' );
            discount.find( '.price' ).html('');

            if( response.status == 'success' ) {

                p.addClass( 'success' );
                p.append( $( '<label />' ).html( response.message ) );

                discount.find( '.price' ).html( response.discount );
                discount.addClass( 'visible' );
                calculateTotal( { preventQuantitiesUpdate: true } );

            } else {
                p.addClass( 'error' );
                p.append( $( '<label />' ).html( response.message ? response.message : 'Er is iets misgegaan.' ) );
            }

        }, 'json' );
    } );

    // $( '.order-once' ).on( 'click', order_once );
    // $( '.order-recurring' ).on( 'click', order_recurring );

    $( '.open-register' ).on( 'click', function( e ) {
        $( '.account-login-wrap' ).animate( { opacity: 0 }, 400, 'swing', function() {
            $( '.account-login-wrap' ).hide();
            $( '.account-register-wrap' ).show();
            $( '.account-register-wrap' ).animate( { opacity: 1 } );
        } );
    } );

    $( '.open-login' ).on( 'click', function( e ) {
        $( '.account-register-wrap' ).animate( { opacity: 0 }, 400, 'swing', function() {
            $( '.account-register-wrap' ).hide();
            $( '.account-login-wrap' ).show();
            $( '.account-login-wrap' ).animate( { opacity: 1 } );
        } );
    } );


    $( '.login' ).on( 'click', function( e ) {
        if( ! logged_in ) {
            e.preventDefault();
            $( '.login_popup:visible' ).trigger('close');
            $( '#login2' ).lightbox_me( { centered: true } );
        }
    } );

    $( '.register' ).on( 'click', function( e ) {
        e.preventDefault();
        $( '.login_popup:visible' ).trigger('close');
        $( '#login1' ).lightbox_me( { centered: true } );
    } );


    $( '.forgot' ).on( 'click', function( e ) {
        e.preventDefault();
        $( '.login_popup:visible' ).trigger('close');
        $( '#login3' ).lightbox_me( { centered: true } );
    } );


    $( '.pop_close' ).on( 'click', function( e ) {
        $( '.login_popup' ).trigger('close');
    } );

    $( '.login_popup form' ).on( 'submit', function( e ) {
        e.preventDefault();
        $( this ).closest( '.login_popup' ).find( '.btn' ).click();
    } );

    $( '.terms-conditions a' ).on( 'click', function( e ) {
        var tar = '.' + $(this).attr('id');
        $( tar ).lightbox_me( {
            centered: true,
            closeSelector: '.term-close'
        } );
    } );

    $( '.terms-conditions i' ).on( 'click', function( e ) {
        var i = $( this ),
            t = i.closest( '.terms-conditions' );

        if( t.hasClass( 'accepted' ) ) {
            t.removeClass( 'accepted' );
            i.attr( 'class', 'fal fa-circle' );
        } else {
            t.addClass( 'accepted' );
            i.attr( 'class', 'fal fa-check-circle' );
        }
    } );

    $( '.next-step.term-close' ).on( 'click', function( e ) {
        var t = $( '.terms-conditions' ), i = t.find( 'i' );
        t.addClass( 'accepted' );
        i.attr( 'class', 'fal fa-check-circle' );
    } );
    $( '.go-back.term-close' ).on( 'click', function( e ) {
        var t = $( '.terms-conditions' ), i = t.find( 'i' );
        t.removeClass( 'accepted' );
        i.attr( 'class', 'fal fa-circle' );
    } );


    $( '.vertical_scroll' ).mCustomScrollbar( { axis: 'y' } );

    $( '#login3 .btn' ).on( 'click', function( e ) {
        var form = $( '#login3 form' ),
            data = {
                action: 'ss_forgot',
                email: form.find( '[name="email"]' ).val(),
                security: form.find( '[name="security"]' ).val(),
            };

        $( '#overlay-loader' ).show();
        $.post( urls.ajax, data, function( response ) {
            $( '#overlay-loader' ).hide();
            if( response.status == 'success' ) {
                if( response.message ) {
                    popup_alert( response.message );
                } else {
                    popup_alert( 'Er is een email naar je toegestuurd met instructies om je wachtwoord te herstellen.' );
                    form.find( '[name="email"]' ).val('');
                }
            } else {

                if( response.message ) {
                    popup_alert( response.message );
                } else {
                    popup_alert( 'Kon wachtwoord niet resetten.' );
                }
            }

        }, 'json' );
    } );

    $( '#login2 .btn' ).on( 'click', function( e ) {
        var form = $( '#login2 form' ),
            data = {
                action: 'ss_login',
                email: form.find( '[name="email"]' ).val(),
                password: form.find( '[name="password"]' ).val(),
                security: form.find( '[name="security"]' ).val(),
                referrer: form.find( '[name="_wp_http_referer"]' ).val(),
            };
		
		
		
        $( '#overlay-loader' ).show();
        $.post( urls.ajax, data, function( response ) {
            if( response.status == 'success' ) {
	            //ToDo: associate session
	            associate_session(data.email);
                location = location.href = response.url;
            } else if( response.message ) {
                $( '#overlay-loader' ).hide();
                popup_alert( response.message );
            } else if( response.error_messages && ! $.isEmptyObject( response.error_messages ) ) {
                var i, m = '';
                for( i in response.error_messages ) {
                    m += response.error_messages[ i ] + "<br>\n";
                }
                $( '#overlay-loader' ).hide();
                popup_alert( m );
            } else {
                popup_alert( 'Inloggen is mislukt.' );
            }

        }, 'json' );
    } );


    $( '#login1 .btn' ).on( 'click', function( e ) {
        var form = $( '#login1 form' ),
            data = {
                action: 'ss_register',
                email: form.find( '[name="email"]' ).val(),
                password: form.find( '[name="password"]' ).val(),
                security: form.find( '[name="security"]' ).val(),
                referrer: form.find( '[name="_wp_http_referer"]' ).val(),
            };

        if( data.password != form.find( '[name="cpassword"]' ).val() ) {
            popup_alert( 'Twee verschillende wachtwoorden opgegeven' );
            return false;
        }

        $( '#overlay-loader' ).show();
        $.post( urls.ajax, data, function( response ) {
            if( response.status == 'success' ) {
                location = location.href = location.href;
                location.reload();
            } else if( response.message ) {
                $( '#overlay-loader' ).hide();
                popup_alert( response.message );
            } else {
                $( '#overlay-loader' ).hide();
                popup_alert( 'Registreren is mislukt.' );
            }

        }, 'json' );
    } );

     $('.nav-icon').click(function() {
        $('body').toggleClass('open');
        $('.nav-icon').toggleClass('open');
    });

  $( '.product-content' ).on( 'click', 'li.col-6', function( e ) {

        if( e.target.tagName.toUpperCase() == 'A' ) {
            return;
        }

        e.preventDefault();
        var a = $( e.target ).closest( 'li.col-6' ).find( 'a' );
        if( a.length ) {
            a[0].click();
        }
    } );

    $( '.jouw' ).on( 'click', function( e ) {
        if( $( window ).width() < 768 ) {
            var el = $( this ).closest( '.step-1-left' );
            if( el.hasClass( 'open' ) ) {
                el.removeClass( 'open' );
            } else {
                el.addClass( 'open' );
            }
        }
    } );

    jQuery( '.product-content ul, .mesjes-1, .our-products, .meer-dan .soap-in' ).on( 'click', function( e ) {

        if( e.target.tagName.toUpperCase() == 'A' ) {
            return;
        }

        e.preventDefault();
        var a = $( e.target ).closest( $( this ).data( 'items' ) ).find( 'a' );
        if( a.length ) {
            a[0].click();
        }

    } );

    if( window.FB ) {
        FB.init( {
            appId: '453674858488671',
            status: true,
            xfbml: true,
            version: 'v3.1',
        } );
    }

    $( '.twitter-open-share' ).on( 'click', function( e ) {
        e.preventDefault();
        window.open( 'https://twitter.com/intent/tweet?original_referer=' + location.href + '&text=ShaverClub%20-%20The%20New%20Smart%20Way%20of%20Shaving&tw_p=tweetbutton&url=' + encodeURIComponent( urls.site + '?ref=' + $( 'span.ss_ref' ).text().trim() ), 'twitter-dialog', 'titlebar=no,width=550,height=420' );
    } );

    $( '.messenger-open-share' ).on( 'click', function( e ) {
        e.preventDefault();
        var uri = urls.site + '?ref=' + $( 'span.ss_ref' ).text().trim();
        FB.ui( {
            method: 'send',
            link: uri,
        } );

        // window.open( 'fb-messenger://share?link=' + encodeURIComponent( uri ) + '&app_id=' + encodeURIComponent( '453674858488671' ) );
    } );

    $( '.facebook-open-share' ).on( 'click', function( e ) {
        e.preventDefault();
        FB.ui( {
            method: 'share',
            href: urls.site + '?ref=' + $( 'span.ss_ref' ).text().trim(),
        } );

    } );

    $('.rewards').mouseenter(function(e) {

        var srcimg= '.'+$(this).attr('data-src');
	$(srcimg).removeClass('active');
		var srcimghover= '.'+$(this).attr('data-src-hover');
	$(srcimghover).addClass('active');


    });
     $('.rewards').mouseleave(function(e) {

		 var srcimg= '.'+$(this).attr('data-src');
	$(srcimg).addClass('active');
		var srcimghover= '.'+$(this).attr('data-src-hover');
	$(srcimghover).removeClass('active');


    });


   $('.slider').slick({
  dots: false,
  infinite: true,
  autoplay: true,
  autoScroll: true,
  arrows: true,
  speed: 300,
  slidesToShow: 2,
  slidesToScroll: 1,

});
    $('.slider1').slick({
  dots: false,
  infinite: true,
  autoplay: true,
  autoScroll: true,
  arrows: true,
  speed: 300,
  slidesToShow: 3,
  slidesToScroll: 1,

});





});
