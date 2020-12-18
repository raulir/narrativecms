function cms_cookie_create(name, value, days) {
    var expires;

    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = '; expires=' + date.toGMTString();
    } else {
        expires = '';
    }
    document.cookie = encodeURIComponent(name) + '=' + encodeURIComponent(value) + expires + '; path=/; samesite=none; secure';
}

function cms_cookie_read(name) {
    var nameeq = encodeURIComponent(name) + '=';
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameeq) === 0) return decodeURIComponent(c.substring(nameeq.length, c.length));
    }
    return null;
}

function cms_cookie_erase(name) {
	cms_cookie_create(name, '', -1);
}
