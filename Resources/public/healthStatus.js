(function (window) {
    var Mapbender = window.Mapbender || {};

    Mapbender.DatabaseHealthCheck = function () {
        this.getHealthStatus()
            .then(this.generateTableHeaders(response[0]).bind(this))
            .then(this.updateHealhStatusTable(response).bind(this))
            .then(this.initDataTable())
        ;

    };

    var proto = Mapbender.DatabaseHealthCheck.prototype;

    proto.getHealthStatus = function () {
        return $.ajax("getHealtStatus");
    };

    proto.initDataTable = function () {

        this.getHealtStatusTable().DataTable();
    };

    proto.parseHealthStatusResponse = function (response) {
        var dataSets = [];
        _.each(response, function (connection) {
            dataSets.push(this.getDataset(connection));
        }.bind(this));
        return dataSets;
    };

    proto.updateHealhStatusTable = function () {

    };

    proto.getHealtStatusTable = function () {
        return $("#-fn-healthstatus-table")
    };

    proto.getDataset = function (connection) {
        var dataSet = [];
        _.each(connection, function (connectionAttribute, value) {
            dataSet.push(value);
        });
        return dataSet;
    };

    proto.generateTableHeaders = function (connection) {
        var headerNames = Object.keys(connection);
        var $headerRow = this.getHealtStatusTable().find("thead > tr");
        _.each(headerNames, function (name) {
            var $head = $("<td/>").text(name);
            $($headerRow).append($head);
        });

    };
})(window);

new Mapbender.DatabaseHealthCheck();
