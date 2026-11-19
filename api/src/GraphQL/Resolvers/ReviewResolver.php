<?php

namespace App\Configs\Content\GraphQL\Resolvers;

use App\Configs\Content\Models\Comment;
use App\Configs\Content\Models\Publication;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Обсуждение и согласование публикации. Права задаёт схема (`content.move` — писать в ленту, `content.approve` —
 * решение); здесь только правила процесса: решение принимается по публикации «на согласовании», «на доработку»
 * требует пояснения — автору должно быть понятно, что править.
 */
final class ReviewResolver
{
    public function comments(mixed $root, array $args): array
    {
        Publication::query()->findOr($args['publication_id'], fn () => throw ValidationException::withMessages(['publication_id' => 'Публикация не найдена.']));

        return Comment::query()->with('user:id,name')->where('publication_id', $args['publication_id'])->orderBy('id')->get()
            ->map(fn (Comment $c) => $this->plain($c))->all();
    }

    public function addComment(mixed $root, array $args): array
    {
        $pub = $this->publication($args['publication_id']);

        return $this->plain($this->record($pub, 'comment', trim($args['body'])));
    }

    /** approve → «готов к публикации»; return → «в работе» с пояснением. Только из статуса «на согласовании». */
    public function decide(mixed $root, array $args): array
    {
        $pub = $this->publication($args['publication_id']);
        if ($pub->status !== 'review') {
            throw ValidationException::withMessages(['publication_id' => 'Решение принимается по публикации со статусом «На согласовании».']);
        }

        $decision = $args['decision'];
        $body = trim((string) ($args['comment'] ?? ''));
        if ($decision === 'return' && $body === '') {
            throw ValidationException::withMessages(['comment' => 'Напишите, что нужно доработать.']);
        }

        $pub->status = $decision === 'approve' ? 'ready' : 'in_work';
        $pub->save();

        return $this->plain($this->record($pub, $decision, $body));
    }

    private function publication(mixed $id): Publication
    {
        return Publication::query()->find($id)
            ?? throw ValidationException::withMessages(['publication_id' => 'Публикация не найдена.']);
    }

    private function record(Publication $pub, string $kind, string $body): Comment
    {
        $c = new Comment(['publication_id' => $pub->id, 'user_id' => Auth::id(), 'kind' => $kind, 'body' => $body !== '' ? $body : null]);
        $c->tenant_id = $pub->tenant_id;
        $c->save();

        return $c->load('user:id,name');
    }

    private function plain(Comment $c): array
    {
        return [
            'id' => $c->id, 'publication_id' => $c->publication_id, 'kind' => $c->kind, 'body' => $c->body,
            'author' => $c->user?->name, 'created_at' => $c->created_at?->toIso8601String(),
        ];
    }
}
