jQuery(document).ready(function($) {
  $('#get-recipe').click(function(e) {
      e.preventDefault();

      var ingredients = $('#ingredients').val();
      var data = {
          'action': 'recipe_recommender',
          'ingredients': ingredients,
          'nonce': recipe_recommender_ajax_obj.nonce
      };

      $.ajax({
          url: recipe_recommender_ajax_obj.ajax_url,
          type: 'POST',
          data: data,
          dataType: 'json',
          success: function(response) {
              if (response.success) {
                  $('#recipe-result').html(response.data);
              } else {
                  $('#recipe-result').html('<p>' + response.data + '</p>');
              }
          },
          error: function(xhr, status, error) {
              console.error('AJAX Error:', error);
          }
      });
  });
});