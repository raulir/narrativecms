'use strict';

if (typeof config_url != 'undefined'){
	var _cms_url = config_url
}

/*
 * A JavaScript implementation of the RSA Data Security, Inc. MD5 Message
 * Digest Algorithm, as defined in RFC 1321.
 * Version 2.2 Copyright (C) Paul Johnston 1999 - 2009
 * Other contributors: Greg Holt, Andrew Kepert, Ydnar, Lostinet
 * Distributed under the BSD License
 * See http://pajhome.org.uk/crypt/md5 for more info.
 */
var hexcase = 0;   /* hex output format. 0 - lowercase; 1 - uppercase        */
var b64pad  = "";  /* base-64 pad character. "=" for strict RFC compliance   */

function md5(s)    { return rstr2hex(rstr_md5(str2rstr_utf8(s))); }
function b64_md5(s)    { return rstr2b64(rstr_md5(str2rstr_utf8(s))); }
function any_md5(s, e) { return rstr2any(rstr_md5(str2rstr_utf8(s)), e); }
function hex_hmac_md5(k, d)
  { return rstr2hex(rstr_hmac_md5(str2rstr_utf8(k), str2rstr_utf8(d))); }
function b64_hmac_md5(k, d)
  { return rstr2b64(rstr_hmac_md5(str2rstr_utf8(k), str2rstr_utf8(d))); }
function any_hmac_md5(k, d, e)
  { return rstr2any(rstr_hmac_md5(str2rstr_utf8(k), str2rstr_utf8(d)), e); }

/*
 * Calculate the MD5 of a raw string
 */
function rstr_md5(s)
{
  return binl2rstr(binl_md5(rstr2binl(s), s.length * 8));
}

/*
 * Calculate the HMAC-MD5, of a key and some data (raw strings)
 */
function rstr_hmac_md5(key, data)
{
  var bkey = rstr2binl(key);
  if(bkey.length > 16) bkey = binl_md5(bkey, key.length * 8);

  var ipad = Array(16), opad = Array(16);
  for(var i = 0; i < 16; i++)
  {
    ipad[i] = bkey[i] ^ 0x36363636;
    opad[i] = bkey[i] ^ 0x5C5C5C5C;
  }

  var hash = binl_md5(ipad.concat(rstr2binl(data)), 512 + data.length * 8);
  return binl2rstr(binl_md5(opad.concat(hash), 512 + 128));
}

/*
 * Convert a raw string to a hex string
 */
function rstr2hex(input)
{
  try { hexcase } catch(e) { hexcase=0; }
  var hex_tab = hexcase ? "0123456789ABCDEF" : "0123456789abcdef";
  var output = "";
  var x;
  for(var i = 0; i < input.length; i++)
  {
    x = input.charCodeAt(i);
    output += hex_tab.charAt((x >>> 4) & 0x0F)
           +  hex_tab.charAt( x        & 0x0F);
  }
  return output;
}

/*
 * Convert a raw string to a base-64 string
 */
function rstr2b64(input)
{
  try { b64pad } catch(e) { b64pad=''; }
  var tab = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
  var output = "";
  var len = input.length;
  for(var i = 0; i < len; i += 3)
  {
    var triplet = (input.charCodeAt(i) << 16)
                | (i + 1 < len ? input.charCodeAt(i+1) << 8 : 0)
                | (i + 2 < len ? input.charCodeAt(i+2)      : 0);
    for(var j = 0; j < 4; j++)
    {
      if(i * 8 + j * 6 > input.length * 8) output += b64pad;
      else output += tab.charAt((triplet >>> 6*(3-j)) & 0x3F);
    }
  }
  return output;
}

/*
 * Convert a raw string to an arbitrary string encoding
 */
