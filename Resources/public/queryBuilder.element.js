(function($) {

    /**
     * Example:
     *     confirmDialog({html: "Feature löschen?", title: "Bitte bestätigen!", onSuccess:function(){
                  return false;
           }});
     * @param options
     * @returns {*}
     */
    function confirmDialog(options) {
        var dialog = $("<div class='confirm-dialog'>" + (options.hasOwnProperty('html') ? options.html : "") + "</div>").popupDialog({
            title:       options.hasOwnProperty('title') ? options.title : "",
            maximizable: false,
            dblclick:    false,
            minimizable: false,
            resizable:   false,
            collapsable: false,
            modal:       true,
            buttons:     [{
                text:  "OK",
                click: function(e) {
                    if(!options.hasOwnProperty('onSuccess') || options.onSuccess(e) !== false) {
                        dialog.popupDialog('close');
                    }
                    return false;
                }
            }, {
                text:    "Cancel",
                'class': 'critical',
                click:   function(e) {
                    if(!options.hasOwnProperty('onCancel') || options.onCancel(e) !== false) {
                        dialog.popupDialog('close');
                    }
                    return false;
                }
            }]
        });
        return dialog;
    }

    var widget;
    var element;
    var config;
    var exportButton = {
        text:  "Export",
        className: 'fa-cloud-download',
        click: function() {
            widget.exportData($(this).data("item"));
        }
    };
    var closeButton = {
        text:  "Cancel",
        click: function() {
            $(this).popupDialog('close');
        }
    };

    var editButton = {
        text:      "Edit",
        className: 'fa-edit',
        click:     function(e) {
            widget.openEditDialog($(this).data("item"));
        }
    };

    var createButton = {
        type:      "button",
        title:     " ",
        cssClass: 'fa-plus create',
        click:     function(e) {
            widget.openEditDialog({connection_name:"default"});
        }
    };

    var saveButton = {
        text:      "Save",
        className: 'fa-floppy-o',
        click:     function(e) {
            var dialog = $(this);
            var originData = dialog.data("item");
            $.extend(originData, dialog.formData())

            dialog.disableForm();
            widget.saveData(originData).done(function() {
                dialog.enableForm();
                $.notify("SQL saved.","notice");
            });
        }
    };
    var removeButton = {
        text:      "Remove",
        className: 'fa-remove',
        'class':   'critical',
        click:     function(e) {
            var target = $(this);
            var item = target.data("item");
            var isDialog = target.hasClass("popup-dialog");

            if(isDialog) {
                target.disableForm();
            }
            widget.removeData(item).done(function(result) {
                widget.redrawListTable();
                if(isDialog) {
                    target.popupDialog('close');
                }
                $.notify("SQL removed.", "notice");
            }).error(function() {
                target.enableForm();
            });
        }
    };

    var executeButton = {
        text:      "Execute",
        className: 'fa-play',
        'class':   'critical',
        click: function() {
            var dialog = $(this);
            var originData = dialog.data("item");
            var tempItem = dialog.formData();

            $.extend(tempItem, originData);

            widget.displayResults(tempItem, {
                title:           "Results: " + tempItem.name,
                pageResultCount: tempItem.pageResultCount
            });
        }
    };

    $.widget("mapbender.mbQueryBuilderElement", {

        sqlList:     [],
        connections: [],
        options:     {
            maxResults: 100
        },

        _create: function() {
            widget = this;
            config = widget.options;
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
         * Save item data
         * @param item
         * @returns {*}
         */
        saveData: function(item) {
            return widget.query("save", {item: item});
        },

        /**
         * Redraw list table
         */
        redrawListTable: function(){
            var tableApi = widget.getListTableApi();
            tableApi.clear();
            tableApi.rows.add(widget.sqlList);
            tableApi.draw();
        },

        /**
         * Get list table API
         *
         * @returns {*}
         */
        getListTableApi: function() {
            return $(" > div > .mapbender-element-result-table", element).resultTable("getApi");
        },

        /**
         * Remove  item data
         *
         * @param item
         * @returns {*}
         */
        removeData: function(item) {
            confirmDialog({
                title:     "Remove #" + item.id,
                html:      "Please confirm remove SQL: " + item.name,
                onSuccess: function() {
                    widget.query("remove", {id: item.id}).done(function() {
                        $.each(widget.sqlList, function(i, _item) {
                            if(_item === item) {
                                widget.sqlList.splice(i, 1);
                                return false;
                            }
                        });
                    });
                }
            });
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
         * @param item Item
         * @param config Configuration
         * @return XHR Object this has "dialog" property to get the popup dialog.
         */
        displayResults: function(item, config) {
            return widget.query("execute", {id: item.id}).done(function(results) {
                this.dialog = $("<div class='queryBuilder-results'>")
                    .data("item", item)
                    .generateElements({
                        children: [{
                            type:       "resultTable",
                            searching:  true,
                            pageLength: config.pageResultCount*10,
                            paginate:   false,
                            name:       "results",
                            data:       results,
                            columns:    widget.getColumnNames(results)
                        }]
                    })
                    .popupDialog({
                        title:   config.title ? config.title : "Results",
                        width:   $(document).width - 100,
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
            var buttons = [];

            config.allowSave && buttons.push(saveButton);
            config.allowExecute && buttons.push(executeButton);
            config.allowExport && buttons.push(exportButton);
            config.allowRemove && buttons.push(removeButton);

            buttons.push(closeButton);

            var $form = $("<form class='queryBuilder-edit'>")
                .data("item", item)
                .generateElements({
                    children: [{
                        type:     "fieldSet",
                        children: [{
                            title:       "Name",
                            type:        "input",
                            css:         {"width": "60%"},
                            name:        "name",
                            placeholder: "Query name",
                            options:     widget.connections
                        }, {
                            title:   "Connection name",
                            type:    "select",
                            name:    "connection_name",
                            css:     {"width": "25%"},
                            value:   item.connection_name,
                            options: widget.connections
                        }, {
                            title: "Anzeigen",
                            type:  "checkbox",
                            css:   {"width": "15%"},
                            value: 1,
                            name:  "anzeigen"
                        }]
                    }, {
                        type:  "textArea",
                        title: "SQL",
                        name:  "sql_definition",
                        rows:  16
                    }]
                })
                .popupDialog({
                    title:   item.name,
                    width: $(document).width() - 100,
                    buttons: buttons
                })
                .formData(item);


            if( !config.allowSave){
                $form.disableForm();
            }
            return $form;
        },

        _initialize: function() {
            widget.query("connections").done(function(connections) {
                widget.connections = connections;
                widget.query("select").done(function(results) {
                    var buttons = [];
                    var toolBar = [];

                    config.allowExport && buttons.push(exportButton);
                    config.allowExecute && buttons.push(executeButton);
                    config.allowRemove && buttons.push(removeButton);
                    config.allowEdit && buttons.push(editButton);
                    config.allowCreate && toolBar.push(createButton);

                    element.generateElements({
                        children: [{
                            type:     "fieldSet",
                            children: toolBar
                        }, {
                            type:         "resultTable",
                            name:         "queries",
                            lengthChange: false,
                            pageLength:   10,
                            info:         true,
                            processing:   false,
                            ordering:     true,
                            paging:       true,
                            selectable:   false,
                            autoWidth:    false,
                            buttons:      buttons,
                            data:         results,
                            columns:      config.tableColumns
                        }]
                    });
                    widget.sqlList = results;
                });
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
