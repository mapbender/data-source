## 0.1.16??
- Fix Feature initialization from GeoJSON: respect configured `geomField`, apply optional non-standard embedded `srid` and `id` correctly
- Fix broken data format in Oracle::prepareResults
- Support `:userName` filter binding also in DataStore::search (previously only in FeatureType::search)
- Escape `:userName` properly in FeatureType::search and DataStore::search
- Escape `:distance` in FeatureType::search (now a bound param)
- Extract FeatureType / DataStore method `addCustomSearchCritera` method for customization support
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

