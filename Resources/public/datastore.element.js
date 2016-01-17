(function($) {

    var widget;
    var element;
    var exportButton = {
        text:  'Export',
        click: function() {
            var data = $(this);
            debugger;
            widget.exportData(item);
        }
    };
    var closeButton = {
        text:    "Close",
        'class': 'critical',
        click:   function() {
            $(this).popupDialog('close');
        }
    };

    var executeButton = {
        text:    "Execute",
        'class': 'critical',
        click:   function() {
            debugger;
            widget.displayResults(item);
        }
    };

    var editButton = {
        title:     "Edit",
        className: 'edit',
        onClick:   function(data, ui) {
            widget.openEditDialog(data);
        }
    };

    $.widget("mapbender.mbDataStoreElement", {

        options: {
            maxResults: 100
        },

        _create: function() {
            widget = this;
            element = $(widget.element);
            widget.elementUrl = Mapbender.configuration.application.urls.element + '/' + element.attr('id') + '/';
            widget._initialize();
        },

        /**
         * Execute SQL and export als excel or data.
         * This fake the form POST method to get download export file.
         *
         * @returns jQuery form object
         * @param item
         */
        exportData: function(item) {
            return $('<form action="' + widget.elementUrl + 'export" method="post"/>')
                .append('<input type="text" name="id"  value="' + item.id + '"/>')
                .submit();
        },

        /**
         * Get column names
         *
         * @param items
         * @returns {Array}
         */
        getColumnNames: function(items) {
            var columns = [];
            if(items.length) {
                for (var key in items[0]) {
                    columns.push({
                        data:  key,
                        title: key
                    });
                }
            }
            return columns;
        },

        /**
         * Executes SQL by ID and display results as popups
         *
         * @param item
         * @param title
         * @return XHR Object this has "dialog" property to get the popup dialog.
         */
        displayResults: function(item, title) {
            return widget.query("execute", {id: item.id}).done(function(results) {
                this.dialog = $("<div>")
                    .data("item", item)
                    .generateElements({
                        children: [{
                            type:    "resultTable",
                            name:    "results",
                            data:    results,
                            columns: widget.getColumnNames(results)
                        }]
                    })
                    .popupDialog({
                        title:   title ? title : "Results",
                        buttons: [closeButton, exportButton]
                    });
            });
        },

        /**
         * Open SQL edit dialog
         *
         * @param item
         */
        openEditDialog: function(item) {
            return $("<div>")
                .data("item", item)
                .generateElements({
                    children: [{
                        type:  "textArea",
                        name:  "SQL",
                        value: item.sql_definition,
                        rows:  8
                    }]
                })
                .popupDialog({
                    title:   item.name,
                    buttons: [closeButton, exportButton, executeButton]
                });
        },

        _initialize: function() {
            widget.query("select").done(function(results) {
                var dialog = $("<div/>");
                if(!widget.options.hasOwnProperty("formItems")) {
                    return;
                }
                var formItems = widget.options.formItems;
                var table = formItems[0];
                table.data = results;
                table.buttons = [executeButton, editButton];
                dialog.generateElements({children: formItems});
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
