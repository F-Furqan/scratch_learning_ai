<?php

namespace App\Policies\Concerns;

use BackedEnum;

trait ComparesBackedEnums
{
    protected function enumEquals(mixed $actual, BackedEnum $expected): bool
    {
        if ($actual instanceof BackedEnum) {
            return $actual === $expected;
        }

        return $actual === $expected->value;
    }

    /**
     * @param  list<BackedEnum>  $expected
     */
    protected function enumIn(mixed $actual, array $expected): bool
    {
        foreach ($expected as $enum) {
            if ($this->enumEquals($actual, $enum)) {
                return true;
            }
        }

        return false;
    }
}
