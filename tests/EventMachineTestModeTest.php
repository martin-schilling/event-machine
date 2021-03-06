<?php
declare(strict_types = 1);

namespace Prooph\EventMachineTest;

use Prooph\EventMachine\Container\EventMachineContainer;
use Prooph\EventMachine\EventMachine;
use ProophExample\Aggregate\CacheableUserDescription;
use ProophExample\Aggregate\UserDescription;
use ProophExample\Messaging\Command;
use ProophExample\Messaging\Event;
use ProophExample\Messaging\MessageDescription;
use Ramsey\Uuid\Uuid;

final class EventMachineTestModeTest extends BasicTestCase
{
    /**
     * @var EventMachine
     */
    private $eventMachine;

    protected function setUp()
    {
        $this->eventMachine = new EventMachine();

        $this->eventMachine->load(MessageDescription::class);
        $this->eventMachine->load(CacheableUserDescription::class);

        $this->eventMachine->initialize(new EventMachineContainer($this->eventMachine));
    }

    protected function tearDown()
    {
        $this->eventMachine = null;
    }

    /**
     * @test
     */
    public function it_uses_in_memory_event_store_bootstraps_with_history_and_provides_recorded_events()
    {
        $userId = Uuid::uuid4()->toString();

        $history = [
            $this->eventMachine->messageFactory()->createMessageFromArray(
                Event::USER_WAS_REGISTERED,
                [
                    'payload' => [
                        UserDescription::IDENTIFIER => $userId,
                        UserDescription::USERNAME => 'Alex',
                        UserDescription::EMAIL => 'contact@prooph.de',
                    ]
                ]
            ),
        ];

        $this->eventMachine->bootstrapInTestMode($history);

        $changeUsername = $this->eventMachine->messageFactory()->createMessageFromArray(
            Command::CHANGE_USERNAME,
            [
                'payload' => [
                    UserDescription::IDENTIFIER => $userId,
                    UserDescription::USERNAME => 'codeliner',
                ]
            ]
        );

        $this->eventMachine->dispatch($changeUsername);

        $recordedEvents = $this->eventMachine->popRecordedEventsOfTestSession();

        self::assertCount(1, $recordedEvents);
    }
}
