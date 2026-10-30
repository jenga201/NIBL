<?php

namespace App\Http\Requests;

class SearchPacksRequest extends PaginatePacksRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'query' => ['required', 'string', 'max:250'],
        ]);
    }

    public function searchTerm(): string
    {
        return (string) $this->validated('query');
    }
}