function rstr2any(input, encoding)
{
  var divisor = encoding.length;
  var i, j, q, x, quotient;

  /* Convert to an array of 16-bit big-endian values, forming the dividend */
  var dividend = Array(Math.ceil(input.length / 2));
  for(i = 0; i < dividend.length; i++)
  {
    dividend[i] = (input.charCodeAt(i * 2) << 8) | input.charCodeAt(i * 2 + 1);
  }

  /*
   * Repeatedly perform a long division. The binary array forms the dividend,
   * the length of the encoding is the divisor. Once computed, the quotient
   * forms the dividend for the next step. All remainders are stored for later
   * use.
   */
  var full_length = Math.ceil(input.length * 8 /
                                    (Math.log(encoding.length) / Math.log(2)));
  var remainders = Array(full_length);
  for(j = 0; j < full_length; j++)
  {
    quotient = Array();
    x = 0;
    for(i = 0; i < dividend.length; i++)
    {
      x = (x << 16) + dividend[i];
      q = Math.floor(x / divisor);
      x -= q * divisor;
      if(quotient.length > 0 || q > 0)
        quotient[quotient.length] = q;
    }
    remainders[j] = x;
    dividend = quotient;
  }

  /* Convert the remainders to the output string */
  var output = "";
  for(i = remainders.length - 1; i >= 0; i--)
    output += encoding.charAt(remainders[i]);

  return output;
}

/*
 * Encode a string as utf-8.
 * For efficiency, this assumes the input is valid utf-16.
 */
function str2rstr_utf8(input)
{
  var output = "";
  var i = -1;
  var x, y;

  while(++i < input.length)
  {
    /* Decode utf-16 surrogate pairs */
    x = input.charCodeAt(i);
    y = i + 1 < input.length ? input.charCodeAt(i + 1) : 0;
    if(0xD800 <= x && x <= 0xDBFF && 0xDC00 <= y && y <= 0xDFFF)
    {
      x = 0x10000 + ((x & 0x03FF) << 10) + (y & 0x03FF);
      i++;
    }

    /* Encode output as utf-8 */
    if(x <= 0x7F)
      output += String.fromCharCode(x);
    else if(x <= 0x7FF)
      output += String.fromCharCode(0xC0 | ((x >>> 6 ) & 0x1F),
                                    0x80 | ( x         & 0x3F));
    else if(x <= 0xFFFF)
      output += String.fromCharCode(0xE0 | ((x >>> 12) & 0x0F),
                                    0x80 | ((x >>> 6 ) & 0x3F),
                                    0x80 | ( x         & 0x3F));
    else if(x <= 0x1FFFFF)
      output += String.fromCharCode(0xF0 | ((x >>> 18) & 0x07),
                                    0x80 | ((x >>> 12) & 0x3F),
                                    0x80 | ((x >>> 6 ) & 0x3F),
                                    0x80 | ( x         & 0x3F));
  }
  return output;
}

/*
 * Encode a string as utf-16
 */
function str2rstr_utf16le(input)
{
  var output = "";
  for(var i = 0; i < input.length; i++)
    output += String.fromCharCode( input.charCodeAt(i)        & 0xFF,
                                  (input.charCodeAt(i) >>> 8) & 0xFF);
  return output;
}

function str2rstr_utf16be(input)
{
  var output = "";
  for(var i = 0; i < input.length; i++)
    output += String.fromCharCode((input.charCodeAt(i) >>> 8) & 0xFF,
                                   input.charCodeAt(i)        & 0xFF);
  return output;
}

/*
 * Convert a raw string to an array of little-endian words
 * Characters >255 have their high-byte silently ignored.
 */
function rstr2binl(input)
{
  var output = Array(input.length >> 2);
  for(var i = 0; i < output.length; i++)
    output[i] = 0;
  for(var i = 0; i < input.length * 8; i += 8)
    output[i>>5] |= (input.charCodeAt(i / 8) & 0xFF) << (i%32);
  return output;
}

/*
 * Convert an array of little-endian words to a string
 */
function binl2rstr(input)
{
  var output = "";
  for(var i = 0; i < input.length * 32; i += 8)
    output += String.fromCharCode((input[i>>5] >>> (i % 32)) & 0xFF);
  return output;
}

/*
 * Calculate the MD5 of an array of little-endian words, and a bit length.
 */
