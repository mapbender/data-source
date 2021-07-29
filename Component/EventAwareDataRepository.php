<?php


namespace Mapbender\DataSourceBundle\Component;


use Doctrine\DBAL\Connection;
use Mapbender\DataSourceBundle\Entity\DataItem;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

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
                                TokenStorageInterface $tokenStorage,
                                EventProcessor $eventProcessor,
                                array $eventConfig,
                                $tableName, $idColumnName)
    {
        parent::__construct($connection, $tokenStorage, $tableName, $idColumnName);
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
        $values = $item->toArray();
        if (isset($this->events[self::EVENT_ON_BEFORE_UPDATE]) || isset($this->events[self::EVENT_ON_AFTER_UPDATE])) {
            $eventData = $this->getSaveEventData($item, $values);
        } else {
            $eventData = null;
        }
        if (isset($this->events[self::EVENT_ON_BEFORE_UPDATE])) {
            $this->eventProcessor->runExpression($this->events[self::EVENT_ON_BEFORE_UPDATE], $eventData);
            $runQuery = $this->eventProcessor->allowUpdate;
        } else {
            $runQuery = true;
        }
        if ($runQuery) {
            $values = $this->prepareStoreValues($item, $values);
            $identifier = $this->idToIdentifier($item->getId());
            $values = $this->getTableMetaData()->prepareUpdateData($values);
            $this->getDriver()->update($this->connection, $this->getTableName(), $values, $identifier);
        }
        if (isset($this->events[self::EVENT_ON_AFTER_UPDATE])) {
            $this->eventProcessor->runExpression($this->events[self::EVENT_ON_AFTER_UPDATE], $eventData);
        }
        return $item;
    }

    /**
     * @param DataItem $item
     * @return DataItem
     * @since 0.1.21
     */
    public function insertItem(DataItem $item)
    {
        $values = $item->toArray();
        if (isset($this->events[self::EVENT_ON_BEFORE_INSERT]) || isset($this->events[self::EVENT_ON_AFTER_INSERT])) {
            $eventData = $this->getSaveEventData($item, $values);
        } else {
            $eventData = null;
        }
        if (isset($this->events[self::EVENT_ON_BEFORE_INSERT])) {
            $this->eventProcessor->runExpression($this->events[self::EVENT_ON_BEFORE_INSERT], $eventData);
            $runQuery = $this->eventProcessor->allowUpdate;
        } else {
            $runQuery = true;
        }
        if ($runQuery) {
            $values = $this->prepareStoreValues($item, $values);
            unset($values[$this->uniqueIdFieldName]);
            $values = $this->getTableMetaData()->prepareInsertData($values);
            $id = $this->getDriver()->insert($this->connection, $this->getTableName(), $values, $this->uniqueIdFieldName);
            $item->setId($id);
        }
        if (isset($this->events[self::EVENT_ON_AFTER_INSERT])) {
            $this->eventProcessor->runExpression($this->events[self::EVENT_ON_AFTER_INSERT], $eventData);
        }
        return $item;
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
