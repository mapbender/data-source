<?php


namespace Mapbender\DataSourceBundle\Component;


use Doctrine\DBAL\Connection;

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
}
