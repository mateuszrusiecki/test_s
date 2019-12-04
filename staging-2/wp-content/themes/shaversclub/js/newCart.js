var cartProducts = [];
var cartSubscriptions = [];
var order = [];
var apiUrl = 'http://localhost:8073/api/cart/';
//var apiUrl = 'https://shop.shaversclub.nl/api/cart/';

jQuery(".shop-new").on("click", function(){
  jQuery("#dropdown-cart").fadeToggle(750, function() {
    if($(this).is(":visible")) {
      getCart();
    }
  });
});

$('#productpage-splash').on('click', '.new-add-to-cart', function(e) {
  e.preventDefault();

  var id = $( this ).data( 'id' );

  var otherRecurringProductId = $("input[name='op-id']").val();
  var purchase_type = $("input[name='type-aankoop']:checked").val();
  var subscription = 0;
  if(purchase_type === 'nalever') {
    subscription = 1;
  }

  if(purchase_type === 'nalever' && otherRecurringProductId > 0) {
    id = otherRecurringProductId;
  }

  //$( '#overlay-loader' ).show();
  var quantity = $( this ).closest( '.bestel-directly' ).find( '[name="quantity"]' ).val();

  // console.log(purchase_type);
  // console.log(id);
  // console.log(quantity);

  var userToken = getUserToken();


    $.post( apiUrl, { id: id , quantity: quantity, wp_user_token: userToken, subscription: subscription}, function( response ) {
    //   $( '#overlay-loader' ).hide();
      console.log(response);
       if( response && ( response.status === 'success' )) {
         getCartFromAPI();
    //     $( '#overlay-loader' ).hide();
         $("#dropdown-cart").fadeIn(750);
       }
     }, 'json' );


});

function getCart() {
  if(cartProducts.length > 0) {
    console.log('render from cache');
    renderCart(cartProducts, cartSubscriptions);
    $('#dropdown-price').text(order.total / 100);

    return;
  }

  getCartFromAPI();
}

function getCartFromAPI() {
  console.log('get cart from api');
  var userToken = getUserToken();

  var url = `${apiUrl}${userToken}`;

  $.get(url, function(response) {
    if( response && response.data.products ) {
      cartProducts = response.data.products;
      cartSubscriptions = response.data.subscriptions;
      order = response.data.order;
console.log(response.data);
      renderCart(cartProducts, cartSubscriptions);

      $('#dropdown-price').text(order.total / 100);
    }
  });
}

function renderCart(products, subscriptions) {
  $( '.dropdown-products' ).html("");

  var item_html_new = '';

  if(products.length > 0) {
    item_html_new += `<p id="single-products-label" class="pl-3" style="font-family: 'TheWave-DBd';">Losse artikelen</p>`;
  }

  products.forEach((product, index) => {
    item_html_new += getProductHtml(product, index);
  });

  if(subscriptions.length > 0) {
    item_html_new += `<p id="services-label" class="pl-3" style="font-family: 'TheWave-DBd';">Services</p>`;
  }

  subscriptions.forEach((product, index) => {
    item_html_new += getSubscriptionHtml(product, index);
  });

  $( '.dropdown-products' ).append( item_html_new );
}

function getProductHtml(product, index,) {
  return `
          <div class="drop-prod" data-price="${product.price}" data-id="${index}">
            <div class="row">
              <div class="col-lg-12">
                <div class="row">
                  <div class="col-3">
                     <img src="${product.main_img}" class="img-responsive" />
                  </div>
                  <div class="col-9 align-self-center">
                    <div><p style="font-family:'TheWave-DBd';"><span>${product.name}</span></p></div>
                    <div class="second-row" style="display:flex;">
                      <div class="col-9 p-0" style="display:flex;">
                        <div class="handle-counter align-self-center" style="border: 0px;">
                              <button class="counter-minus btn btn-primary">-</button>
                              <input type="text" id="quantity-${product.id}" class="quantity" value="${product.quantity}">
                              <button class="counter-plus btn btn-primary">+</button>
                        </div>
                        <p style="padding: 8px; font-size: 20px;">
                          <span style="color: #d45a1d;">&euro;</span>
                          <span class="item-price" data-id="${index}" style="font-family:'TheWave-DBd';color: #d45a1d">${product.price * product.quantity / 100}</span>
                        </p>
          						</div>
                      <div class="col-3">
                        <a data-id="${product.id}" data-array-id="${index}" class="btn-remove-drop" style="float: right; cursor: pointer"><i class="fa fa-trash"></i></a>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        `;
}

