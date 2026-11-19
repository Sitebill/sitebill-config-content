<?php

namespace App\Configs\Content\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** «Важное» в календаре: день рождения, праздник, приём у врача и т. п. */
class Event extends Model
{
    use BelongsToTenant;

    protected $table = 'content_events';

    protected $fillable = ['kind', 'title', 'event_date', 'event_time', 'comment'];
}
