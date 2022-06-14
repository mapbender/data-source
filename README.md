# Mapbender data source module

Components abstracting raw doctrine tables into repositories.

The base DataStore is a repository promoting loaded rows
into DataItem objects, and also supports DataItem objects when
updating / inserting.

FeatureType is an extended repository type with spatial data support.
It loads / stores Feature objects, with dedicated methods
for accessing / replacing the geometry. The geometry is internally
always an EWKT, but methods are provided to get / update the plain WKT
or the SRID individually.

Only a single geometry column per table is supported.

## Main repository methods
Method `search` loads all rows from the table and promotes them into an array of DataItem / Feature objects.
Accepts an array of controlling parameters. Allowed params are `maxResults`, `where` (string; additional
SQL where clause). FeatureType additionally supports params `srid` (explicit geometry output SRID) and `intersect`
(string; (E)WKT geometry to spatially limit results).

Method `count` Accepts the same parameters as search, but returns only the number of matched rows.

Method `getById` loads one DataItem / Feature. Filters are skipped.

Methods `save`, `insert`, `update` perform storage. `save` auto-inflects to insert or update, depending on
the presence of an id in the passed argument. These methods accept either DataItem / Feature objects or arrays.
The affected or new row is reloaded and returned as a DataItem / Feature object.

Method `delete` removes the row corresponding with an id or a specific DataItem / Feature object.

## Configuring repositories

Named DataStore / FeatureType repositorys can be pre-configured
globally via container parameters (`dataStores` and `featureTypes` respectively),
or instantiated ad hoc from a configuration array.

DataStore configuration supports the following values:

| name | type | description | default |
|---|---|---|---|
| connection | string | name of doctrine connection | "default" |
| table | string | name of table; required | -none- |
| uniqueId | string | name of primary id column | "id" |
| fields | list of strings | names of columns to load into DataItem / Feature objects | null (=auto-detect columns) |
| filter | string | valid SQL where clause to build into all `search` invocations | null |
| geomField (FeatureType only) | string | name of the geometry column | "geom" |
| srid (FeatureType only) | integer or null | Source srid of `geomField`; used only if detection fails (certain views) | null |

NOTE: you should _not_ attempt placing spatial data into the "default" database containing
your Doctrine entities. You _will_ encounter errors running Doctrine schema updates.

NOTE: Both the `filter` setting and the `where` search param may use a magic placeholder `:userName`, which is
automatically bound to the name of the user running the query.