function binl_md5(x, len)
{
  /* append padding */
  x[len >> 5] |= 0x80 << ((len) % 32);
  x[(((len + 64) >>> 9) << 4) + 14] = len;

  var a =  1732584193;
  var b = -271733879;
  var c = -1732584194;
  var d =  271733878;

  for(var i = 0; i < x.length; i += 16)
  {
    var olda = a;
    var oldb = b;
    var oldc = c;
    var oldd = d;

    a = md5_ff(a, b, c, d, x[i+ 0], 7 , -680876936);
    d = md5_ff(d, a, b, c, x[i+ 1], 12, -389564586);
    c = md5_ff(c, d, a, b, x[i+ 2], 17,  606105819);
    b = md5_ff(b, c, d, a, x[i+ 3], 22, -1044525330);
    a = md5_ff(a, b, c, d, x[i+ 4], 7 , -176418897);
    d = md5_ff(d, a, b, c, x[i+ 5], 12,  1200080426);
    c = md5_ff(c, d, a, b, x[i+ 6], 17, -1473231341);
    b = md5_ff(b, c, d, a, x[i+ 7], 22, -45705983);
    a = md5_ff(a, b, c, d, x[i+ 8], 7 ,  1770035416);
    d = md5_ff(d, a, b, c, x[i+ 9], 12, -1958414417);
    c = md5_ff(c, d, a, b, x[i+10], 17, -42063);
    b = md5_ff(b, c, d, a, x[i+11], 22, -1990404162);
    a = md5_ff(a, b, c, d, x[i+12], 7 ,  1804603682);
    d = md5_ff(d, a, b, c, x[i+13], 12, -40341101);
    c = md5_ff(c, d, a, b, x[i+14], 17, -1502002290);
    b = md5_ff(b, c, d, a, x[i+15], 22,  1236535329);

    a = md5_gg(a, b, c, d, x[i+ 1], 5 , -165796510);
    d = md5_gg(d, a, b, c, x[i+ 6], 9 , -1069501632);
    c = md5_gg(c, d, a, b, x[i+11], 14,  643717713);
    b = md5_gg(b, c, d, a, x[i+ 0], 20, -373897302);
    a = md5_gg(a, b, c, d, x[i+ 5], 5 , -701558691);
    d = md5_gg(d, a, b, c, x[i+10], 9 ,  38016083);
    c = md5_gg(c, d, a, b, x[i+15], 14, -660478335);
    b = md5_gg(b, c, d, a, x[i+ 4], 20, -405537848);
    a = md5_gg(a, b, c, d, x[i+ 9], 5 ,  568446438);
    d = md5_gg(d, a, b, c, x[i+14], 9 , -1019803690);
    c = md5_gg(c, d, a, b, x[i+ 3], 14, -187363961);
    b = md5_gg(b, c, d, a, x[i+ 8], 20,  1163531501);
    a = md5_gg(a, b, c, d, x[i+13], 5 , -1444681467);
    d = md5_gg(d, a, b, c, x[i+ 2], 9 , -51403784);
    c = md5_gg(c, d, a, b, x[i+ 7], 14,  1735328473);
    b = md5_gg(b, c, d, a, x[i+12], 20, -1926607734);

    a = md5_hh(a, b, c, d, x[i+ 5], 4 , -378558);
    d = md5_hh(d, a, b, c, x[i+ 8], 11, -2022574463);
    c = md5_hh(c, d, a, b, x[i+11], 16,  1839030562);
    b = md5_hh(b, c, d, a, x[i+14], 23, -35309556);
    a = md5_hh(a, b, c, d, x[i+ 1], 4 , -1530992060);
    d = md5_hh(d, a, b, c, x[i+ 4], 11,  1272893353);
    c = md5_hh(c, d, a, b, x[i+ 7], 16, -155497632);
    b = md5_hh(b, c, d, a, x[i+10], 23, -1094730640);
    a = md5_hh(a, b, c, d, x[i+13], 4 ,  681279174);
    d = md5_hh(d, a, b, c, x[i+ 0], 11, -358537222);
    c = md5_hh(c, d, a, b, x[i+ 3], 16, -722521979);
    b = md5_hh(b, c, d, a, x[i+ 6], 23,  76029189);
    a = md5_hh(a, b, c, d, x[i+ 9], 4 , -640364487);
    d = md5_hh(d, a, b, c, x[i+12], 11, -421815835);
    c = md5_hh(c, d, a, b, x[i+15], 16,  530742520);
    b = md5_hh(b, c, d, a, x[i+ 2], 23, -995338651);

    a = md5_ii(a, b, c, d, x[i+ 0], 6 , -198630844);
    d = md5_ii(d, a, b, c, x[i+ 7], 10,  1126891415);
    c = md5_ii(c, d, a, b, x[i+14], 15, -1416354905);
    b = md5_ii(b, c, d, a, x[i+ 5], 21, -57434055);
    a = md5_ii(a, b, c, d, x[i+12], 6 ,  1700485571);
    d = md5_ii(d, a, b, c, x[i+ 3], 10, -1894986606);
    c = md5_ii(c, d, a, b, x[i+10], 15, -1051523);
    b = md5_ii(b, c, d, a, x[i+ 1], 21, -2054922799);
    a = md5_ii(a, b, c, d, x[i+ 8], 6 ,  1873313359);
    d = md5_ii(d, a, b, c, x[i+15], 10, -30611744);
    c = md5_ii(c, d, a, b, x[i+ 6], 15, -1560198380);
    b = md5_ii(b, c, d, a, x[i+13], 21,  1309151649);
    a = md5_ii(a, b, c, d, x[i+ 4], 6 , -145523070);
    d = md5_ii(d, a, b, c, x[i+11], 10, -1120210379);
    c = md5_ii(c, d, a, b, x[i+ 2], 15,  718787259);
    b = md5_ii(b, c, d, a, x[i+ 9], 21, -343485551);

    a = safe_add(a, olda);
    b = safe_add(b, oldb);
    c = safe_add(c, oldc);
    d = safe_add(d, oldd);
  }
  return Array(a, b, c, d);
}

