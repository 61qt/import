<?php

namespace Tests\QT\Import\Stubs;

use Illuminate\Database\Eloquent\Model;

class Relation extends Model
{
    protected $table = 'foo_bar_relations';

    protected $guarded = [];

    public $timestamps = false;
}
