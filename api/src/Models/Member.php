<?php

namespace App\Configs\Content\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Участник команды контент-фабрики тенанта: editor | author | approver | viewer. */
class Member extends Model
{
    use BelongsToTenant;

    public const ROLES = ['editor', 'author', 'approver', 'viewer'];

    protected $table = 'content_members';

    protected $fillable = ['user_id', 'role'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
