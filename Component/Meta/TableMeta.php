<?php


namespace Mapbender\DataSourceBundle\Component\Meta;


use Doctrine\DBAL\Platforms\AbstractPlatform;

class TableMeta
{
    /** @var AbstractPlatform */
    protected $platform;
    /** @var Column[] */
    protected $columns = array();

    /**
     * @param AbstractPlatform $platform
     * @param Column[] $columns
     */
    public function __construct(AbstractPlatform $platform, array $columns)
    {
        $this->platform = $platform;
        foreach ($columns as $name => $column) {
            $resultName = $platform->getSQLResultCasing($name);
            $this->columns[$resultName] = $column;
        }
        $this->columns = $columns;
    }

    /**
     * @return string[]
     */
    public function getColumNames()
    {
        return \array_keys($this->columns);
    }

    public function prepareUpdateData(array $data)
    {
        foreach ($data as $columnName => $value) {
            if (\is_string($value) && !$value) {
                $column = $this->getColumn($columnName);
                if ($column->isNumeric()) {
                    $data[$columnName] = $column->getSafeDefault();
                }
            }
        }
        return $data;
    }

    public function prepareInsertData(array $data)
    {
        $data = $this->prepareUpdateData($data);
        $dataNames = array();
        foreach (array_keys($data) as $dataKey) {
            $dataNames[] = $this->platform->getSQLResultCasing($dataKey);
        }
        foreach (\array_keys($this->columns) as $columnName) {
            $column = $this->getColumn($columnName);
            if (!$column->hasDefault()) {
                if (!\in_array($columnName, $dataNames, true)) {
                    $data[$columnName] = $column->getSafeDefault();
                }
            }
        }
        return $data;
    }

    /**
     * @param string $name
     * @return Column
     * @throws \RuntimeException
     */
    public function getColumn($name)
    {
        $resultName = $this->platform->getSQLResultCasing($name);
        if (\array_key_exists($resultName, $this->columns)) {
            return $this->columns[$resultName];
        }
        throw new \RuntimeException("Unknown column {$name}");
    }
}
