<?php

namespace App\Services;

use App\Models\CustomerTag;
use App\Repositories\Contracts\CustomerTagRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class CustomerTagService extends BaseService
{
    public function __construct(protected CustomerTagRepositoryInterface $tags)
    {
        parent::__construct($tags);
    }

    public function all(): Collection
    {
        return $this->tags->all();
    }

    public function create(array $data): CustomerTag
    {
        return $this->tags->create($data);
    }

    public function update(CustomerTag $tag, array $data): CustomerTag
    {
        return $this->tags->update($tag, $data);
    }

    public function delete(CustomerTag $tag): bool
    {
        return $this->tags->delete($tag);
    }
}
