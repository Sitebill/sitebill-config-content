<?php

namespace App\Configs\Content\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Placement extends Model
{
    use BelongsToTenant;

    protected $table = 'content_placements';

    protected $fillable = ['publication_id', 'platform_id', 'status', 'published_at', 'url', 'comment'];
}
