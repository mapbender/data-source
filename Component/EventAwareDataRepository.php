<?php


namespace Mapbender\DataSourceBundle\Component;


use Doctrine\DBAL\Connection;
use Mapbender\DataSourceBundle\Entity\DataItem;

class EventAwareDataRepository extends DataRepository
{
    /**
     * Eval events
     */
    const EVENT_ON_AFTER_SAVE    = 'onAfterSave';
    const EVENT_ON_BEFORE_SAVE   = 'onBeforeSave';
    const EVENT_ON_BEFORE_REMOVE = 'onBeforeRemove';
    const EVENT_ON_AFTER_REMOVE  = 'onAfterRemove';
    const EVENT_ON_BEFORE_SEARCH = 'onBeforeSearch';
    const EVENT_ON_AFTER_SEARCH  = 'onAfterSearch';
    const EVENT_ON_BEFORE_UPDATE = 'onBeforeUpdate';
    const EVENT_ON_AFTER_UPDATE  = 'onAfterUpdate';
    const EVENT_ON_BEFORE_INSERT = 'onBeforeInsert';
    const EVENT_ON_AFTER_INSERT  = 'onAfterInsert';

    /** @var EventProcessor */
    protected $eventProcessor;
    /** @var string[] */
    protected $events = array();

    public function __construct(Connection $connection,
                                EventProcessor $eventProcessor,
                                array $eventConfig,
                                $tableName, $idColumnName)
    {
        parent::__construct($connection, $tableName, $idColumnName);
        $this->eventProcessor = $eventProcessor;
        $this->events = $eventConfig;
    }


    /**
     * @param DataItem $item
     * @return DataItem
     * @since 0.1.17
     */
    public function updateItem(DataItem $item)
    {
        return $this->storeItemInternal($item, false, self::EVENT_ON_BEFORE_UPDATE, self::EVENT_ON_AFTER_UPDATE);
    }

    /**
     * @param DataItem $item
     * @return DataItem
     * @since 0.1.21
     */
    public function insertItem(DataItem $item)
    {
        return $this->storeItemInternal($item, true, self::EVENT_ON_BEFORE_INSERT, self::EVENT_ON_AFTER_INSERT);
    }

    protected function getCommonEventData()
    {
        return array(
            'idKey' => $this->uniqueIdFieldName,
            'connection' => $this->connection,
        );
    }

    /**
     * @param DataItem $item
     * @param bool $isInsert
     * @param string|null $eventNameBefore
     * @param string|null $eventNameAfter
     * @return DataItem
     */
    protected function storeItemInternal(DataItem $item, $isInsert, $eventNameBefore = null, $eventNameAfter = null)
    {
        $values = $this->getSaveData($item);

        if ($eventNameBefore && isset($this->events[$eventNameBefore]) || $eventNameAfter && isset($this->events[$eventNameAfter])) {
            $eventData = $this->getSaveEventData($item, $values);
        } else {
            $eventData = null;
        }

        if (isset($this->events[$eventNameBefore])) {
            $this->eventProcessor->runExpression($this->events[$eventNameBefore], $eventData);
            $runQuery = $isInsert ? $this->eventProcessor->allowInsert : $this->eventProcessor->allowUpdate;
        } else {
            $runQuery = true;
        }

        if ($runQuery) {
            if ($isInsert) {
                $idName = $this->getUniqueId();
                unset($values[$idName]);
                $values = $this->getTableMetaData()->prepareInsertData($values);
                $id = $this->getDriver()->insert($this->connection, $this->getTableName(), $values, $idName);
                $item->setId($id);
            } else {
                $identifier = $this->idToIdentifier($item->getId());
                $values = $this->getTableMetaData()->prepareUpdateData($values);
                $this->getDriver()->update($this->connection, $this->getTableName(), $values, $identifier);
            }
        }

        if ($eventNameAfter && isset($this->events[$eventNameAfter])) {
            $this->eventProcessor->runExpression($this->events[$eventNameAfter], $eventData);
        }

        return $item;
    }

    /**
     * @param DataItem $item
     * @param DataItem|array|mixed $dataArg original value passed to save method
     * @return array
     */
    protected function getSaveEventData(DataItem $item, &$dataArg)
    {
        // legacy quirk originData:
        // 1) for inserts (no id), provide a blank, empty, DataItem object (like ->get(array()))
        // 2) for updates, reload the original item (incoming item already carries new data!)
        if ($item->getId()) {
            $originData = $this->reloadItem($item);
        } else {
            $originData = $this->itemFactory();
        }

        return $this->getCommonEventData() + array(
            'item' => &$dataArg,
            'feature' => $item,
            'originData' => $originData,
        );
    }
}
