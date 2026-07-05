<?php

namespace Dead;

// Never implemented nor referenced: a dead interface. Its method must
// NOT appear as a dead method (contract, no body).
interface Contract
{
    public function apply(): int;
}
