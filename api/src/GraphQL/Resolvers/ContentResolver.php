<?php

namespace App\Configs\Content\GraphQL\Resolvers;

use App\Configs\Content\Support\ContentAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Configs\Content\Models\Event;
use App\Configs\Content\Models\Material;
use App\Configs\Content\Services\AudiencePains;
use App\Configs\Content\Models\Placement;
use App\Configs\Content\Models\Platform;
use App\Configs\Content\Models\Project;
use App\Configs\Content\Models\Publication;
use App\Configs\Content\Services\DefaultPlatforms;
use App\Configs\Content\Services\DemoData;
use App\Configs\Content\Services\PlanAgent;
use App\Configs\Content\Services\PublicationState;

/** Admin GraphQL of the app. Permissions are checked by @guard + @can in the schema; tenant scope by AppModel. */
final class ContentResolver
{
    // ------------------------------------------------------------------ reads

    public function projects(): array
    {
        return Project::query()->orderBy('id')->get()->map(fn (Project $p) => $this->plain($p))->all();
    }

    public function platforms(): array
    {
        return Platform::query()->orderBy('project_id')->orderBy('id')->get()->map(fn (Platform $p) => $this->plain($p))->all();
    }

    public function publications(mixed $root, array $args): array
    {
        $pubs = Publication::query()
            ->when(! empty($args['project_id']), fn ($q) => $q->where('project_id', $args['project_id']))
            ->orderBy('planned_date')->orderBy('planned_time')->orderBy('id')
            ->limit(max(1, min(5000, (int) ($args['limit'] ?? 2000))))->get();

        return $this->withPlacements($pubs);
    }

    public function materials(mixed $root, array $args): array
    {
        return Material::query()
            ->when(! empty($args['project_id']), fn ($q) => $q->where(fn ($w) => $w->where('project_id', $args['project_id'])->orWhereNull('project_id')))
            ->orderByDesc('entry_date')->orderByDesc('id')->get()->map(fn (Material $m) => $this->materialRow($m))->all();
    }

    public function events(): array
    {
        return Event::query()->orderBy('event_date')->orderBy('event_time')->get()->map(fn (Event $e) => $this->plain($e))->all();
    }

    // ------------------------------------------------------------------ assistant (never writes)

    public function review(mixed $root, array $args): array
    {
        return (new PlanAgent())->review($args['project_id'] ?? null, $this->today($args));
    }

    public function ideas(mixed $root, array $args): array
    {
        return (new PlanAgent())->ideas(Project::query()->findOrFail($args['project_id']), (int) ($args['count'] ?? 6), $this->locale($args));
    }

    public function planProposal(mixed $root, array $args): array
    {
        return (new PlanAgent())->plan(Project::query()->findOrFail($args['project_id']), (int) ($args['days'] ?? 7), $this->locale($args), $this->today($args));
    }

    public function adaptation(mixed $root, array $args): array
    {
        return (new PlanAgent())->adapt(
            Publication::query()->findOrFail($args['publication_id']),
            Platform::query()->findOrFail($args['platform_id']),
            $this->locale($args),
        );
    }

    // ------------------------------------------------------------------ projects and channels

    public function saveProject(mixed $root, array $args): array
    {
        $project = ! empty($args['id']) ? Project::query()->findOrFail($args['id']) : new Project();
        $isNew = ! $project->exists;
        if ($isNew) {
            app(\App\Entitlements\TenantEntitlements::class)->assertCanCreate(app('currentTenant'), 'content_projects_max');
        }
        $project->fill($args['input'])->save();
        if ($isNew) {
            DefaultPlatforms::ensure($project); // Дзен, ВКонтакте, Автор.тудей, Макс, Одноклассники, vc.ru, workspace.ru, TenChat, Looky
        }

        return $this->plain($project);
    }

    public function deleteProject(mixed $root, array $args): bool
    {
        $project = Project::query()->findOrFail($args['id']);
        $pubIds = Publication::query()->where('project_id', $project->id)->pluck('id');
        Placement::query()->whereIn('publication_id', $pubIds)->delete();
        Publication::query()->where('project_id', $project->id)->delete();
        Platform::query()->where('project_id', $project->id)->delete();
        Material::query()->where('project_id', $project->id)->delete();
        $project->delete();

        return true;
    }

    public function savePlatform(mixed $root, array $args): array
    {
        $in = $args['input'];
        Project::query()->findOrFail($in['project_id']); // the project must belong to this site
        $in['api_status'] = $in['api_status'] ?? 'manual';
        $platform = ! empty($args['id']) ? Platform::query()->findOrFail($args['id']) : new Platform();
        $platform->fill($in)->save();

        return $this->plain($platform);
    }

