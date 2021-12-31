<?php

namespace Tests\QT\Import\Stubs;

use Mockery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;

class Foo extends Model
{
    protected $table = 'table';

    protected $primaryKey = 'id';

    protected $guarded = [];

    public $timestamps = false;

    public static $snakeAttributes = false;

    public function belongsToBar()
    {
        return $this->belongsTo(Bar::class);
    }

    public function hasOneBar()
    {
        return $this->hasOne(Bar::class);
    }

    public function hasManyBar()
    {
        return $this->hasMany(Bar::class);
    }

    public function belongsToManyBar()
    {
        return $this->belongsToMany(Bar::class);
    }

    public function hasManyThroughBar()
    {
        return $this->hasManyThrough(Bar::class, Relation::class);
    }

    public function unknownRelation()
    {
        return Mockery::mock(EloquentRelation::class);
    }
}
