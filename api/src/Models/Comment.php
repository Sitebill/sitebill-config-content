<?php

namespace App\Configs\Content\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Запись ленты публикации: комментарий команды или решение согласующего (approve | return). */
class Comment extends Model
{
    use BelongsToTenant;

    protected $table = 'content_comments';

    protected $fillable = ['publication_id', 'user_id', 'kind', 'body'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
