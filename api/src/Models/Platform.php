<?php

namespace App\Configs\Content\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Platform extends Model
{
    use BelongsToTenant;

    protected $table = 'content_platforms';

    protected $fillable = ['project_id', 'name', 'kind', 'url', 'audience', 'formats', 'max_length', 'per_week', 'api_status'];
}
