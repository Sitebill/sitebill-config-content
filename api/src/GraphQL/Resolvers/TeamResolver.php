<?php

namespace App\Configs\Content\GraphQL\Resolvers;

use App\Configs\Content\Models\Member;
use App\Configs\Content\Support\ContentAccess;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** Команда тенанта. Права — `content.team` (только owner), тенант — через BelongsToTenant. */
final class TeamResolver
{
    public function myRole(): string
    {
        return ContentAccess::roleOf(Auth::user()) ?? 'viewer';
    }

    public function members(): array
    {
        return Member::query()->with('user:id,name,email')->orderBy('id')->get()->map(fn (Member $m) => $this->row($m))->all();
    }

    public function saveMember(mixed $root, array $args): array
    {
        $in = $args['input'];
        // Только пользователь этого же сайта (а не любой id): иначе можно «пригласить» чужого.
        $user = User::query()->where('tenant_id', app('currentTenant')->id)->find($in['user_id']);
        if (! $user) {
            throw ValidationException::withMessages(['input.user_id' => 'User not found on this site.']);
        }
        if ($user->hasRole('admin')) {
            throw ValidationException::withMessages(['input.user_id' => 'The site admin is always the owner.']);
        }
        $member = Member::query()->where('user_id', $user->id)->first() ?? new Member(['user_id' => $user->id]);
        if (! $member->exists) {
            app(\App\Entitlements\TenantEntitlements::class)->assertCanCreate(app('currentTenant'), 'content_members_max');
        }
        $member->role = $in['role'];
        $member->save();

        return $this->row($member->load('user:id,name,email'));
    }

    public function removeMember(mixed $root, array $args): bool
    {
        Member::query()->where('user_id', $args['user_id'])->delete();

        return true;
    }

    private function row(Member $m): array
    {
        return ['id' => $m->id, 'user_id' => $m->user_id, 'name' => (string) $m->user?->name, 'email' => $m->user?->email, 'role' => $m->role];
    }
}
