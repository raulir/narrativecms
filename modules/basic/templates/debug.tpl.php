<script type="text/javascript">

	setInterval(function(){
    		document.getElementById('debug').innerHTML = 'Window: ' + window.innerWidth + ' x ' + window.innerHeight + 
    		' Client: ' + document.documentElement.clientWidth + ' x ' + document.documentElement.clientHeight + 
    		' Orientation: ' + window.orientation + ' Css detect: ' + (isVisible(document.getElementById('debug_desktop')) ? 'desktop' : 'mobile');
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
		color: white; text-size: 1.0rem; padding: 0.3rem; display: inline-block; opacity: 0.2; bottom: 0; right: 0; ">
    <span id="debug"></span>
    <span id="debug_desktop"> Css rule: desktop </span>
    <span id="debug_mobile"> Css rule: mobile </span>
    <span id="debug_event"></span>
</div>
