<?php

namespace App\Http\Controllers\Api\V1\Album;

use App\Http\Controllers\Controller;
use App\Http\Requests\Album\StoreAlbumRequest;
use App\Http\Requests\Album\UpdateAlbumRequest;
use App\Http\Resources\AlbumResource;
use App\Models\Album;
use App\Services\AlbumService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlbumController extends Controller
{
    use ApiResponse;

    public function __construct(protected AlbumService $albums)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Album::class);

        $paginator = $this->albums->paginate($request->only([
            'search', 'sortBy', 'sortDesc', 'page', 'perPage', 'status', 'customer_id', 'order_id',
        ]));

        return $this->success(
            AlbumResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreAlbumRequest $request): JsonResponse
    {
        $album = $this->albums->create($request->validated(), $request->user());

        return $this->created(new AlbumResource($album), 'Album created successfully.');
    }

    public function show(Album $album): JsonResponse
    {
        $this->authorize('view', $album);

        return $this->success(new AlbumResource($album->load('customer', 'order')));
    }

    public function update(UpdateAlbumRequest $request, Album $album): JsonResponse
    {
        $album = $this->albums->update($album, $request->validated());

        return $this->success(new AlbumResource($album), 'Album updated successfully.');
    }

    public function destroy(Album $album): JsonResponse
    {
        $this->authorize('delete', $album);

        $this->albums->delete($album);

        return $this->noContent('Album deleted successfully.');
    }

    public function start(Album $album): JsonResponse
    {
        $this->authorize('update', $album);

        return $this->success(new AlbumResource($this->albums->start($album)), 'Album moved to in progress.');
    }

    public function markReady(Album $album): JsonResponse
    {
        $this->authorize('update', $album);

        return $this->success(new AlbumResource($this->albums->markReady($album)), 'Album marked ready for delivery.');
    }

    public function deliver(Album $album): JsonResponse
    {
        $this->authorize('update', $album);

        return $this->success(new AlbumResource($this->albums->deliver($album)), 'Album delivered.');
    }

    public function archive(Album $album): JsonResponse
    {
        $this->authorize('update', $album);

        return $this->success(new AlbumResource($this->albums->archive($album)), 'Album archived.');
    }
}
