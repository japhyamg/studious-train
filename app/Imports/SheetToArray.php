<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * Raw sheet reader — returns the worksheet as a plain array of rows so the
 * caller can map columns flexibly (header-aware or positional).
 */
class SheetToArray implements ToArray
{
    public function array(array $array)
    {
        return $array;
    }
}
