<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * V6 §7.5 — projection of one SafetyTrainingCatalog module entry. Pass
 * either the catalog's full array (for the detail endpoint) or a slim
 * subset (for the list endpoint) — see `forCatalogList()` / `forDetail()`.
 *
 * Catalog content is already a plain array, but going through a Resource
 * keeps the wire shape stable across module shape changes and matches the
 * project pattern (every other module exposes its data via a Resource).
 */
class TrainingModuleResource extends JsonResource
{
    /** Slim shape used by `GET /modules` (catalog list). */
    public const SHAPE_LIST = 'list';

    /** Full shape used by `GET /modules/{moduleId}` (detail). */
    public const SHAPE_DETAIL = 'detail';

    public function __construct(array $module, protected string $shape = self::SHAPE_DETAIL)
    {
        parent::__construct($module);
    }

    public function toArray($request): array
    {
        /** @var array $m */
        $m = $this->resource;

        $base = [
            'id' => $m['id'] ?? null,
            'title' => $m['title'] ?? null,
            'summary' => $m['summary'] ?? null,
            'readingTime' => $m['readingTime'] ?? null,
            'icon' => $m['icon'] ?? null,
        ];

        if ($this->shape === self::SHAPE_LIST) {
            return $base;
        }

        return array_merge($base, [
            'gridTitle' => $m['gridTitle'] ?? null,
            'keyItems' => $m['keyItems'] ?? [],
            'keyFacts' => $m['keyFacts'] ?? [],
            'sections' => $m['sections'] ?? [],
            'confirmations' => $m['confirmations'] ?? [],
            'warning' => $m['warning'] ?? null,
        ]);
    }
}
