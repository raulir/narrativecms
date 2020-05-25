
<script type="text/javascript">

	setInterval(function(){
    		document.getElementById('debug').innerHTML = 'Visible: ' + window.innerWidth + ' x ' + window.innerHeight + ' O: ' + window.orientation
    		+ ' Client: ' + document.documentElement.clientWidth + '/' + document.documentElement.clientHeight
    		 + ' isvisible desktop: ' + isVisible(document.getElementById('debug_desktop'));
    }, 250);

    function isVisible(e) {
        return !!( e.offsetWidth || e.offsetHeight || e.getClientRects().length );
    }

</script>
    
<style type="text/css">
    
    #debug_desktop {
    	display: inline;
    }
    #debug_mobile {
    	display: none;
    }
    	
    @media screen and (max-width: 900px) {
    	#debug_desktop {
    		display: none;
    	}
    	#debug_mobile {
    		display: inline;
    	}
    }

</style>
	
<div style="position: fixed; -webkit-backface-visibility: hidden; backface-visibility: hidden; z-index: 1000; background-color: black; 
		color: white; text-size: 1.0rem; padding: 0.3rem; display: inline-block; opacity: 0.05; bottom: 0; right: 0; ">
    <span id="debug"></span>
    <span id="debug_desktop"> css desktop </span>
    <span id="debug_mobile"> css mobile </span>
    <span id="debug_event"></span>
</div>
