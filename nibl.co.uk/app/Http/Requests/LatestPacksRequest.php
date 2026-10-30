<?php

namespace App\Http\Requests;

use App\Models\Pack;
use Illuminate\Foundation\Http\FormRequest;

class LatestPacksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'size' => ['integer', 'min:1', 'max:'.Pack::MAX_PAGE_SIZE],
        ];
    }

    public function size(): int
    {
        return (int) $this->validated('size', Pack::DEFAULT_PAGE_SIZE);
    }
}