    public function deletePlatform(mixed $root, array $args): bool
    {
        $platform = Platform::query()->findOrFail($args['id']);
        $affected = Placement::query()->where('platform_id', $platform->id)->pluck('publication_id')->unique();
        Placement::query()->where('platform_id', $platform->id)->delete();
        $platform->delete();
        // The overall status of publications that were on this channel may change.
        Publication::query()->whereIn('id', $affected)->get()->each(fn (Publication $p) => PublicationState::sync($p));

        return true;
    }

    // ------------------------------------------------------------------ publications

    public function savePublication(mixed $root, array $args): array
    {
        $in = $args['input'];
        Project::query()->findOrFail($in['project_id']);
        if (! empty($in['parent_id'])) {
            Publication::query()->findOrFail($in['parent_id']);
        }
        $this->guardStatus($in['status'] ?? null, 'input.status');
        $platformIds = $in['platform_ids'] ?? null;
        unset($in['platform_ids']);
        // Аудитория и боли: боль, которой нет у выбранной аудитории, молча отбрасываем (аудиторию могли отредактировать).
        if (array_key_exists('audience_id', $in) || array_key_exists('pain_ids', $in)) {
            $aud = ! empty($in['audience_id']) ? Material::query()->where('kind', 'audience')->findOrFail($in['audience_id']) : null;
            $in['audience_id'] = $aud?->id;
            $in['pain_ids'] = $aud ? array_values(array_intersect($in['pain_ids'] ?? [], AudiencePains::painIds($aud->pains))) : [];
        }

        $pub = ! empty($args['id']) ? Publication::query()->findOrFail($args['id']) : new Publication();
        $pub->fill(array_filter($in, fn ($v) => $v !== null) + ($pub->exists ? [] : ['status' => 'idea']))->save();
        // nullable fields can be cleared explicitly
        foreach (['rubric', 'planned_date', 'planned_time', 'body', 'hashtags', 'link', 'assignee', 'comment', 'audience_id'] as $f) {
            if (array_key_exists($f, $in) && $in[$f] === null) {
                $pub->{$f} = null;
            }
        }
        $pub->save();

        if (is_array($platformIds)) {
            $this->syncPlatforms($pub, $platformIds);
        }

        return $this->withPlacements(collect([PublicationState::sync($pub->fresh())]))[0];
    }

    public function deletePublication(mixed $root, array $args): bool
    {
        $pub = Publication::query()->findOrFail($args['id']);
        Placement::query()->where('publication_id', $pub->id)->delete();
        // Adaptations stay as independent publications; only the link to the deleted source goes away.
        Publication::query()->where('parent_id', $pub->id)->update(['parent_id' => null]);
        $pub->delete();

        return true;
    }

    public function movePublication(mixed $root, array $args): array
    {
        $pub = Publication::query()->findOrFail($args['id']);
        $this->guardStatus($args['status'] ?? null, 'status');
        if (! empty($args['status'])) {
            $pub->status = $args['status'];
        }
        if (array_key_exists('planned_date', $args)) {
            $pub->planned_date = $args['planned_date'];
        }
        $pub->save();

        return $this->withPlacements(collect([$pub->fresh()]))[0];
    }

    public function setPlacement(mixed $root, array $args): array
    {
        $pub = Publication::query()->findOrFail($args['publication_id']);
        $platform = Platform::query()->findOrFail($args['platform_id']);
        $pl = Placement::query()->where('publication_id', $pub->id)->where('platform_id', $platform->id)->first() ?? new Placement([
            'publication_id' => $pub->id,
            'platform_id' => $platform->id,
        ]);
        $pl->status = $args['status'];
        if (array_key_exists('url', $args)) {
            $pl->url = $args['url'];
        }
        if (array_key_exists('comment', $args)) {
            $pl->comment = $args['comment'];
        }
        if ($pl->status === 'published' && ! $pl->published_at) {
            $pl->published_at = now();
        } elseif ($pl->status !== 'published') {
            $pl->published_at = null;
        }
        $pl->save();

        return $this->withPlacements(collect([PublicationState::sync($pub->fresh())]))[0];
    }

    public function duplicatePublication(mixed $root, array $args): array
    {
        $src = Publication::query()->findOrFail($args['id']);
        $copy = $src->replicate(['created_at', 'updated_at']);
        $copy->parent_id = $src->id;
        $copy->status = 'idea';
        $copy->title = mb_substr($src->title.' (v2)', 0, 200);
        $copy->save();

        return $this->withPlacements(collect([$copy->fresh()]))[0];
    }

    // ------------------------------------------------------------------ library

