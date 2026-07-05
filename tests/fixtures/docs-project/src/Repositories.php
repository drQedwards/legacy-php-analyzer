<?php

namespace Docs;

/**
 * Base repository with the canonical lookup documentation.
 */
abstract class Repository
{
    /**
     * Finds an entity by its identifier.
     *
     * Longer description that must NOT leak into the summary.
     *
     * @param string $id The identifier.
     * @return object|null The entity or null when missing.
     */
    public function find(string $id): ?object
    {
        return null;
    }
}

class UserRepository extends Repository
{
    /**
     * {@inheritdoc}
     */
    public function find(string $id): ?object
    {
        return null;
    }
}

class LegacyUserRepository extends Repository
{
    /**
     * {@inheritDoc}
     */
    public function find(int $id, bool $cached = false): ?object
    {
        return null;
    }
}

class ExternalChild extends \ArrayObject
{
    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return 0;
    }
}
