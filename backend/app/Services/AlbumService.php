<?php

namespace App\Services;

use App\Enums\AlbumStatus;
use App\Exceptions\ApiException;
use App\Models\Album;
use App\Models\User;
use App\Repositories\Contracts\AlbumRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AlbumService extends BaseService
{
    public function __construct(protected AlbumRepositoryInterface $albums)
    {
        parent::__construct($albums);
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->albums->paginateServer($filters);
    }

    public function create(array $data, ?User $creator = null): Album
    {
        /** @var Album $album */
        $album = $this->albums->create([
            ...$data,
            'created_by' => $creator?->id,
        ]);

        return $album->load('customer', 'order');
    }

    public function update(Album $album, array $data): Album
    {
        $this->albums->update($album, $data);

        return $album->fresh(['customer', 'order']);
    }

    public function delete(Album $album): bool
    {
        return $this->albums->delete($album);
    }

    public function start(Album $album): Album
    {
        $this->assertStatus($album, AlbumStatus::Draft);
        $album->update(['status' => AlbumStatus::InProgress]);

        return $album;
    }

    public function markReady(Album $album): Album
    {
        $this->assertStatus($album, AlbumStatus::InProgress);
        $album->update(['status' => AlbumStatus::Ready]);

        return $album;
    }

    public function deliver(Album $album): Album
    {
        $this->assertStatus($album, AlbumStatus::Ready);
        $album->update(['status' => AlbumStatus::Delivered, 'delivered_at' => now()]);

        return $album;
    }

    public function archive(Album $album): Album
    {
        if ($album->status === AlbumStatus::Archived) {
            throw new ApiException(422, 'This album is already archived.', 'ALBUM_ALREADY_ARCHIVED');
        }

        $album->update(['status' => AlbumStatus::Archived]);

        return $album;
    }

    protected function assertStatus(Album $album, AlbumStatus $expected): void
    {
        if ($album->status !== $expected) {
            throw new ApiException(422, "This action requires the album to be \"{$expected->label()}\" (currently \"{$album->status->label()}\").", 'ALBUM_INVALID_STATUS_TRANSITION', ['expected' => $expected->label(), 'current' => $album->status->label()]);
        }
    }
}
