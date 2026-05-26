<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Terminal configuration returned to the driver-facing login page (V3 §15.1
 * + §21.3 "GET terminal config").
 *
 * Drives the page header (facility / terminal name), language selector,
 * support phone and online/service mode states.
 */
class TerminalConfigResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'terminalId' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'terminalType' => $this->terminal_type,
            'facilityName' => $this->resource->site?->name ?? 'Tyczka Hydrogen (Schweinfurt)',
            'siteCode' => $this->resource->site?->code ?? null,

            'languageSupport' => is_array($this->language_support) && count($this->language_support) > 0
                ? $this->language_support
                : ['de'], // §22 German fallback when none configured
            'defaultLanguage' => is_array($this->language_support) && in_array('de', $this->language_support, true)
                ? 'de'
                : ($this->language_support[0] ?? 'de'),

            'status' => $this->status,                              // draft | active | service_mode | …
            'isActive' => (bool) $this->is_active,
            'terminalStatus' => $this->resolveTerminalStatus(),     // online | offline | service_mode | degraded
            'readerStatus' => 'ready',                               // hardware health not modelled yet — defaults to ready
            'demoChipScanEnabled' => (bool) config('terminal.demo_chip_scan', false),

            'supportPhone' => config('terminal.support_phone'),     // TBC per V3 §10.1
            'supportHelperText' => 'Need help with login or terminal access? Contact terminal support or the control room.',
        ];
    }

    protected function resolveTerminalStatus(): string
    {
        if ($this->status === 'service_mode') {
            return 'service_mode';
        }
        if (! $this->is_active || $this->status === 'inactive') {
            return 'offline';
        }
        if ($this->status === 'draft') {
            return 'degraded';
        }
        return 'online';
    }
}
