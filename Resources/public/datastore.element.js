(function($) {
    var widget, element;
    var frames = [];
    var titleElement;
    var selector;
    var options;
    var hasOnlyOneScheme;
    var activeSchema;
    /**
     * Regular Expression to get checked if string should be translated
     *
     * @type {RegExp}
     */
    var translationReg = /^trans:\w+\.(\w|-|\.{1}\w+)+\w+$/;

    /**
     * Translate digitizer keywords
     * @param title
     * @returns {*}
     */
    function translate(title, withoutSuffix) {
        return Mapbender.trans(withoutSuffix ? title : "mb.digitizer." + title);
    }

    /**
     * Translate object
     *
     * @param items
     * @returns object
     */
    function translateObject(items) {
        for (var k in items) {
            var item = items[k];
            if(typeof item === "string" && item.match(translationReg)) {
                items[k] = translate(item.split(':')[1], true);
            } else if(typeof item === "object") {
                translateObject(item);
            }
        }
        return item;
    }

    /**
     * Check and replace values recursive if they should be translated.
     * For checking used "translationReg" variable
     *
     *
     * @param items
     */
    function translateStructure(items) {
        var isArray = items instanceof Array;
        for (var k in items) {
            if(isArray || k == "children") {
                translateStructure(items[k]);
            } else {
                if(typeof items[k] == "string" && items[k].match(translationReg)) {
                    items[k] = translate(items[k].split(':')[1], true);
                }
            }
        }
    }
    /**
     * @param options
     * @returns {*}
     */
    confirmDialog = function (options) {
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
                text:    "Abbrechen",
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
    };


    /**
     * Digitizing tool set
     *
     * @author Andriy Oblivantsev <eslider@gmail.com>
     * @author Stefan Winkelmann <stefan.winkelmann@wheregroup.com>
     *
     * @copyright 20.04.2015 by WhereGroup GmbH & Co. KG
     */
    $.widget("mapbender.mbDataStore", {
        options: {
            allowCreate:     true,
            allowEditData:   true,
            allowDelete:     true,
            maxResults:      5001,
            oneInstanceEdit: true,
            inlineSearch:    false,
            useContextMenu:  false,
            dataStore:       "default",
            tableFields:     [],
            newItems:        []
        }, 
        toolsets: {
            point: [
              {type: 'drawPoint'},
            ],
            line: [
              {type: 'removeSelected'}
              //{type: 'removeAll'}
            ],
            polygon: [
                {type: 'removeAll'}
            ]
        },
        currentSettings: null,
        featureEditDialogWidth: "423px",

        /**
         * Constructor.
         *
         * At this moment not all elements (like a OpenLayers) are avaible.
         *
         * @private
         */
        _create: function() {
            widget = this;
            element = widget.element;
            widget.elementUrl = Mapbender.configuration.application.urls.element + '/' + element.attr('id') + '/';
            titleElement = $("> div.title", element);
            selector = widget.selector = $("select.selector", element);
            options = widget.options;
            hasOnlyOneScheme = _.size(options.schemes) === 1;

            if(hasOnlyOneScheme) {
                var title = _.propertyOf(_.first(_.toArray(options.schemes)))("label");
                if(title) {
                    titleElement.html(title);
                } else {
                    titleElement.css('display', 'none');
                }
                selector.css('display', 'none');
            } else {
                titleElement.css('display', 'none');
            }

            if(options.tableTranslation) {
                translateObject(options.tableTranslation);
            }

            // build select options
            _.each(options.schemes, function(schema, schemaName) {
                var buttons = [];
                var option = $("<option/>");
                var frame = schema.frame = $("<div/>")
                    .addClass('frame')
                    .data("schema", schema);

                schema.schemaName = schemaName;

                // Merge settings with default values from options there are not set by backend configuration
                _.extend(schema, _.omit(options, _.keys(schema)));

                buttons.push({
                    title:     translate('feature.edit'),
                    className: 'fa-edit',
                    onClick:   function(dataItem, ui) {
                        widget._openEditDialog(dataItem);
                    }
                });

                if(schema.allowDelete) {
                    buttons.push({
                        title:     translate("feature.remove"),
                        className: 'fa-times',
                        cssClass:  'critical',
                        onClick:   function(dataItem, ui) {
                            widget.removeData(dataItem);
                        }
                    });
                }

                option.val(schemaName).html(schema.label);


                if( !schema.hasOwnProperty("tableFields")){
                    console.error(translate("table.fields.not.defined"),schema );
                }

                //_.each(schema.tableFields, function(fieldSettings, fieldName) {
                //    fieldSettings.title = fieldSettings.label;
                //    fieldSettings.data = fieldName;
                //    columns.push(fieldSettings);
                //});

                var resultTableSettings = _.extend({
                    lengthChange: false,
                    pageLength:   20,
                    searching:    true,
                    info:         true,
                    processing:   false,
                    ordering:     true,
                    paging:       true,
                    selectable:   false,
                    autoWidth:    false
                }, schema.table);

                // Merge buttons
                resultTableSettings.buttons = resultTableSettings.buttons ? _.flatten(buttons, resultTableSettings.buttons) : buttons;

                if(options.tableTranslation) {
                    resultTableSettings.oLanguage = options.tableTranslation;
                }

                var table = schema.table = $("<div/>").resultTable(resultTableSettings);
                var tableWidget = table.data('visUiJsResultTable');
                schema.schemaName = schemaName;

                var toolBarButtons = [];

                if(schema.allowCreate){
                    toolBarButtons.push({
                        type:  "button",
                        title: "Create",
                        click: function() {
                            console.log("Create");
                        }
                    })
                }

                frame.generateElements({
                    children: [{
                        type:     'fieldSet',
                        children: toolBarButtons
                    }]
                });
                frame.append(table);
                frames.push(frame);

                frame.css('display','none');

                element.append(frame);
                option.data("schema", schema);
                selector.append(option);

            });

            function deactivateFrame(schema) {
                var frame = schema.frame;
                frame.css('display', 'none');
                if(widget.currentPopup){
                    widget.currentPopup.popupDialog('close');
                }
                //tableApi.clear();
            }

            function activateFrame(schema) {
                var frame = schema.frame;
                activeSchema = widget.currentSettings = schema;
                frame.css('display', 'block');
            }

            function onSelectorChange() {
                var option = selector.find(":selected");
                var schema = option.data("schema");
                var table = schema.table;
                var tableApi = table.resultTable('getApi');

                if(widget.currentSettings) {
                    deactivateFrame(widget.currentSettings);
                }

                activateFrame(schema);

                table.off('mouseenter', 'mouseleave', 'click');
                table.delegate("tbody > tr", 'mouseenter', function() {
                    var tr = this;
                    var row = tableApi.row(tr);
                });
                table.delegate("tbody > tr", 'mouseleave', function() {
                    var tr = this;
                    var row = tableApi.row(tr);
                });
                table.delegate("tbody > tr", 'click', function() {
                    var tr = this;
                    var row = tableApi.row(tr);
                });

                widget._getData(schema);
            }

            selector.on('change',onSelectorChange);

            widget._trigger('ready');

            onSelectorChange();
        },

        /**
         * Open edit feature dialog
         *
         * @param dataItem open layer feature
         * @private
         */
        _openEditDialog: function(dataItem) {
            var schema = widget.findSchemaByDataItem(dataItem);
            var table = schema.table;
            var tableApi = table.resultTable('getApi');
            var buttons = [];

            if(widget.currentPopup) {
                widget.currentPopup.popupDialog('close');
            }

            if(schema.popup && schema.popup.buttons) {
                $.each(schema.popup.buttons, function(k, button){
                    $.each(button, function(k, property) {
                        if(k == "click") {
                            button[k] = function() {
                                var form = $(this).closest(".ui-dialog-content");
                                var data = form.formData();
                                eval(property);
                            }
                        }
                        if(k == "title") {
                            button[k] = translate(property, false);
                        }
                    });
                    buttons.push(button)
                });
            }

            if(schema.allowEditData){
                var saveButton = {
                    text:  translate("feature.save"),
                    click: function() {
                        var form = $(this).closest(".ui-dialog-content");
                        var formData = form.formData();
                        var request = {dataItem: formData};

                        _.extend(dataItem.data, formData);

                        tableApi.draw({"paging": "page"});

                        var errorInputs = $(".has-error", dialog);
                        var hasErrors = errorInputs.size() > 0;

                        if( !hasErrors ){
                            form.disableForm();
                            widget.query('save', {
                                schema:  widget.schemaName,
                                feature: request
                            }).done(function(response) {

                                if(response.hasOwnProperty('errors')) {
                                    form.enableForm();
                                    $.each(response.errors, function(i, error) {
                                        $.notify(error.message, {
                                            title:     'API Error',
                                            autoHide:  false,
                                            className: 'error'
                                        });
                                        console.error(error.message);
                                    });
                                    return;
                                }

                                var hasFeatureAfterSave = response.features.length > 0;

                                if(!hasFeatureAfterSave) {
                                    widget.reloadFeatures( schema.layer, _.without(schema.layer.features, dataItem));
                                    widget.currentPopup.popupDialog('close');
                                    return;
                                }

                                var dbFeature = response.features[0];
                                dataItem.fid = dbFeature.id;
                                dataItem.state = null;
                                $.extend(dataItem.data, dbFeature.properties);

                                tableApi.draw();

                                delete dataItem.isNew;

                                form.enableForm();
                                widget.currentPopup.popupDialog('close');
                                $.notify(translate("feature.save.successfully"), 'info');

                            });
                        }
                    }
                };
                buttons.push(saveButton);
            }
            if(schema.allowDelete) {
                buttons.push({
                    text:  translate("feature.remove"),
                    'class': 'critical',
                    click: function() {
                        widget.removeData(dataItem);
                        widget.currentPopup.popupDialog('close');
                    }
                });
            }
            buttons.push({
                text:  translate("mb.digitizer.cancel",true),
                click: function() {
                    widget.currentPopup.popupDialog('close');
                }
            });
            var popupConfiguration = {
                title: translate("feature.attributes"),
                width: widget.featureEditDialogWidth,
            };

            if(schema.popup) {
                $.extend(popupConfiguration, schema.popup);
                popupConfiguration.buttons = buttons;
            }

            var dialog = $("<div/>");
            dialog.on("popupdialogopen", function(event, ui) {
                setTimeout(function() {
                    dialog.formData(dataItem);
                }, 1);
            });

            if(!schema.elementsTranslated) {
                translateStructure(widget.currentSettings.formItems);
                schema.elementsTranslated = true;
            }

            DataUtil.eachItem(widget.currentSettings.formItems, function(item) {
                if(item.type == "file") {
                    item.uploadHanderUrl = widget.elementUrl + "file-upload?schema=" + schema.schemaName + "&fid=" + dataItem.fid + "&field=" + item.name;
                    if(item.hasOwnProperty("name") && dataItem.data.hasOwnProperty(item.name) && dataItem.data[item.name]) {
                        item.dbSrc = dataItem.data[item.name];
                        if(schema.featureType.files) {
                            $.each(schema.featureType.files, function(k, fileInfo) {
                                if(fileInfo.field && fileInfo.field == item.name) {
                                    if(fileInfo.formats) {
                                        item.accept = fileInfo.formats;
                                    }
                                }
                            });
                        }
                    }

                }

                if(item.type == 'image') {

                    if(!item.origSrc) {
                        item.origSrc = item.src;
                    }

                    if(item.hasOwnProperty("name") && dataItem.data.hasOwnProperty(item.name) && dataItem.data[item.name]) {
                        item.dbSrc = dataItem.data[item.name];
                        if(schema.featureType.files) {
                            $.each(schema.featureType.files, function(k, fileInfo) {
                                if(fileInfo.field && fileInfo.field == item.name) {

                                    if(fileInfo.uri) {
                                        item.dbSrc = fileInfo.uri + "/" + item.dbSrc;
                                    } else {
                                    }
                                }
                            });
                        }
                    }

                    var src = item.dbSrc ? item.dbSrc : item.origSrc;
                    if(item.relative) {
                        item.src = src.match(/^(http[s]?\:|\/{2})/) ? src : Mapbender.configuration.application.urls.asset + src;
                    } else {
                        item.src = src;
                    }
                }
            });

            dialog.generateElements({children: widget.currentSettings.formItems});
            dialog.popupDialog(popupConfiguration);
            widget.currentPopup = dialog;

            return dialog;
        },

        /**
         * Analyse changed bounding box geometrie and load features as FeatureCollection.
         *
         * @private
         */
        _getData: function(schema) {
            var tableApi = schema.table.resultTable('getApi');
            return widget.query('select', {
                maxResults: schema.maxResults,
                schema:     schema.schemaName
            }).done(function(dataItems) {
                schema.dataItems = dataItems;
                widget.reloadData(schema);
            });
        },

        /**
         * Find schema definition by dataItem
         *
         * @param dataItem
         */
        findSchemaByDataItem: function(dataItem) {
            var r;
            _.each(options.schemes, function(schema) {
                if(_.indexOf(schema.dataItems, dataItem) > -1) {
                    r = schema;
                    return;
                }
            });
            return r;
        },


        /**
         * Remove OL feature
         *
         * @param feature
         * @version 0.2
         * @returns {*}
         */
        removeData: function(dataItem) {
            var schema = widget.findSchemaByDataItem(dataItem);
            var isNew = dataItem.hasOwnProperty('isNew');
            var featureData = dataItem;

            if(!schema) {
                $.notify("Remove failed.", "error");
                return;
            }

            function _removeDataFromUI() {
                //var existingFeatures = schema.isClustered ? _.flatten(_.pluck(layer.features, "cluster")) : layer.features;
                widget.reloadFeatures( _.without(existingFeatures, dataItem));


                widget._trigger('featureRemoved', null, {
                    schema:  schema,
                    feature: featureData
                });
            }

            if(isNew) {
                _removeDataFromUI()
            } else {
                confirmDialog({
                    html:      translate("feature.remove.from.database"),
                    onSuccess: function() {
                        widget.query('delete', {
                            schema:  schema.schemaName,
                            feature: featureData
                        }).done(function(fid) {
                            _removeDataFromUI();
                            $.notify(translate('feature.remove.successfully'), 'info');
                        });
                    }
                });
            }

            return dataItem;
        },

        reloadData: function(schema) {
            var tableApi = schema.table.resultTable('getApi');
            tableApi.clear();
            tableApi.rows.add(schema.dataItems);
            tableApi.draw();
        },

        /**
         * Query API
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
                var errorMessage = translate('api.query.error-message');
                $.notify(errorMessage + JSON.stringify(xhr.responseText));
                console.log(errorMessage, xhr);
            });
        }
    });

})(jQuery);
