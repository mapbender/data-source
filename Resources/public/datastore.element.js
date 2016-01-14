(function($) {
    $.widget("mapbender.mbDataStoreElement", {
        options: {
            maxResults: 100
        },
        _create: function() {
            var widget = this;
            var element = $(widget.element);
            var options = widget.options;
            widget.elementUrl = Mapbender.configuration.application.urls.element + '/' + element.attr('id') + '/';
            //Mapbender.elementRegistry.onElementReady(options.target, $.proxy(widget._initialize, widget));
            widget._initialize();
        },

        _initialize: function() {
            var widget = this;
            var element = $(widget.element);
            widget.query("select").done(function(results) {
                var dialog = $("<div/>");
                if(!widget.options.hasOwnProperty("formItems")) {
                    return;
                }
                var formItems = widget.options.formItems;
                var table = formItems[0];

                table.data = results;
                table.buttons = [{
                    title:     "Edit",
                    className: 'edit',
                    onClick:   function(data, ui) {
                        console.log(data, ui);
                    }
                },{
                    title:     "Export",
                    className: 'edit',
                    onClick:   function(data, ui) {
                        console.log(data, ui);
                    }
                }];
                dialog.generateElements({children: formItems});

                var tableElement = $("[name='queries']",dialog);

                element.append(dialog)
            });
        },

        /**
         * API connection query
         *
         * @param uri suffix
         * @param request query
         * @return xhr jQuery XHR object
         * @version 0.2
         */
        query: function(uri, request) {
            var widget = this;
            return $.ajax({
                url:         widget.elementUrl + uri,
                type:        'POST',
                contentType: "application/json; charset=utf-8",
                dataType:    "json",
                data:        JSON.stringify(request)
            }).error(function(xhr) {
                var errorMessage = "Api-Error: ";// translate('api.query.error-message');
                $.notify(errorMessage + JSON.stringify(xhr.responseText));
                console.log(errorMessage, xhr);
            });
        }
    });
})(jQuery);
