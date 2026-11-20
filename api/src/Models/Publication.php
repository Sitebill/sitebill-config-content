<?php

namespace App\Configs\Content\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Publication extends Model
{
    use BelongsToTenant;

    protected $table = 'content_publications';

    protected $fillable = ['project_id', 'parent_id', 'title', 'kind', 'rubric', 'sphere', 'status', 'priority', 'planned_date', 'planned_time', 'body', 'hashtags', 'link', 'assignee', 'comment', 'audience_id', 'pain_ids'];

    protected $casts = ['pain_ids' => 'array'];
}
