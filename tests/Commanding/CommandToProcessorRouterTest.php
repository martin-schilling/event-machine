<?php
declare(strict_types = 1);

namespace Prooph\EventMachineTest\Commanding;

use Prooph\Common\Event\ProophActionEventEmitter;
use Prooph\Common\Messaging\MessageFactory;
use Prooph\EventMachine\Commanding\CommandProcessor;
use Prooph\EventMachine\Commanding\CommandToProcessorRouter;
use Prooph\EventMachineTest\BasicTestCase;
use Prooph\EventStore\EventStore;
use Prooph\ServiceBus\MessageBus;
use Prooph\SnapshotStore\SnapshotStore;

final class CommandToProcessorRouterTest extends BasicTestCase
{
    /**
     * @test
     */
    public function it_sets_command_processor_as_command_handler()
    {
        $commandMap = [
            'TestCommand' => [
                'commandName' => 'TestCommand',
                'aggregateType' => 'User',
                'createAggregate' => true,
                'aggregateIdentifier' => 'id',
                'aggregateFunction' => function() {},
                'eventRecorderMap' => []
            ]
        ];

        $aggregateDescriptions = [
            'User' => [
                'eventApplyMap' => [
                    'UserWasRegistered' => function() {}
                ],
            ]
        ];

        $messageFactory = $this->prophesize(MessageFactory::class);
        $eventStore = $this->prophesize(EventStore::class);
        $snapshotStore = $this->prophesize(SnapshotStore::class);

        $router = new CommandToProcessorRouter(
            $commandMap,
            $aggregateDescriptions,
            $messageFactory->reveal(),
            $eventStore->reveal(),
            $snapshotStore->reveal()
        );

        $actionEvent = (new ProophActionEventEmitter())->getNewActionEvent(MessageBus::EVENT_DISPATCH);

        $actionEvent->setParam(MessageBus::EVENT_PARAM_MESSAGE_NAME, 'TestCommand');

        $router->onRouteMessage($actionEvent);

        /** @var CommandProcessor $commandProcessor */
        $commandProcessor = $actionEvent->getParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLER);

        self::assertInstanceOf(CommandProcessor::class, $commandProcessor);
    }
}
