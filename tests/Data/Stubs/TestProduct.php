<?php

declare(strict_types = 1);

namespace Prooph\EventMachineTest\Data\Stubs;

use Prooph\EventMachine\Data\ImmutableRecord;
use Prooph\EventMachine\Data\ImmutableRecordLogic;

final class TestProduct implements ImmutableRecord
{
    use ImmutableRecordLogic;

    /**
     * @var int
     */
    protected $productId;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var TestProductPrice
     */
    protected $price;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var array
     */
    private $tags;

    /**
     * @return int
     */
    public function productId(): int
    {
        return $this->productId;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * @return TestProductPrice
     */
    public function price(): TestProductPrice
    {
        return $this->price;
    }

    /**
     * @return bool
     */
    public function active(): bool
    {
        return $this->active;
    }

    /**
     * @return array
     */
    public function tags(): array
    {
        return $this->tags;
    }
}
