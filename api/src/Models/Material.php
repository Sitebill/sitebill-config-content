<?php

namespace App\Configs\Content\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use BelongsToTenant;

    protected $table = 'content_materials';

    protected $fillable = ['project_id', 'kind', 'entry_date', 'state', 'sphere', 'topic', 'title', 'body', 'tags'];
}
