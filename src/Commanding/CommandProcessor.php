<?php
declare(strict_types = 1);

namespace Prooph\EventMachine\Commanding;

use Prooph\Common\Messaging\MessageFactory;
use Prooph\EventMachine\Aggregate\ClosureAggregateTranslator;
use Prooph\EventMachine\Aggregate\Exception\AggregateNotFound;
use Prooph\EventMachine\Aggregate\GenericAggregateRoot;
use Prooph\EventMachine\Eventing\GenericJsonSchemaEvent;
use Prooph\EventSourcing\Aggregate\AggregateRepository;
use Prooph\EventSourcing\Aggregate\AggregateType;
use Prooph\EventStore\EventStore;
use Prooph\SnapshotStore\SnapshotStore;

final class CommandProcessor
{
    /**
     * @var string
     */
    private $commandName;

    /**
     * @var string
     */
    private $aggregateType;

    /**
     * @var string
     */
    private $aggregateIdentifier;

    /**
     * @var bool
     */
    private $createAggregate;

    /**
     * @var EventStore
     */
    private $eventStore;

    /**
     * @var array
     */
    private $eventRecorderMap;

    /**
     * @var array
     */
    private $eventApplyMap;

    /**
     * @var callable
     */
    private $aggregateFunction;

    /**
     * @var MessageFactory
     */
    private $messageFactory;

    /**
     * @var SnapshotStore
     */
    private $snapshotStore;

    /**
     * @var AggregateRepository
     */
    private $aggregateRepository;

    public static function fromDescriptionArrayAndDependencies(array $description, MessageFactory $messageFactory, EventStore $eventStore, SnapshotStore $snapshotStore = null): self
    {
        if(!array_key_exists('commandName', $description)) {
            throw new \InvalidArgumentException("Missing key commandName in commandProcessorDescription");
        }

        if(!array_key_exists('createAggregate', $description)) {
            throw new \InvalidArgumentException("Missing key createAggregate in commandProcessorDescription");
        }

        if(!array_key_exists('aggregateType', $description)) {
            throw new \InvalidArgumentException("Missing key aggregateType in commandProcessorDescription");
        }

        if(!array_key_exists('aggregateIdentifier', $description)) {
            throw new \InvalidArgumentException("Missing key aggregateIdentifier in commandProcessorDescription");
        }

        if(!array_key_exists('aggregateFunction', $description)) {
            throw new \InvalidArgumentException("Missing key aggregateFunction in commandProcessorDescription");
        }

        if(!array_key_exists('eventRecorderMap', $description)) {
            throw new \InvalidArgumentException("Missing key eventRecorderMap in commandProcessorDescription");
        }

        if(!array_key_exists('eventApplyMap', $description)) {
            throw new \InvalidArgumentException("Missing key eventApplyMap in commandProcessorDescription");
        }

        return new self(
            $description['commandName'],
            $description['aggregateType'],
            $description['createAggregate'],
            $description['aggregateIdentifier'],
            $description['aggregateFunction'],
            $description['eventRecorderMap'],
            $description['eventApplyMap'],
            $messageFactory,
            $eventStore,
            $snapshotStore
        );
    }

    public function __construct(
        string $commandName,
        string $aggregateType,
        bool $createAggregate,
        string $aggregateIdentifier,
        callable $aggregateFunction,
        array $eventRecorderMap,
        array $eventApplyMap,
        MessageFactory $messageFactory,
        EventStore $eventStore,
        SnapshotStore $snapshotStore = null
    ) {
        $this->commandName = $commandName;
        $this->aggregateType = $aggregateType;
        $this->aggregateIdentifier = $aggregateIdentifier;
        $this->createAggregate = $createAggregate;
        $this->aggregateFunction = $aggregateFunction;
        $this->eventRecorderMap = $eventRecorderMap;
        $this->eventApplyMap = $eventApplyMap;
        $this->messageFactory = $messageFactory;
        $this->eventStore = $eventStore;
        $this->snapshotStore = $snapshotStore;
    }

    public function __invoke(GenericJsonSchemaCommand $command)
    {
        if($command->messageName() !== $this->commandName) {
            throw  new \RuntimeException('Wrong routing detected. Command processor is responsible for '
                . $this->commandName . ' but command '
                . $command->messageName() . ' received.');
        }

        $payload = $command->payload();

        if(!array_key_exists($this->aggregateIdentifier, $payload)) {
            throw new \RuntimeException(sprintf(
                "Missing aggregate identifier %s in payload of command %s",
                $this->aggregateIdentifier,
                $this->commandName
            ));
        }

        $arId = (string)$payload[$this->aggregateIdentifier];
        $arRepository = $this->getAggregateRepository($arId);
        $arFuncArgs = [];

        if($this->createAggregate) {
            $aggregate = new GenericAggregateRoot($arId, AggregateType::fromString($this->aggregateType), $this->eventApplyMap);
            $arFuncArgs[] = $command;
        } else {
            /** @var GenericAggregateRoot $aggregate */
            $aggregate = $arRepository->getAggregateRoot($arId);

            if(!$aggregate) {
                throw AggregateNotFound::with($this->aggregateType, $arId);
            }

            $arFuncArgs[] = $aggregate->currentState();
            $arFuncArgs[] = $command;
        }

        $arFunc = $this->aggregateFunction;
        $eventNameList = array_keys($this->eventRecorderMap);

        $eventPayloads = $arFunc(...$arFuncArgs);
        if(!$eventPayloads instanceof \Generator) {
            throw new \InvalidArgumentException(
                'Expected aggregateFunction to be of type Generator. ' .
                'Did you forget the yield keyword in your command handler?'
            );
        }

        foreach ($eventPayloads as $i => $eventPayload) {
            if(!array_key_exists($i, $eventNameList)) {
                throw new \RuntimeException(sprintf(
                    "eventRecorderMap of aggregate type %s and command %s contains too few events.",
                    $this->aggregateType,
                    $this->commandName
                ));
            }

            /** @var GenericJsonSchemaEvent $event */
            $event = $this->messageFactory->createMessageFromArray($eventNameList[$i], [
                'payload' => $eventPayload,
                'metadata' => [
                    '_causation_id' => $command->uuid()->toString(),
                    '_causation_name' => $this->commandName
                ]
            ]);

            $aggregate->recordThat($event);
        }

        $arRepository->saveAggregateRoot($aggregate);
    }

    private function getAggregateRepository(string $aggregateId): AggregateRepository
    {
        if(null === $this->aggregateRepository) {
            $this->aggregateRepository = new AggregateRepository(
                $this->eventStore,
                AggregateType::fromString($this->aggregateType),
                new ClosureAggregateTranslator($aggregateId, $this->eventApplyMap),
                $this->snapshotStore
            );
        }

        return $this->aggregateRepository;
    }
}
