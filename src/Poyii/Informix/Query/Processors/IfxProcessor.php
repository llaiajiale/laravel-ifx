<?php

namespace Poyii\Informix\Query\Processors;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor;

class IfxProcessor extends Processor
{
    /**
     * Process the results of a column listing query.
     *
     * @param  array  $results
     * @return array
     */
    public function processColumnListing($results)
    {
        $mapping = function ($r) {
            $r = (object) $r;

            return $r->column_name;
        };

        return array_map($mapping, $results);
    }

    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        return parent::processInsertGetId($query, $sql, $values, $sequence);
    }

    public function processSelect(Builder $query, $results)
    {
        return $results;
    }

}
