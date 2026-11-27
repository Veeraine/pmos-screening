<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One item on the PMOS symptom tick list. The list comes from its migration and the rules use the codes.
 */
class Symptom extends Model
{
    #[Scope]
    protected function inListOrder(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }
}
