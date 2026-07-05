<?php

namespace Docs;

class Box
{
    /**
     * Replaces the items inside.
     *
     * @param Item[] $items The items to store.
     * @return $this Fluent interface.
     */
    public function setItems(array $items): static
    {
        return $this;
    }

    /**
     * Sets the printed label.
     *
     * @param string|null $label Modern docblock, pre-8.0 signature style.
     */
    public function setLabel(?string $label): void
    {
    }
}

class Item
{
}

class NoDocs
{
    public function plain($anything)
    {
        return $anything;
    }
}
