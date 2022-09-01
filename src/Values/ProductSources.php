<?php

namespace Armezit\GetCandy\VirtualProduct\Values;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use Livewire\Wireable;
use Spatie\DataTransferObject\Attributes\CastWith;
use Spatie\DataTransferObject\Casters\ArrayCaster;
use Spatie\DataTransferObject\DataTransferObject;

class ProductSources extends DataTransferObject implements Wireable, ArrayAccess, IteratorAggregate
{
    /**
     * @var Source[]
     */
    #[CastWith(ArrayCaster::class, itemType: Source::class)]
    public array $sources;

    public function toLivewire()
    {
        return collect($this->sources)
            ->map(fn (Source $source) => $source->toLivewire())
            ->toArray();
    }

    public static function fromLivewire($value)
    {
        return new static(
            sources: collect($value)
                ->map(fn (array $args) => new Source($args['class'], $args['enabled'], $args['data']))
                ->toArray(),
        );
    }

    public function setSourceData(string $class, array $data)
    {
        foreach ($this->sources as $source) {
            if ($source->class === $class) {
                $source->data = $data;
                break;
            }
        }
    }

    public function offsetExists(mixed $offset)
    {
        return isset($this->sources[$offset]);
    }

    public function offsetGet(mixed $offset)
    {
        return $this->sources[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value)
    {
        if (is_null($offset)) {
            $this->sources[] = $value;
        } else {
            $this->sources[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset)
    {
        unset($this->sources[$offset]);
    }

    public function getIterator()
    {
        return new ArrayIterator($this->sources);
    }
}
