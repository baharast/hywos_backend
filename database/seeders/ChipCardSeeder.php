<?php

namespace Database\Seeders;

use App\Enums\AuthMediumStatus;
use App\Enums\AuthMediumType;
use App\Enums\ChipCardAssignmentState;
use App\Enums\ChipCardType;
use App\Models\AuthMedium;
use App\Models\ChipCardAssignment;
use App\Models\Driver;
use App\Models\Trailer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ChipCardSeeder extends Seeder
{
    public function run(): void
    {
        $driver1 = Driver::where('driver_code', 'DRV-1001')->first();
        $driver2 = Driver::where('driver_code', 'DRV-1002')->first();
        $driver3 = Driver::where('driver_code', 'DRV-1003')->first();
        $trailer1 = Trailer::where('trailer_code', 'TR-1001')->first();

        $cards = [
            [
                'card_code' => 'CARD-001',
                'card_type' => ChipCardType::DRIVER_CARD,
                'medium_type' => AuthMediumType::CHIP_CARD,
                'masked_uid' => '•••• 4242',
                'serial_number' => 'SN-A001',
                'status' => AuthMediumStatus::ACTIVE,
                'expires_at' => now()->addYear(),
                'assign_to' => $driver1 ? ['type' => 'driver', 'id' => $driver1->id] : null,
            ],
            [
                'card_code' => 'CARD-002',
                'card_type' => ChipCardType::DRIVER_CARD,
                'medium_type' => AuthMediumType::CHIP_CARD,
                'masked_uid' => '•••• 1111',
                'serial_number' => 'SN-A002',
                'status' => AuthMediumStatus::ACTIVE,
                'expires_at' => now()->addYear(),
                'assign_to' => null,
            ],
            [
                'card_code' => 'CARD-003',
                'card_type' => ChipCardType::TRAILER_CHIP_TAG,
                'medium_type' => AuthMediumType::TRAILER_CHIP,
                'masked_uid' => '•••• 9999',
                'serial_number' => 'SN-T001',
                'status' => AuthMediumStatus::ACTIVE,
                'expires_at' => null,
                'assign_to' => $trailer1 ? ['type' => 'trailer', 'id' => $trailer1->id] : null,
            ],
            [
                'card_code' => 'CARD-004',
                'card_type' => ChipCardType::DRIVER_CARD,
                'medium_type' => AuthMediumType::CHIP_CARD,
                'masked_uid' => '•••• 2222',
                'serial_number' => 'SN-A003',
                'status' => AuthMediumStatus::BLOCKED,
                'revocation_reason' => 'Security review',
                'assign_to' => $driver2 ? ['type' => 'driver', 'id' => $driver2->id] : null,
            ],
            [
                'card_code' => 'CARD-005',
                'card_type' => ChipCardType::DRIVER_CARD,
                'medium_type' => AuthMediumType::CHIP_CARD,
                'masked_uid' => '•••• 3333',
                'serial_number' => 'SN-A004',
                'status' => AuthMediumStatus::LOST,
                'lost_at' => now()->subDays(2),
                'assign_to' => $driver3 ? ['type' => 'driver', 'id' => $driver3->id, 'unassign_after' => true] : null,
            ],
            [
                'card_code' => 'CARD-006',
                'card_type' => ChipCardType::DRIVER_CARD,
                'medium_type' => AuthMediumType::CHIP_CARD,
                'masked_uid' => '•••• 4444',
                'serial_number' => 'SN-A005',
                'status' => AuthMediumStatus::ACTIVE,
                'expires_at' => now()->addDays(20),
                'assign_to' => null,
            ],
        ];

        foreach ($cards as $row) {
            if ($row['card_type'] === ChipCardType::TRAILER_CHIP_TAG && ! $trailer1) {
                $this->command?->warn("Skipping {$row['card_code']} — no trailer available (TrailerSeeder not run?).");
                continue;
            }
            if ($row['card_type'] === ChipCardType::DRIVER_CARD && $row['assign_to']
                && ! Driver::find($row['assign_to']['id'])
            ) {
                $this->command?->warn("Skipping assignment for {$row['card_code']} — driver missing.");
                $row['assign_to'] = null;
            }

            $assignTo = $row['assign_to'] ?? null;
            $unassignAfter = isset($assignTo['unassign_after']) && $assignTo['unassign_after'];

            $assignmentState = $assignTo && ! $unassignAfter
                ? ChipCardAssignmentState::ASSIGNED
                : ChipCardAssignmentState::UNASSIGNED;

            $attrs = [
                'id' => (string) Str::uuid(),
                'medium_type' => $row['medium_type'],
                'card_code' => $row['card_code'],
                'card_type' => $row['card_type'],
                'serial_number' => $row['serial_number'] ?? null,
                'masked_uid' => $row['masked_uid'] ?? null,
                'status' => $row['status'],
                'assignment_state' => $assignmentState,
                'is_single_use' => false,
                'issued_at' => now()->subMonths(3),
                'expires_at' => $row['expires_at'] ?? null,
                'revocation_reason' => $row['revocation_reason'] ?? null,
                'lost_at' => $row['lost_at'] ?? null,
            ];

            if ($assignTo && ! $unassignAfter) {
                if ($assignTo['type'] === 'driver') {
                    $attrs['driver_id'] = $assignTo['id'];
                } else {
                    $attrs['trailer_id'] = $assignTo['id'];
                }
            }

            $card = AuthMedium::firstOrCreate(
                ['card_code' => $row['card_code']],
                $attrs
            );

            // Always seed an assignment-history row when we have a target — even if
            // the card was later marked lost and unassigned (we record both events).
            if ($assignTo) {
                $label = $assignTo['type'] === 'driver'
                    ? optional(Driver::find($assignTo['id']))->driver_code
                    : optional(Trailer::find($assignTo['id']))->trailer_code;

                ChipCardAssignment::firstOrCreate(
                    [
                        'auth_medium_id' => $card->id,
                        'action' => 'assigned',
                        'entity_type' => $assignTo['type'],
                        'entity_id' => $assignTo['id'],
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'entity_label' => $label,
                        'reason' => 'Seeded initial assignment',
                        'created_at' => now()->subMonths(2),
                    ]
                );

                if ($unassignAfter) {
                    ChipCardAssignment::firstOrCreate(
                        [
                            'auth_medium_id' => $card->id,
                            'action' => 'unassigned',
                            'entity_type' => $assignTo['type'],
                            'entity_id' => $assignTo['id'],
                        ],
                        [
                            'id' => (string) Str::uuid(),
                            'entity_label' => $label,
                            'reason' => 'Auto-unassigned after lost report',
                            'created_at' => now()->subDays(2),
                        ]
                    );
                }
            }
        }
    }
}