    public function saveMaterial(mixed $root, array $args): array
    {
        $in = $args['input'];
        if (! empty($in['project_id'])) {
            Project::query()->findOrFail($in['project_id']);
        }
        $m = ! empty($args['id']) ? Material::query()->findOrFail($args['id']) : new Material();
        $pains = array_key_exists('pains', $in) ? AudiencePains::sanitize($in['pains']) : ($m->pains ?? null);
        unset($in['pains']);
        $m->fill($in);
        $m->pains = $in['kind'] === 'audience' ? $pains : null;
        $m->entry_date = $in['entry_date'] ?? ($m->entry_date ?: now()->toDateString());
        $m->save();

        return $this->materialRow($m);
    }

    public function deleteMaterial(mixed $root, array $args): bool
    {
        Material::query()->findOrFail($args['id'])->delete();

        return true;
    }

    public function saveEvent(mixed $root, array $args): array
    {
        $e = ! empty($args['id']) ? Event::query()->findOrFail($args['id']) : new Event();
        $e->fill($args['input'])->save();

        return $this->plain($e->fresh());
    }

    public function deleteEvent(mixed $root, array $args): bool
    {
        Event::query()->findOrFail($args['id'])->delete();

        return true;
    }

    public function seedDemo(mixed $root, array $args): bool
    {
        if (Project::query()->exists() || Publication::query()->exists()) {
            return false;
        }
        // The browser tells which language the owner reads the site in; fall back to the site's own locale.
        $ru = str_starts_with((string) ($args['locale'] ?? app('currentTenant')->default_locale), 'ru');
        (new DemoData())->seed($ru, CarbonImmutable::today());

        return true;
    }

    // ------------------------------------------------------------------ helpers

    /** «Готово» и «опубликовано» ставит согласующий или редактор; автор доводит материал только до «на согласовании». */
    private function guardStatus(?string $status, string $field): void
    {
        if ($status !== null && in_array($status, ContentAccess::APPROVED_STATUSES, true) && ! ContentAccess::can(Auth::user(), 'content.approve')) {
            throw ValidationException::withMessages([$field => 'This status can only be set by an editor or an approver.']);
        }
    }

    /** Replaces the channel list of a publication. Published placements are never removed. */
    private function syncPlatforms(Publication $pub, array $platformIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $platformIds)));
        // Every channel must belong to the project of the publication.
        $valid = Platform::query()->where('project_id', $pub->project_id)->whereIn('id', $ids)->pluck('id')->all();
        $have = Placement::query()->where('publication_id', $pub->id)->get()->keyBy('platform_id');
        foreach ($valid as $id) {
            if (! $have->has($id)) {
                Placement::create(['publication_id' => $pub->id, 'platform_id' => $id, 'status' => 'todo']);
            }
        }
        foreach ($have as $platformId => $pl) {
            if (! in_array($platformId, $valid, true) && $pl->status !== 'published') {
                $pl->delete();
            }
        }
    }

    /** @param  \Illuminate\Support\Collection<int, Publication>  $pubs */
    private function withPlacements($pubs): array
    {
        $placements = Placement::query()->whereIn('publication_id', $pubs->pluck('id'))->orderBy('id')->get()->groupBy('publication_id');

        return $pubs->values()->map(function (Publication $p) use ($placements) {
            $mine = $placements->get($p->id, collect());
            $row = $this->plain($p);
            $row['placements'] = $mine->map(fn (Placement $pl) => $this->plain($pl))->all();
            $row['placement_state'] = PublicationState::placementState($mine);

            return $row;
        })->all();
    }

    /** Боли отдаём JSON-строкой: структура свободная, клиент разбирает её сам. */
    private function materialRow(Material $m): array
    {
        $row = $this->plain($m);
        $row['pains'] = $m->pains ? json_encode($m->pains, JSON_UNESCAPED_UNICODE) : null;

        return $row;
    }

    private function plain(\Illuminate\Database\Eloquent\Model $m): array
    {
        $a = $m->attributesToArray();
        foreach (['planned_date', 'entry_date', 'event_date'] as $f) {
            if (! empty($a[$f])) {
                $a[$f] = substr((string) $a[$f], 0, 10);
            }
        }
        if (! empty($a['published_at'])) {
            $a['published_at'] = CarbonImmutable::parse($a['published_at'])->format('Y-m-d H:i:s');
        }
        if (! empty($a['planned_time'])) {
            $a['planned_time'] = substr((string) $a['planned_time'], 0, 5);
        }
        if (! empty($a['event_time'])) {
            $a['event_time'] = substr((string) $a['event_time'], 0, 5);
        }

        return $a;
    }

    private function locale(array $args): string
    {
        return (string) ($args['locale'] ?? app('currentTenant')->default_locale ?? 'en');
    }

    private function today(array $args): CarbonImmutable
    {
        return CarbonImmutable::parse($args['today'] ?? 'today')->startOfDay();
    }
}
