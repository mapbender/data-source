## 0.1.16??
- Escape `:userName` and `:distance` properly in FeatureType::search
- Extract `FeatureType::addCustomSearchCritera` method for customization support
- Deprecate DataItem construction with a (jsonish) string
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

