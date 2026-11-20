<?php

namespace App\Configs\Content\Mcp\Concerns;

use App\Configs\Content\Support\ContentAccess;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;

/**
 * Доступ к tools контент-фабрики: только персональный токен участника команды (владелец сайта или роль из
 * `content_members`) на тенанте с app_type `content`, и только к тем действиям, на которые у его роли есть право
 * (`ContentAccess::can` — те же способности, что в GraphQL). Тенантский ключ без человека не допускается.
 * Скрытый в tools/list tool всё равно проверяется при вызове (PlatformTool::handle).
 */
trait ContentMcpAccess
{
    /** Способность из ContentAccess::ABILITIES, нужная для этого tool. */
    abstract protected function ability(): string;

    protected function roleAllows(Tenant $tenant, ?User $user): bool
    {
        return $user !== null && $tenant->app_type === 'content' && ContentAccess::can($user, $this->ability());
    }

    /**
     * Валидация входа агента. Пустая строка = «очистить поле» (агенты так обычно и делают), поэтому до проверки
     * превращаем её в null: правила `nullable` её пропускают, а `date_format` — нет.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function input(Request $request, array $rules): array
    {
        $all = array_map(fn ($v) => $v === '' ? null : $v, $request->all());

        return Validator::make($all, $rules)->validate();
    }
}