/*
 * These functions implement the four basic operations the algorithm uses.
 */
function md5_cmn(q, a, b, x, s, t)
{
  return safe_add(bit_rol(safe_add(safe_add(a, q), safe_add(x, t)), s),b);
}
function md5_ff(a, b, c, d, x, s, t)
{
  return md5_cmn((b & c) | ((~b) & d), a, b, x, s, t);
}
function md5_gg(a, b, c, d, x, s, t)
{
  return md5_cmn((b & d) | (c & (~d)), a, b, x, s, t);
}
function md5_hh(a, b, c, d, x, s, t)
{
  return md5_cmn(b ^ c ^ d, a, b, x, s, t);
}
function md5_ii(a, b, c, d, x, s, t)
{
  return md5_cmn(c ^ (b | (~d)), a, b, x, s, t);
}

/*
 * Add integers, wrapping at 2^32. This uses 16-bit operations internally
 * to work around bugs in some JS interpreters.
 */
function safe_add(x, y)
{
  var lsw = (x & 0xFFFF) + (y & 0xFFFF);
  var msw = (x >> 16) + (y >> 16) + (lsw >> 16);
  return (msw << 16) | (lsw & 0xFFFF);
}

/*
 * Bitwise rotate a 32-bit number to the left.
 */
function bit_rol(num, cnt)
{
  return (num << cnt) | (num >>> (32 - cnt));
}

