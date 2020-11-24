(function($) {
    $.entwine('ss', function($){
        $('.cms-edit-form input.googlesuggest').entwine({
            onmatch : function() {
                $(this).autocomplete({
                    source: function( request, response ) {
                        $.ajax({
                          url: "//suggestqueries.google.com/complete/search",
                          dataType: "jsonp",
                          data: {
                            client: 'firefox',
                            q: request.term
                          },
                          success: function( data ) {
                            response( data[1] );
                          }
                        });
                    },
                    minLength: 3
                });
            }
        });
    });
})(jQuery);