(function (window) {
    var Mapbender = window.Mapbender || {};

    var DatabaseHealthCheck = window.DatabaseHealthCheck = function () {
        this.getHealthStatus()
            .done(this.generateTableHeaders.bind(this))
            .done(this.initDataTable.bind(this))
            .done(this.updateHealhStatusTable.bind(this))

        ;

    };

    var proto = DatabaseHealthCheck.prototype;

    proto.getHealthStatus = function () {
        return $.ajax("getHealth");
    };

    proto.initDataTable = function () {

        this.getHealtStatusTable().dataTable({
            "createdRow": function (row,data) {

                    data[2] !== '' ? $(row).addClass('bg-danger') :  $(row).addClass('bg-success')

            }
        });
    };

    proto.parseHealthStatusResponse = function (response) {
        var dataSets = [];
        _.each(response, function (connection) {
            this.getHealtStatusTable().DataTable().row.add(this.getDataset(connection)).draw( false );
        }.bind(this));
        //return dataSets;
    };

    proto.updateHealhStatusTable = function (response) {
        this.parseHealthStatusResponse(response.connectionHealth)
    };

    proto.getHealtStatusTable = function () {
        return $("#-fn-healthstatus-table")
    };

    proto.getDataset = function (connection) {
        var dataSet = [];
        _.each(connection, function (value,key) {
            dataSet.push(value);
        });
        return dataSet;
    };

    proto.generateTableHeaders = function (response) {
        var connection = response.connectionHealth[0];
        var headerNames = Object.keys(connection);
        var $headerRow = this.getHealtStatusTable().find("thead > tr");
        _.each(headerNames, function (name) {
            var $head = $("<td/>").text(name);
            $($headerRow).append($head);
        });

    };
})(window);

new DatabaseHealthCheck();


