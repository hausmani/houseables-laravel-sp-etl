<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientProfile extends Model
{
    use SoftDeletes;

    protected $table = 'client_profile_info';
    protected $fillable = [
        'client_id',
        'profileId',
        'countryCode',
        'currencyCode',
        'id_timezone',
        'client_authorisation_id',
        'sellerName',
        'sellerId',
        'marketplaceId',
        'type',
        'active',
        'inactive_reports',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'inactive_reports' => 'array',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];
}
