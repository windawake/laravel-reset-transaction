<?php

namespace Laravel\ResetTransaction\Database;

use Illuminate\Database\Query\Processors\MySqlProcessor as Processor;
use Illuminate\Database\Query\Builder;
use Laravel\ResetTransaction\Facades\RT;

class MySqlProcessor extends Processor
{
    /**
     * Process an  "insert get ID" query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $sql
     * @param  array  $values
     * @param  string|null  $sequence
     * @return int
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        $id = parent::processInsertGetId($query, $sql, $values, $sequence);

        RT::saveQuery($sql, $values, 0, 0, $sequence, $id);

        return $id;
    }
}
