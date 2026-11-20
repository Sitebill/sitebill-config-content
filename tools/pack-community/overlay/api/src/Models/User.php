<?php

namespace SitebillApps\Rumantic\ContentManager\Models;

use Illuminate\Database\Eloquent\Model;

/** Только чтение: имя автора комментария. Таблица пользователей общая, своих колонок приложение не добавляет. */
class User extends Model
{
    protected $table = 'users';
}
