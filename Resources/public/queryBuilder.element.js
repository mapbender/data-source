(function($) {

    /**
     * Translate digitizer keywords
     * @param title
     * @returns {*}
     */
    function trans(title) {
        var key = "mb.query.builder." + title;
        return Mapbender.trans(key);

    }

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
                text:  trans('OK'),
                click: function(e) {
                    if(!options.hasOwnProperty('onSuccess') || options.onSuccess(e) !== false) {
                        dialog.popupDialog('close');
                    }
                    return false;
                }
            }, {
                text:    trans('Cancel'),
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
        text:  trans('Export'),
        className: 'fa-cloud-download',
        click: function() {
            widget.exportData ($(this).data("item"));
        }
    };

    var exportHtmlButton = {
        text:  trans('HTML-Export'),
        className: 'fa-table',
        click: function() {
            widget.exportHtml($(this).data("item"));
        }
    };

    var closeButton = {
        text:  trans('Cancel'),
        click: function() {
            $(this).popupDialog('close');
        }
    };

    var editButton = {
        text:      trans('Edit'),
        className: 'fa-edit',
        click:     function(e) {
            widget.openEditDialog($(this).data("item"));
        }
    };

    var createButton = {
        type:      "button",
        text:      trans('Create'),
        title:     " ",
        cssClass: 'fa-plus create',
        click:     function(e) {
            widget.openEditDialog({connection_name:"default"});
        }
    };

    var saveButton = {
        text:      trans('Save'),
        className: 'fa-floppy-o',
        click:     function(e) {
            var dialog = $(this);
            var originData = dialog.data("item");
            $.extend(originData, dialog.formData())

            dialog.disableForm();
            widget.saveData(originData).done(function() {
                dialog.enableForm();
                widget.redrawListTable();
                $.notify(trans('sql.saved'),"notice");
            });
        }
    };
    var removeButton = {
        text:      trans('Remove'),
        className: 'fa-remove',
        'class':   'critical',
        click:     function(e) {
            var target = $(this);
            var item = target.data("item");
            var isDialog = target.hasClass("popup-dialog");

            if(isDialog) {
                target.disableForm();
            }
            widget.removeData(item, function(result) {
                widget.redrawListTable();
                if(isDialog) {
                    target.popupDialog('close');
                }
                $.notify(trans('sql.removed'), "notice");
            }, function() {
                target.enableForm();
            });
        }
    };

    var executeButton = {
        text:      trans('Execute'),
        className: 'fa-play',
        'class':   'critical',
        click: function() {
            var dialog = $(this);
            var originData = dialog.data("item");
            var tempItem = dialog.formData();

            $.extend(tempItem, originData);

            widget.displayResults(tempItem, {
                title:           trans('Results') + ": " + tempItem.name,
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
         * Export as HTML.
         *
         * @param item
         */
        exportHtml: function(item) {
            window.open(widget.elementUrl + 'exportHtml?id='+item.id);
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
            return;
            // TODO: get this work!
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
        removeData: function(item, onDone, onError) {
            confirmDialog({
                title:     trans("Remove") + " #" + item.id,
                html:      trans("confirm.remove") + ": " + item.name, // Please confirm remove SQL
                onSuccess: function() {
                    widget.query("remove", {id: item.id}).done(function() {
                            $.each(widget.sqlList, function(i, _item) {
                                if(_item === item) {
                                    widget.sqlList.splice(i, 1);
                                    return false;
                                }
                            });
                        })
                        .done(onDone)
                        .error(onError);
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
                        type:       "resultTable", //searching:  true,
                        selectable: true, //paginate:   false,
                        paging:     false,
                        //searching:  true,
                        name:       "results",
                        data:       results,
                        info:       false,
                        columns:    widget.getColumnNames(results)
                    })
                    .popupDialog({
                        title:   config.title ? config.title : trans("Results") + " : " + results.length,
                        width:   1000,
                        height:  400,
                        buttons: [exportButton, exportHtmlButton, closeButton]
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
            config.allowExport && buttons.push(exportHtmlButton);
            config.allowRemove && buttons.push(removeButton);

            buttons.push(closeButton);

            var $form = $("<form class='queryBuilder-edit'>")
                .data("item", item)
                .generateElements({
                    children: [{
                        type:     "fieldSet",
                        children: [{
                            title:       trans("sql.title"), // "Name"
                            type:        "input",
                            css:         {"width": "60%"},
                            name:        config.titleFieldName,
                            placeholder: "Query name",
                            options:     widget.connections
                        }, {
                            title:   trans("sql.connection.name"), //  "Connection name"
                            type:    "select",
                            name:    config.connectionFieldName,
                            css:     {"width": "25%"},
                            value:   item.connection_name,
                            options: widget.connections
                        }, {
                            title: trans("sql.publish"), //  "Anzeigen"
                            type:  "checkbox",
                            css:   {"width": "15%"},
                            value: 1,
                            name:  config.publicFieldName
                        }]
                    }, {
                        type:  "textArea",
                        title: "SQL",
                        name:  config.sqlFieldName,
                        rows:  16
                    }]
                })
                .popupDialog({
                    title:   item.name,
                    width: 500,
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
                    config.allowExport && buttons.push(exportHtmlButton);
                    config.allowExecute && buttons.push(executeButton);
                    config.allowEdit && buttons.push(editButton);
                    config.allowRemove && buttons.push(removeButton);
                    config.allowCreate && toolBar.push(createButton);

                    element.generateElements({
                        children: [{
                            type:     "fieldSet",
                            children: toolBar
                        }, {
                            type:         "resultTable",
                            name:         "queries",
                            lengthChange: false,
                            pageLength:   17,
                            info:         true,
                            searching:    config.allowSearch,
                            processing:   false,
                            ordering:     true,
                            paging:       true,
                            selectable:   false,
                            autoWidth:    false,
                            order:        [[1, "desc"]],
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
                var errorMessage = trans("api.error") + ": ";// trans('api.query.error-message');
                $.notify(errorMessage + JSON.stringify(xhr.responseText));
                console.log(errorMessage, xhr);
            });
        }
    });
})(jQuery);
