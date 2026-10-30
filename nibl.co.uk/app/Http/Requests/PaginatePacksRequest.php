<?php

namespace App\Http\Requests;

use App\Models\Pack;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaginatePacksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['integer', 'min:1'],
            'size' => ['integer', 'min:1', 'max:'.Pack::MAX_PAGE_SIZE],
            'sort' => ['string', Rule::in(array_keys(Pack::SORTABLE))],
            'direction' => ['string', Rule::in(['asc', 'desc', 'ASC', 'DESC'])],
            'episode_number' => ['integer', 'min:0'],
            'episodeNumber' => ['integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Accept the legacy API's camelCase episode parameter.
        if ($this->filled('episodeNumber') && ! $this->filled('episode_number')) {
            $this->merge(['episode_number' => $this->query('episodeNumber')]);
        }
    }

    public function page(): int
    {
        return (int) $this->validated('page', 1);
    }

    public function size(): int
    {
        return (int) $this->validated('size', Pack::DEFAULT_PAGE_SIZE);
    }

    public function sort(): string
    {
        return (string) $this->validated('sort', Pack::DEFAULT_SORT);
    }

    public function direction(): string
    {
        return (string) $this->validated('direction', 'asc');
    }

    public function episodeNumber(): int
    {
        return (int) $this->validated('episode_number', 0);
    }
}
