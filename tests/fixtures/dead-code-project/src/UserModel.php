<?php

namespace Dead;

class UserModel extends BaseModel
{
    // Alive ONLY because BaseModel::render() calls $this->hookName():
    // requires the ancestor scope to count for protected methods.
    protected function hookName(): string
    {
        return 'user';
    }

    public function label(): string
    {
        // midHelper reaches UserModel through BaseModel's `use MidTrait`
        // plus inheritance: transitive consumer closure.
        return $this->midHelper();
    }

    private function neverCalled(): void
    {
        // Dead on purpose.
    }
}
