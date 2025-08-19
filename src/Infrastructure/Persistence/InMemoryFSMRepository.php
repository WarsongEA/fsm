<?php

declare(strict_types=1);

namespace FSM\Infrastructure\Persistence;

use FSM\Application\Port\FSMRepository;
use FSM\Core\Model\FSMInstance;

/**
 * In-memory implementation for testing
 */
final class InMemoryFSMRepository implements FSMRepository
{
    /** @var array<string, FSMInstance> */
    private array $storage = [];
    
    public function save(FSMInstance $instance): void
    {
        $this->storage[$instance->getId()] = clone $instance;
    }
    
    public function findById(string $id): ?FSMInstance
    {
        return isset($this->storage[$id]) ? clone $this->storage[$id] : null;
    }
    
    public function delete(string $id): void
    {
        unset($this->storage[$id]);
    }
    
    public function findAll(int $limit = 100, int $offset = 0): array
    {
        return array_slice($this->storage, $offset, $limit);
    }
    
    public function exists(string $id): bool
    {
        return isset($this->storage[$id]);
    }
    
    public function clear(): void
    {
        $this->storage = [];
    }
    
    public function count(): int
    {
        return count($this->storage);
    }
}