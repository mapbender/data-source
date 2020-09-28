## 0.1.17
- Fix DataStore-only errors on updates with reserved words as column names (e.g. PostgreSQL "public")
- Fix SELECT queries with reserved words as column names (e.g. PostgreSQL "public")
- Fix UPDATEs and INSERTs writing values to type BOOLEAN columns on PostgreSQL
- Fix inability to write NULL into nullable columns with non-null defaults on INSERT on PostgreSQL
- Added misc BaseElement child class customization support methods
  * `getDataStoreKeyInSchemaConfig`
  * `getDataStoreKeyInFormItemConfig`

## 0.1.16.2
- Fix Oracle bounding-box intersection query ([PR#14](https://github.com/mapbender/data-source/pull/14))
- Fix DataStore empty item initialization

## 0.1.16.1
- Fix DataStore getById
- Fix error handling when saving

## 0.1.16
- Fix Feature initialization from GeoJSON: respect configured `geomField`, apply optional non-standard embedded `srid` and `id` correctly
- Fix broken data format in Oracle::prepareResults
- Fix exception on table miss in DataStore::getById, return null instead
- Change FeatureType::getById return value on table miss from `false` to `null`
- Support `:userName` filter binding also in DataStore::search (previously only in FeatureType::search)
- Escape `:userName` properly in FeatureType::search and DataStore::search
- Escape `:distance` in FeatureType::search (now a bound param)
- Extract FeatureType / DataStore method `addCustomSearchCritera` method for customization support
- Add DataStoreService::getDbalConnectionByName method
- `getUniqueId` and `getTablename` methods are now also available on DataStore object (previously only FeatureType)
- Deprecate DataItem construction with a (jsonish) string
- Deprecate magic Feature::__toString invocation
- Make tests with missing prerequisites fail instead of skip

## 0.1.15
- Fix broken select item options when combining static options with `sql`-path options
- Customization support: extracted methods from `BaseItem::prepareItem`
  - prepareSelectItem
  - formatSelectItemOption
  - formatStaticSelectItemOptions
  - prepareSqlSelectItem
  - formatSqlSelectItemOption
  - prepareDataStoreSelectItem
  - prepareServiceSelectItem
  - getDataStoreService
  - getDbalConnectionByName
- Disambiguate `DataStoreService::get` and `FeatureTypeService::get` by adding `getDataStoreByName` and `getFeatureTypeByName` methods
  - Extract factory methods for customization support
- Remove invalid default service id `default` from dataStore select item path; `serviceName` is no longer optional
- Log warning on redundant combination of "dataStore" / "sql" / "service" select item configuration
- Emit more specific errors for missing / type mismatched driver configuration values

