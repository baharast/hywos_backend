<?php

namespace App\Services\MasterDataExport\Exporters;

use App\Enums\ExportCategory;
use App\Enums\ExportStatusScope;
use App\Models\Customer;
use App\Models\ExportJob;
use Illuminate\Database\Eloquent\Builder;

class CustomersExporter extends AbstractExporter
{
    public function categorySlug(): string
    {
        return ExportCategory::CUSTOMERS;
    }

    public function defaultFields(): array
    {
        return [
            'code', 'name', 'legal_name', 'sap_customer_no',
            'city', 'country',
            'status', 'is_active', 'is_sap_owned',
            'created_at', 'updated_at',
        ];
    }

    public function allFields(): array
    {
        return array_merge($this->defaultFields(), [
            'external_reference', 'street', 'postal_code',
            'primary_contact_name', 'email', 'phone',
            'document_requirements', 'default_document_language',
            'block_reason', 'blocked_at', 'notes',
        ]);
    }

    public function rows(ExportJob $job, array $fields): \Generator
    {
        foreach ($this->query($job)->cursor() as $c) {
            yield $this->shape($c, $fields);
        }
    }

    public function estimateCount(ExportJob $job): ?int
    {
        return $this->query($job)->count();
    }

    protected function query(ExportJob $job): Builder
    {
        $query = Customer::query();

        if ($job->status_scope === ExportStatusScope::ACTIVE_CURRENT_ONLY) {
            $query->where('is_active', true)->where('status', 'active');
        }

        return $this->applyRecordScope($query, $job);
    }

    protected function shape(Customer $c, array $fields): array
    {
        $row = [];
        foreach ($fields as $f) {
            $row[$f] = match ($f) {
                'blocked_at' => $c->blocked_at?->toIso8601String(),
                'created_at' => $c->created_at?->toIso8601String(),
                'updated_at' => $c->updated_at?->toIso8601String(),
                'is_active' => $c->is_active ? '1' : '0',
                'is_sap_owned' => $c->is_sap_owned ? '1' : '0',
                'document_requirements' => is_array($c->document_requirements)
                    ? implode('|', $c->document_requirements)
                    : null,
                default => $c->{$f} ?? null,
            };
        }
        return $row;
    }
}
