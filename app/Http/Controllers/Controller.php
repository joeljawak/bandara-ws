<?php

namespace App\Http\Controllers;

use Illuminate\Support\Collection;

abstract class Controller
{
    /**
     * Menyaring data menggunakan callback dan mengembalikan Collection.
     * Contoh: $this->filter($data, fn ($row) => ! empty($row['iata'])).
     */
    public function filter(iterable $items, callable $callback): Collection
    {
        return collect($items)
            ->filter($callback)
            ->values();
    }

    /**
     * Mengakses nilai secara aman ketika objek/nilai mungkin null.
     * Callback hanya dijalankan jika nilai tidak null.
     */
    public function optional(mixed $value, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($value === null) {
            return $default;
        }

        return $callback !== null ? $callback($value) : $value;
    }
}
