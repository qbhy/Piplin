<?php

/*
 * This file is part of Piplin.
 *
 * Copyright (C) 2016-2017 piplin.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Piplin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use McCool\LaravelAutoPresenter\HasPresenter;
use Piplin\Models\Traits\BroadcastChanges;
use Piplin\Models\Traits\HasTargetable;
use Piplin\Presenters\CommandPresenter;

/**
 * The command model.
 *
 * @property int $id
 * @property string $name
 * @property string|null $user
 * @property string $script
 * @property int $targetable_id
 * @property string $targetable_type
 * @property int $step
 * @property bool $optional
 * @property int $order
 * @property bool $default_on
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\Piplin\Models\Environment[] $environments
 * @property-read int|null $environments_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\Piplin\Models\Pattern[] $patterns
 * @property-read int|null $patterns_count
 * @property-read Model|\Eloquent $targetable
 * @method static \Illuminate\Database\Eloquent\Builder|Command newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Command newQuery()
 * @method static \Illuminate\Database\Query\Builder|Command onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Command query()
 * @method static \Illuminate\Database\Eloquent\Builder|Command whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Command whereDefaultOn($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Command whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Command whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Command whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Command whereOptional($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Command whereOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Command whereScript($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Command whereStep($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Command whereTargetableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Command whereTargetableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Command whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Command whereUser($value)
 * @method static \Illuminate\Database\Query\Builder|Command withTrashed()
 * @method static \Illuminate\Database\Query\Builder|Command withoutTrashed()
 * @mixin \Eloquent
 */
class Command extends Model implements HasPresenter
{
    use SoftDeletes, BroadcastChanges, HasTargetable;

    // For deploy
    const BEFORE_CLONE    = 1;
    const DO_CLONE        = 2;
    const AFTER_CLONE     = 3;
    const BEFORE_INSTALL  = 4;
    const DO_INSTALL      = 5;
    const AFTER_INSTALL   = 6;
    const BEFORE_ACTIVATE = 7;
    const DO_ACTIVATE     = 8;
    const AFTER_ACTIVATE  = 9;
    const BEFORE_PURGE    = 10;
    const DO_PURGE        = 11;
    const AFTER_PURGE     = 12;

    // For build
    const BEFORE_PREPARE = 31;
    const DO_PREPARE     = 32;
    const AFTER_PREPARE  = 33;
    const BEFORE_BUILD   = 34;
    const DO_BUILD       = 35;
    const AFTER_BUILD    = 36;
    const BEFORE_TEST    = 37;
    const DO_TEST        = 38;
    const AFTER_TEST     = 39;
    const BEFORE_RESULT  = 40;
    const DO_RESULT      = 41;
    const AFTER_RESULT   = 42;

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['created_at', 'deleted_at', 'updated_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'user', 'script', 'step', 'order', 'optional', 'default_on'];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'id'         => 'integer',
        'step'       => 'integer',
        'optional'   => 'boolean',
        'default_on' => 'boolean',
        'order'      => 'integer',
    ];

    public function environments()
    {
        return $this->belongsToMany(Environment::class)
                    ->orderBy('order', 'ASC');
    }

    public function patterns()
    {
        return $this->belongsToMany(Pattern::class)
                    ->orderBy('name', 'ASC');
    }

    /**
     * Get the presenter class.
     *
     * @return string
     */
    public function getPresenterClass()
    {
        return CommandPresenter::class;
    }
}
