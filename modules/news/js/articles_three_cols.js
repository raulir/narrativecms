function articles_three_cols_init(){
	
	$('.articles_three_cols_bottom').on('click.r', function(){
		get_ajax_panel(
			'articles_load_more', 
			{
				'increment': $(this).data('increment'), 
				'start': $('.articles_three_cols_content .article_thumbnail_container').length,
				'types': $(this).data('types') 
			}, 
			function(data){
				
				var $articles_three_cols_content = $('.articles_three_cols_content');
				var height = $articles_three_cols_content.height();
				$articles_three_cols_content.height(height);
				
				$('.articles_three_cols_content>div').last().remove();
				$articles_three_cols_content.append(data.result.html + '<div style="clear: both; "></div>');
				
				$articles_three_cols_content.animate({'height': $articles_three_cols_content[0].scrollHeight}, 800);

			}
		);
	});
	
}

function articles_three_cols_resize(){

}

$(document).ready(function() {
	
	$(window).on('resize.r', function(){
		articles_three_cols_resize();
	});
	
	setTimeout(articles_three_cols_resize, 1000);
	articles_three_cols_resize();
	
	articles_three_cols_init();
	
});