function getSubscriptionHtml(product, index) {
  return `
          <div class="drop-prod-sub" data-price="${product.price}" data-id="${index}">
            <div class="row">
              <div class="col-lg-12">
                <div class="row">
                  <div class="col-3">
                     <img src="${product.main_img}" class="img-responsive" />
                  </div>
                  <div class="col-9 align-self-center">
                    <div><p style="font-family:'TheWave-DBd';"><span>${product.name}</span></p></div>
                    <div class="second-row" style="display:flex;">
                      <div class="col-9 p-0" style="display:flex;">
                        <div class="handle-counter align-self-center" style="border: 0px;">
                              <button class="counter-sub-minus btn btn-primary">-</button>
                              <input type="text" id="quantity-${product.id}" class="quantity" value="${product.quantity}">
                              <button class="counter-sub-plus btn btn-primary">+</button>
                        </div>
          						</div>
                      <div class="col-3">
                        <a data-id="${product.id}" data-sub-array-id="${index}" class="btn-remove-sub-drop" style="float: right; cursor: pointer"><i class="fa fa-trash"></i></a>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        `;
}

function process_dropdown_cart() {
  console.log('process dropdown cart');
  getCartFromAPI();
}

var dropdownProduct = $('.dropdown-products');

dropdownProduct.on('click', '.counter-minus', function() {
  console.log('counter-minus');

  var id = $(this).closest('.drop-prod').attr("data-id");
  console.log(id);

  if(cartProducts[id].quantity > 1) {
    var userToken = getUserToken();
    var url = `${apiUrl}${userToken}/product/${cartProducts[id].id}/decreaseAmount`;

    $.ajax({
      url: url,
      method: 'PUT',
      crossDomain: true,
      contentType: 'application/json',
      success: function(result) {
        console.log(result);
      },
      error: function(request,msg,error) {
        console.log(result);
      }
    });

    cartProducts[id].quantity -= 1;
    order.total -= cartProducts[id].price;

    $(this).next().val( function(i, oldval) {
      return parseInt( oldval, 10) - 1;
    });
    $(`.item-price[data-id=${id}]`).text(cartProducts[id].price * cartProducts[id].quantity / 100);

    $('#dropdown-price').text(order.total / 100);
  }

});

dropdownProduct.on('click', '.counter-plus', function() {
  console.log('counter-plus');
  //console.log(cartProducts);
  var id = $(this).closest('.drop-prod').attr("data-id");
  console.log(id);

  if(cartProducts[id].quantity > 0) {
    var userToken = getUserToken();
    var url = `${apiUrl}${userToken}/product/${cartProducts[id].id}/increaseAmount`;

    $.ajax({
      url: url,
      method: 'PUT',
      crossDomain: true,
      contentType: 'application/json',
      success: function(result) {
        console.log(result);
      },
      error: function(request,msg,error) {
        console.log(result);
      }
    });

    cartProducts[id].quantity += 1;
    order.total += cartProducts[id].price;

    $(this).prev().val( function(i, oldval) {
      return parseInt( oldval, 10) + 1;
    });
    $(`.item-price[data-id=${id}]`).text(cartProducts[id].price * cartProducts[id].quantity / 100);

    $('#dropdown-price').text(order.total / 100);
  }
});


dropdownProduct.on('click', '.btn-remove-drop', function() {
  var arrayId = $(this).attr("data-array-id");
  var id = $(this).attr("data-id");
  console.log('click delete  ' + id);

  var userToken = getUserToken();
  var url = `${apiUrl}${userToken}/product/${id}`;

  $.ajax({
    url: url,
    method: 'DELETE',
    crossDomain: true,
    contentType: 'application/json',
    success: function(result) {
      console.log(result);
      order.total = result.data.total;
      cartProducts.splice(1, arrayId);

      if(cartProducts.length < 1) {
        $(`#single-products-label`).remove();
      }

      $(`.drop-prod[data-id=${arrayId}]`).remove();
      $('#dropdown-price').text(result.data.total / 100);
    },
    error: function(request,msg,error) {
      console.log(result);
    }
  });

});


