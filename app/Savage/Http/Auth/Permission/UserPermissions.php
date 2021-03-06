<?php

namespace Savage\Http\Auth\Permission;

use Illuminate\Database\Eloquent\Model as Eloquent;

class UserPermissions extends Eloquent
{
    protected $table = 'permissions';

    protected $fillable = ['is_admin', 'is_head_admin'];

    public static $defaults = [
        'is_admin' => false,
        'is_head_admin' => false,
    ];
}