function str_replace(str, findArray, replaceArray) {
	var i, regex = [], map = {};
	for (i = 0; i < findArray.length; i++) {
		regex.push(findArray[i].replace(/([-[\]{}()*+?.\\^$|#,])/g, '\\$1'));
		map[findArray[i]] = replaceArray[i];
	}
	regex = regex.join('|');
	str = str.replace(new RegExp(regex, 'g'), function(matched) {
		return map[matched];
	});
	return str;
}

String.prototype.to_title_case = function () {
    return this.replace(/\w\S*/g, function(txt){return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();});
};

/*
 * 
 * 
 * panels related javascript
 * 
 * 
 */

function get_ajax(name, params){

	return new Promise((resolve, reject) => {
		
		var ext_params = Object.assign({
			'no_html': '1', 
			'success': data => {
				resolve(data)
			}
		}, params)

		var action_on_success = ext_params.success
		delete ext_params.success
		
		get_ajax_panel(name, ext_params, action_on_success)
		
	})

}

var _cms_test_localstorage = function() {
	
	var test = 'test';
    try {
        localStorage.setItem(test, test);
        localStorage.removeItem(test);
        return true;
    } catch(e) {
        return false;
    }

};

function get_ajax_panel(name, args, action_on_success){

	var params = Object.assign({
		'cache': ''
	}, args)

	return new Promise ((resolve, reject) => {

		// TODO: cms_page_panel.js:144-163 - use script running from there to activate external javascripts
		
		var data = false;
		
		var cache = 0;
		if (typeof params.cache != 'undefined'){
			cache = parseInt(params.cache);
		}
		params.cache = '';
		
		// try to read from storage
		if (_cms_test_localstorage() && cache > 0 && !admin_logged_in){
			var key = md5(config_url + name + JSON.stringify(params));
			var local_data = localStorage.getItem(key);
			if (local_data){
				data = $.parseJSON(local_data);
				if (data.storage_timestamp > +new Date() - (cache * 1000)){
					if (action_on_success){
						action_on_success(data);
					} else {
						resolve(data)
					}
				} else {
					data = false;
					localStorage.removeItem(key);
				}
			}
		}

		if (!data){
			params.panel_id = name;
			$.ajax({
				type: 'POST',
			  	url: _cms_url + 'ajax_api/get_panel/',
			  	data: params,
			  	dataType: 'json',
			  	context: this,
			  	success: function( returned_data ) {
			  		
			  		if ((typeof returned_data.result != 'undefined') && (typeof returned_data.result._html != 'undefined') && !returned_data.result.html){
			  			returned_data.result.html = returned_data.result._html
			  		}
			  		
			  		// save to local storage
			  		if (_cms_test_localstorage() && cache > 0){
			  			returned_data.storage_timestamp = new Date().getTime();
			  			localStorage.setItem(key, JSON.stringify(returned_data));
			  		}
	
					if (typeof data.result == 'object'){
				  		$.each(returned_data.result, (key, value) => {
				  			if (typeof value == 'string' || typeof value == 'number'|| typeof value == 'bigint'){
				  				$('.__' + name.replace('/', '__') + '__' + key).html(value)
				  			}
				  		})
			  		}
			  		
					if (action_on_success){
						action_on_success(returned_data);
					} else {
						resolve(returned_data)
					}
					
				}
			});
		}
		
	})
			
}

function get_ajax_panel_anchor(anchor, params){

	return new Promise ((resolve, reject) => {
		
		if (!params){
			params = {}
		}
		
		params.anchor = anchor

		$.ajax({
			type: 'POST',
		  	url: config_url + 'ajax_api/get_panel_anchor/',
		  	data: params,
		  	dataType: 'json',
		  	context: this,
		  	success: function( returned_data ) {

				resolve(returned_data)
				
			},
			error: function( return_handler ) {
				
				var data = {}
				data.result = {'html':return_handler.responseText}
				resolve(data)
				
			}
		})
		
	})
	
}

function get_ajax_page(url, params, action_on_success){
	
	params = $.extend({ '_positions': ['main'] }, params)

	var data = false;
/*	
	var cache = 0;
	if (typeof params.cache != 'undefined'){
		cache = parseInt(params.cache);
	}
	params.cache = '';

	// try to read from storage
	if (_cms_test_localstorage() && cache > 0 && !admin_logged_in){
		var key = md5(config_url + name + JSON.stringify(params));
		var local_data = localStorage.getItem(key);
		if (local_data){
			data = $.parseJSON(local_data);
			if (data.storage_timestamp > +new Date() - (cache * 1000)){
				action_on_success(data);
			} else {
				data = false;
				localStorage.removeItem(key);
			}
		}
	}
*/
	if (!data){
		params._url = url;
		params._ajax = 1;
		$.ajax({
			type: 'POST',
		  	url: params._url,
		  	data: params,
		  	dataType: 'json',
		  	context: this,
		  	success: function( returned_data ) {

		  		/*
		  		// save to local storage
		  		if (_cms_test_localstorage() && cache > 0){
		  			returned_data.storage_timestamp = new Date().getTime();
		  			localStorage.setItem(key, JSON.stringify(returned_data));
		  		}
		  		*/
		  		
		  		action_on_success(returned_data);

		  	}
		});
	}
	
}

// currently only used in cms_position_link.js
function get_ajax_positions(url, params, action_on_success){

	var data = false;

	if (!data){

		params._url = url;
		params._ajax = 1;

		$.ajax({
			type: 'POST',
		  	url: params._url,
		  	data: params,
		  	dataType: 'json',
		  	context: this,
		  	success: function( returned_data ) {

		  		action_on_success(returned_data);

		  	}
		})

	}
	
}

function panels_display_popup(html, params){
	
	$('body').append(html);
	
	params = $.extend({
		'yes': function(after){
			after();
		},
		'select': function(after){
			after();
		},
		'cancel': function(after){
			after();
		},
		'pre_close': function(after){
			after();
		},
		'clean_up': function(){
			
		}
	}, params);
	
	var clean_up = function(){
		$('.popup_container,.popup_overlay').css({'opacity':'0'});
		setTimeout(function(){
			$('.popup_container,.popup_overlay,.cms_popup_container,.cms_popup_overlay').remove();
		}, 300);
	};
	
	$('.popup_yes').off('click.r').on('click.r', function(){
		params.pre_close(function(){
			params.yes(function(){
				clean_up();
				params.clean_up();
			});
		});
	});

	$('.popup_cancel').off('click.r').on('click.r', function(){
		params.pre_close(function(){
			params.cancel(function(){
				clean_up();
				params.clean_up();
			});
		});
	});

	$('.popup_select').off('click.r').on('click.r', function(){
		params.pre_close(function(){
			params.select(function(){
				clean_up();
				params.clean_up();
			});
		});
	});
	
}

function _cms_load_css(filename){
	return new Promise((resolve,reject) => {
		
		let link = document.createElement('link')
	    
	    link.type = 'text/css'
	    link.rel    = 'stylesheet'
	    link.addEventListener('load',resolve)
	    link.href = filename
	    
	    document.head.appendChild(link)
	    
	})
}

function cms_load_css(filenames, force_download, class_to_remove){
	
	return new Promise((resolve,reject) => {
	
		var load_total = 0
		var load_finished = 0
		
		$(filenames).each(function(key, filename){
	
			if (filename.indexOf('?') !== -1){
				var clean_filename = filename.substr(0, filename.indexOf('?'));
			} else {
				var clean_filename = filename;
			}
			
			var found = false;
			
			$('link[type="text/css"]').each(function(){
				if (this.href.indexOf(clean_filename) !== -1){
					found = true;
				}
			});
	
			if(!found){
	
				if (force_download){
					filename = clean_filename + '?v=' + Math.round(Math.random() * 10000000);
				}
				
				load_total += 1
				_cms_load_css(filename).then(() => {
					load_finished += 1
					setTimeout(() => {
						if (load_finished == load_total){
							resolve()
						}
//						console.log(load_finished)
					}, 100)
				})
				
				// $('head').append('<link rel="stylesheet" type="text/css" href="' + filename + '"/>');
	
			}
			
		});
	
//		$('.' + class_to_remove).remove();
		
		if (load_total == 0){
			resolve()
		} else {
			setTimeout(() => {
				resolve()
			}, 5000)
		}

	})

}

function get_api(name, params){

	var ext_params = $.extend({'success': function(){} }, params)
	var action_on_success = ext_params.success;
	delete ext_params.success;

	$.ajax({
		type: 'POST',
	  	url: _cms_url + name,
	  	data: ext_params,
	  	dataType: 'json',
	  	context: this,
	  	success: function( returned_data ) {
	  		action_on_success(returned_data);
	  	}
	});
	
}

/*
 * 
 *  framework related javascript 
 *  
 *  
 **/

// if no console - IE <= 9
if(!window.console) {
	window.console = {};
	window.console.log = function(str) {};
	window.console.dir = function(str) {};
}

Object.keys = Object.keys || function(o) { 
    var keysArray = []; 
    for(var name in o) { 
        if (o.hasOwnProperty(name)) 
          keysArray.push(name); 
    } 
    return keysArray; 
}

if (typeof String.prototype.endsWith !== 'function') {
    String.prototype.endsWith = function(suffix) {
        return this.indexOf(suffix, this.length - suffix.length) !== -1;
    };
}

function stackTrace() {
    var err = new Error();
    return err.stack;
}

function preg_quote( str ) {
    return (str+'').replace(/([\\\.\+\*\?\[\^\]\$\(\)\{\}\=\!\<\>\|\:])/g, "\\$1");
}

function change_url(new_url){
	if (history && history.pushState) {

		if ( !window.location.href.endsWith(new_url) || new_url == '/'){
			history.pushState({}, '', new_url);
			var cms_last_url = window.location.href;
		}

	}
}

function cms_hover_init(){

	// hover button changes
	$('.cms_hover_button').each(function(){
		if (!$(this).data('normal_image')){
			$(this).data('normal_image', $(this).css('background-image'));
		}
	});
	$('.cms_hover_button').on('mouseenter.sc', function(){
		var $this = $(this);
		if (!$this.hasClass('cms_hover_disabled')){
			$this.addClass($this.data('hover_class'));
			setTimeout(function(){
				$this.css({'background-image':$this.data('hover_image')});
			}, 150);
		};
	});
	$('.cms_hover_button').on('mouseleave.sc', function(){
		var $this = $(this);
		if (!$this.hasClass('cms_hover_disabled')){
			$this.removeClass($this.data('hover_class'));
			setTimeout(function(){
				$this.css({'background-image':$this.data('normal_image')});
			}, 150);
		}
	});
}

var get_ios_windowheight = function() {
    var zoomLevel = document.documentElement.clientWidth / window.innerWidth;
    return window.innerHeight * zoomLevel;
};

// detect if touch
function is_touch_event(e){
	if (e.type == 'touchstart' || e.type == 'touchmove' || e.type == 'touchend'){
		return true;
	} else {
		return false;
	}
}

// Production steps of ECMA-262, Edition 5, 15.4.4.18
// Reference: http://es5.github.io/#x15.4.4.18
if (!Array.prototype.forEach) {

	Array.prototype.forEach = function(callback/* , thisArg */) {
		var T, k;
		if (this == null) {
			throw new TypeError('this is null or not defined');
		}
		var O = Object(this);
		var len = O.length >>> 0;
		if (typeof callback !== 'function') {
			throw new TypeError(callback + ' is not a function');
		}
		if (arguments.length > 1) {
			T = arguments[1];
		}
		k = 0;
		while (k < len) {
			var kValue;
			if (k in O) {
				kValue = O[k];
				callback.call(T, kValue, k, O);
			}
			k++;
		}
	};
}

function format_date(date, format, utc) {
	
    var MMMM = ["\x00", "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    var MMM = ["\x01", "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    var dddd = ["\x02", "Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
    var ddd = ["\x03", "Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

    function ii(i, len) {
        var s = i + "";
        len = len || 2;
        while (s.length < len) s = "0" + s;
        return s;
    }

    var y = utc ? date.getUTCFullYear() : date.getFullYear();
    format = format.replace(/(^|[^\\])yyyy+/g, "$1" + y);
    format = format.replace(/(^|[^\\])yy/g, "$1" + y.toString().substr(2, 2));
    format = format.replace(/(^|[^\\])y/g, "$1" + y);

    var M = (utc ? date.getUTCMonth() : date.getMonth()) + 1;
    format = format.replace(/(^|[^\\])MMMM+/g, "$1" + MMMM[0]);
    format = format.replace(/(^|[^\\])MMM/g, "$1" + MMM[0]);
    format = format.replace(/(^|[^\\])MM/g, "$1" + ii(M));
    format = format.replace(/(^|[^\\])M/g, "$1" + M);

    var d = utc ? date.getUTCDate() : date.getDate();
    format = format.replace(/(^|[^\\])dddd+/g, "$1" + dddd[0]);
    format = format.replace(/(^|[^\\])ddd/g, "$1" + ddd[0]);
    format = format.replace(/(^|[^\\])dd/g, "$1" + ii(d));
    format = format.replace(/(^|[^\\])d/g, "$1" + d);

    var H = utc ? date.getUTCHours() : date.getHours();
    format = format.replace(/(^|[^\\])HH+/g, "$1" + ii(H));
    format = format.replace(/(^|[^\\])H/g, "$1" + H);

    var h = H > 12 ? H - 12 : H == 0 ? 12 : H;
    format = format.replace(/(^|[^\\])hh+/g, "$1" + ii(h));
    format = format.replace(/(^|[^\\])h/g, "$1" + h);

    var m = utc ? date.getUTCMinutes() : date.getMinutes();
    format = format.replace(/(^|[^\\])mm+/g, "$1" + ii(m));
    format = format.replace(/(^|[^\\])m/g, "$1" + m);

    var s = utc ? date.getUTCSeconds() : date.getSeconds();
    format = format.replace(/(^|[^\\])ss+/g, "$1" + ii(s));
    format = format.replace(/(^|[^\\])s/g, "$1" + s);

    var f = utc ? date.getUTCMilliseconds() : date.getMilliseconds();
    format = format.replace(/(^|[^\\])fff+/g, "$1" + ii(f, 3));
    f = Math.round(f / 10);
    format = format.replace(/(^|[^\\])ff/g, "$1" + ii(f));
    f = Math.round(f / 10);
    format = format.replace(/(^|[^\\])f/g, "$1" + f);

    var T = H < 12 ? "AM" : "PM";
    format = format.replace(/(^|[^\\])TT+/g, "$1" + T);
    format = format.replace(/(^|[^\\])T/g, "$1" + T.charAt(0));

    var t = T.toLowerCase();
    format = format.replace(/(^|[^\\])tt+/g, "$1" + t);
    format = format.replace(/(^|[^\\])t/g, "$1" + t.charAt(0));

    var tz = -date.getTimezoneOffset();
    var K = utc || !tz ? "Z" : tz > 0 ? "+" : "-";
    if (!utc) {
        tz = Math.abs(tz);
        var tzHrs = Math.floor(tz / 60);
        var tzMin = tz % 60;
        K += ii(tzHrs) + ":" + ii(tzMin);
    }
    format = format.replace(/(^|[^\\])K/g, "$1" + K);

    var day = (utc ? date.getUTCDay() : date.getDay()) + 1;
    format = format.replace(new RegExp(dddd[0], "g"), dddd[day]);
    format = format.replace(new RegExp(ddd[0], "g"), ddd[day]);

    format = format.replace(new RegExp(MMMM[0], "g"), MMMM[M]);
    format = format.replace(new RegExp(MMM[0], "g"), MMM[M]);

    format = format.replace(/\\(.)/g, "$1");

    return format;
};

var cms_disable_zoom = function () { 
	if (!(/iPad|iPhone|iPod/.test(navigator.userAgent))) return; 
	$(document.head).append('<style>*{cursor:pointer;-webkit-tap-highlight-color:rgba(0,0,0,0)}</style>'); 
	$(window).on('gesturestart touchmove', function (evt) {
		if (evt.originalEvent.scale !== 1) { evt.originalEvent.preventDefault(); document.body.style.transform = 'scale(1)'; } 
	}); 
}

var injectScript = function(src, id) {
	
	return new Promise((resolve, reject) => {
		
		var s = 'script';
	    var js, fjs = document.getElementsByTagName(s)[0]
	    
	    if (!id) id = src

	    if (document.getElementById(id)) abort('Script loaded already: ' + src)
	    
	    js = document.createElement(s)
	    js.id = id
	    js.addEventListener('load', resolve)
	    js.addEventListener('error', () => reject('Script loading error: ' + src))
	    js.src = src
	    fjs.parentNode.insertBefore(js, fjs)
	
	})
	
}

var serialize_form = function(form_selector){

	var data = $(form_selector).serializeArray();
	var data_to_submit = {};
	$.each(data, function(key, value){
		var re = value.name.slice(-2);
		if (re == '[]') {
			var name = value.name.replace('[]', '');
			if(typeof data_to_submit[name] == 'undefined' || !$.isArray(data_to_submit[name])){
				data_to_submit[name] = [value.value];
			} else {
				data_to_submit[name].push(value.value);
			}
		} else {
			data_to_submit[value.name] = value.value;
		}
	});
	
	return data_to_submit

}

const zero_pad = (num, places) => String(num).padStart(places || 2, '0')

$(document).ready(function() {
	
	cms_hover_init();
	
	if (typeof cms_disable_zoom == 'function'){
		cms_disable_zoom();
	}
	
});