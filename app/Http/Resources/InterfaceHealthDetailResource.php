<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * V1.4 §9 detail-panel shape. Builds on the list resource and adds
 * fallback narrative, the last 10 audit rows, and a suggested-routes
 * block that mirrors the spec's "Actions" column.
 *
 * Constructor accepts a pre-assembled array (the controller merges the
 * list shape with the relations + suggested-routes hash). Going through
 * a Resource keeps the wire shape stable.
 */
class InterfaceHealthDetailResource extends JsonResource
{
    public function __construct(array $detail)
    {
        parent::__construct($detail);
    }

    public function toArray($request): array
    {
        /** @var array $d */
        $d = $this->resource;
        return $d;
    }
}
