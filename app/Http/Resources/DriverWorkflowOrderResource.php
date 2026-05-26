<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row in the driver-facing orders list (V6 §8.6).
 *
 * Receives the already-shaped associative array returned by
 * DriverWorkflowService::orderRow() so the wire shape is exactly what the
 * kiosk renders — no model->resource translation surprises.
 */
class DriverWorkflowOrderResource extends JsonResource
{
    public function toArray($request): array
    {
        // $this->resource is the array from the service.
        $r = (array) $this->resource;

        return [
            'orderId' => $r['orderId'] ?? null,
            'orderNo' => $r['orderNo'] ?? null,
            'sapNumber' => $r['sapNumber'] ?? null,
            'customer' => $r['customer'] ?? null,
            'productQuality' => $r['productQuality'] ?? null,
            'targetKg' => $r['targetKg'] ?? null,
            'unit' => $r['unit'] ?? 'kg',
            'bayLine' => $r['bayLine'] ?? null,
            'plannedTime' => $r['plannedTime'] ?? null,
            'recommended' => (bool) ($r['recommended'] ?? false),
        ];
    }
}
