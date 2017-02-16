var cms_change_hash_popstate_disabled = false;
var cms_change_hash_last_url = window.location.href;

function cms_change_hash(new_hash){
	cms_change_hash_popstate_disabled = true;
	if (history && history.replaceState) {
        history.replaceState({}, '', new_hash);
    } else { 
        // fallback
        scrollV = document.body.scrollTop;
        scrollH = document.body.scrollLeft;
        location.hash = new_hash;
        document.body.scrollTop = scrollV;
        document.body.scrollLeft = scrollH;
    }
	setTimeout(function(){
		cms_change_hash_popstate_disabled = false;
	}, 20);
}

function change_hash(new_hash){
	
	cms_change_hash(new_hash);

}

$(document).ready(function() {

	// for back button to work with js added history entries
	cms_change_hash_last_url = window.location.href;

	$(window).on('popstate.cms', function(e){
		
	 	if (!cms_change_hash_popstate_disabled && window.location.href !== cms_change_hash_last_url){
	 		
	 		cms_change_hash_popstate_disabled = true;
	 		window.location.href = window.location.href;
	 		setTimeout(function(){
	 			cms_change_hash_popstate_disabled = false;
	 		}, 20);
	 		
	 	}
		
	 	cms_change_hash_last_url = window.location.href;
	 	
	});

});
