<?php

namespace App\Configs\Content\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use BelongsToTenant;

    protected $table = 'content_projects';

    protected $fillable = ['name', 'description', 'topic', 'audience', 'tone', 'keywords', 'competitors', 'manager', 'color'];
}
