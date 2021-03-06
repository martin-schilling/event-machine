<?php
declare(strict_types = 1);

namespace Prooph\EventMachine\Commanding;

use Prooph\Common\Event\ActionEvent;
use Prooph\Common\Messaging\MessageFactory;
use Prooph\EventStore\EventStore;
use Prooph\ServiceBus\MessageBus;
use Prooph\ServiceBus\Plugin\AbstractPlugin;
use Prooph\SnapshotStore\SnapshotStore;

final class CommandToProcessorRouter extends AbstractPlugin
{
    /**
     * Map with command name being the key and CommandProcessorDescription the value
     *
     * @var array
     */
    private $routingMap;

    /**
     * @var array
     */
    private $aggregateDescriptions;

    /**
     * @var MessageFactory
     */
    private $messageFactory;

    /**
     * @var EventStore
     */
    private $eventStore;

    /**
     * @var SnapshotStore|null
     */
    private $snapshotStore;

    public function __construct(
        array $routingMap,
        array $aggregateDescriptions,
        MessageFactory $messageFactory,
        EventStore $eventStore,
        SnapshotStore $snapshotStore = null
    ) {
        $this->routingMap = $routingMap;
        $this->aggregateDescriptions = $aggregateDescriptions;
        $this->messageFactory = $messageFactory;
        $this->eventStore = $eventStore;
        $this->snapshotStore = $snapshotStore;
    }

    public function attachToMessageBus(MessageBus $messageBus): void
    {
        $this->listenerHandlers[] = $messageBus->attach(
            MessageBus::EVENT_DISPATCH,
            [$this, 'onRouteMessage'],
            MessageBus::PRIORITY_ROUTE
        );
    }

    public function onRouteMessage(ActionEvent $actionEvent): void
    {
        $messageName = (string) $actionEvent->getParam(MessageBus::EVENT_PARAM_MESSAGE_NAME);

        if (empty($messageName)) {
            return;
        }

        if (! isset($this->routingMap[$messageName])) {
            return;
        }

        $processorDesc = $this->routingMap[$messageName];

        $aggregateDesc = $this->aggregateDescriptions[$processorDesc['aggregateType'] ?? ''] ?? [];

        if(!isset($aggregateDesc['eventApplyMap'])) {
            throw new \RuntimeException("Missing eventApplyMap for aggregate type: " . $processorDesc['aggregateType'] ?? '');
        }

        $processorDesc['eventApplyMap'] = $aggregateDesc['eventApplyMap'];

        $commandProcessor = CommandProcessor::fromDescriptionArrayAndDependencies(
            $processorDesc,
            $this->messageFactory,
            $this->eventStore,
            $this->snapshotStore
        );

        $actionEvent->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLER, $commandProcessor);
    }
}
