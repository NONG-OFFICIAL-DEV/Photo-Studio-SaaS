<?php

namespace App\Policies;

use App\Models\Album;
use App\Models\User;

class AlbumPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('albums.view');
    }

    public function view(User $user, Album $album): bool
    {
        return $user->can('albums.view') && $user->tenant_id === $album->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('albums.create');
    }

    public function update(User $user, Album $album): bool
    {
        return $user->can('albums.update') && $user->tenant_id === $album->tenant_id;
    }

    public function delete(User $user, Album $album): bool
    {
        return $user->can('albums.delete') && $user->tenant_id === $album->tenant_id;
    }
}
