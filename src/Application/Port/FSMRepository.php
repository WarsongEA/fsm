<?php

declare(strict_types=1);

namespace FSM\Application\Port;

use FSM\Core\Model\FSMInstance;

/**
 * Repository interface (Port) for FSM persistence
 */
interface FSMRepository
{
    public function save(FSMInstance $instance): void;
    
    public function findById(string $id): ?FSMInstance;
    
    public function delete(string $id): void;
    
    /**
     * @return array<FSMInstance>
     */
    public function findAll(int $limit = 100, int $offset = 0): array;
    
    public function exists(string $id): bool;
}