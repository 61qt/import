<?php

namespace Tests\QT\Import\Stubs;

use Illuminate\Database\Eloquent\Model;

class Bar extends Model
{
    protected $table = 'table';

    protected $primaryKey = 'id';

    protected $guarded = [];

    public $timestamps = false;

    public static $snakeAttributes = false;
}