dropdownProduct.on('click', '.counter-sub-minus', function() {
  console.log('counter-sub-minus');

  var id = $(this).closest('.drop-prod-sub').attr("data-id");


  if(cartSubscriptions[id].quantity > 1) {
    var userToken = getUserToken();
    var url = `${apiUrl}${userToken}/product/${cartSubscriptions[id].id}/decreaseAmount`;

    $.ajax({
      url: url,
      method: 'PUT',
      crossDomain: true,
      contentType: 'application/json',
      success: function(result) {
        console.log(result);
      },
      error: function(request,msg,error) {
        console.log(result);
      }
    });

    cartSubscriptions[id].quantity -= 1;
    //order.total -= cartSubscriptions[id].price;

    $(this).next().val( function(i, oldval) {
      return parseInt( oldval, 10) - 1;
    });
    //$(`.item-sub-price[data-id=${id}]`).text(cartSubscriptions[id].price * cartSubscriptions[id].quantity / 100);

    //$('#dropdown-price').text(order.total / 100);
  }

});

dropdownProduct.on('click', '.counter-sub-plus', function() {
  console.log('counter-sub-plus');

  var id = $(this).closest('.drop-prod-sub').attr("data-id");


  if(cartSubscriptions[id].quantity > 0) {
    var userToken = getUserToken();
    var url = `${apiUrl}${userToken}/product/${cartSubscriptions[id].id}/increaseAmount`;

    $.ajax({
      url: url,
      method: 'PUT',
      crossDomain: true,
      contentType: 'application/json',
      success: function(result) {
        console.log(result);
      },
      error: function(request,msg,error) {
        console.log(result);
      }
    });

    cartSubscriptions[id].quantity += 1;
    //order.total += cartSubscriptions[id].price;

    $(this).prev().val( function(i, oldval) {
      return parseInt( oldval, 10) + 1;
    });
    //$(`.item-sub-price[data-id=${id}]`).text(cartSubscriptions[id].price * cartSubscriptions[id].quantity / 100);

    //$('#dropdown-price').text(order.total / 100);
  }
});


dropdownProduct.on('click', '.btn-remove-sub-drop', function() {
  var arrayId = $(this).attr("data-sub-array-id");
  var id = $(this).attr("data-id");
  console.log('click delete  ' + id);

  var userToken = getUserToken();
  var url = `${apiUrl}${userToken}/product/${id}`;

  $.ajax({
    url: url,
    method: 'DELETE',
    crossDomain: true,
    contentType: 'application/json',
    success: function(result) {
      console.log(result);
      order.total = result.data.total;
      cartSubscriptions.splice(1, arrayId);

      console.log(cartSubscriptions.length);
      if(cartSubscriptions.length < 1) {
        $(`#services-label`).remove();
      }

      $(`.drop-prod-sub[data-id=${arrayId}]`).remove();
    },
    error: function(request,msg,error) {
      console.log(result);
    }
  });

});

function getCookie(name) {
  var value = "; " + document.cookie;
  var parts = value.split("; " + name + "=");
  if (parts.length == 2) return parts.pop().split(";").shift();
}

function getUserToken() {
  var userToken = getCookie('wp_user_token');

  if(typeof userToken === 'undefined') {
    userToken = uuidv4();
    setCookie('wp_user_token', userToken);
  }

  return userToken;
}

function setCookie(name,value) {
  var days = 30;
  var date = new Date();
  date.setTime(date.getTime() + (days*24*60*60*1000));
  var expires = "; expires=" + date.toUTCString();

  //document.cookie = name + "=" + (value || "")  + expires + "; path=/ domain=.shaversclub.nl";
  document.cookie = name + "=" + (value || "")  + expires + "; path=/; domain=localhost";
  console.log(name + "=" + (value || "")  + expires + "; path=/ domain=localhost");
}

function uuidv4() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
    var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
    return v.toString(16);
  });
}


function process_cart_status( callback ) {
  console.log('process_cart_status ' + typeof (callback));
  console.log('override not used function');
  return true;
}
function calculateTotal(e) {
  console.log('calculateTotal' + typeof (e));
  console.log('override not used function');
  return true;
}
