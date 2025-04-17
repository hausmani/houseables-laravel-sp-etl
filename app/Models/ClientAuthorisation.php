<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientAuthorisation extends Model
{
    use SoftDeletes;

    protected $table = 'client_authorisations'; // Set the exact table name
    protected $fillable = [
        'client_id',
        'p_id',
        'auth_api',
        'oauth_type',
        'expired_at',
        'authorised_at',
        'access_token',
        'refresh_token',
        'amazon_user_id',
        'name',
        'email',
        'region',
        'active',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'active' => 'boolean'
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'authorised_at',
        'expired_at',
        'deleted_at'
    ];
}
