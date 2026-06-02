<?php

namespace Tests\Feature\Trailers;

use App\Enums\AuthMediumStatus;
use App\Enums\AuthMediumType;
use App\Enums\TrailerStatus;
use App\Http\Resources\TrailerResource;
use App\Models\AuthMedium;
use App\Models\Trailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the order trailer-assignment eligibility rule (2026-06-02):
 *
 *  - A trailer is assignable when it is ACTIVE, is_active=true and carries
 *    at least ONE active credential — a chip OR a TAN.
 *  - The picker lists EVERY trailer and disables the ineligible ones,
 *    tagging each with the credential it carries (chip / tan / both / none).
 *  - One source of truth (Trailer::assignment_block_reason / scopeAssignable)
 *    backs the resource badge, the ?assignable=true list filter and the
 *    assign/create guard — they must never disagree.
 */
class TrailerAssignmentEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function makeTrailer(array $overrides = []): Trailer
    {
        $this->seq++;

        return Trailer::create(array_merge([
            'trailer_code' => 'TR-TEST-' . str_pad((string) $this->seq, 4, '0', STR_PAD_LEFT),
            'plate' => 'P-' . $this->seq,
            'status' => TrailerStatus::ACTIVE,
            'is_active' => true,
        ], $overrides));
    }

    private function attachChip(Trailer $trailer, string $status = AuthMediumStatus::ACTIVE): AuthMedium
    {
        return AuthMedium::create([
            'id' => (string) Str::uuid(),
            'trailer_id' => $trailer->id,
            'medium_type' => AuthMediumType::CHIP_CARD,
            'display_identifier' => '•••• ' . substr($trailer->trailer_code, -4),
            'status' => $status,
            'is_single_use' => false,
            'issued_at' => now(),
        ]);
    }

    private function attachTan(Trailer $trailer, string $status = AuthMediumStatus::ACTIVE): AuthMedium
    {
        return AuthMedium::create([
            'id' => (string) Str::uuid(),
            'trailer_id' => $trailer->id,
            'medium_type' => AuthMediumType::TAN,
            'tan_reference' => (string) random_int(1000000, 9999999),
            'status' => $status,
            'is_single_use' => true,
            'issued_at' => now(),
        ]);
    }

    private function assignment(Trailer $trailer): array
    {
        return (new TrailerResource($trailer->fresh()))->toArray(request())['assignment'];
    }

    public function test_trailer_with_chip_is_assignable_and_tagged_chip(): void
    {
        $trailer = $this->makeTrailer();
        $this->attachChip($trailer);

        $this->assertTrue($trailer->fresh()->is_assignable);
        $this->assertNull($trailer->fresh()->assignment_block_reason);

        $a = $this->assignment($trailer);
        $this->assertTrue($a['assignable']);
        $this->assertNull($a['reason']);
        $this->assertSame('chip', $a['credential']['value']);
        $this->assertSame('Chip', $a['credential']['label']);
        $this->assertSame('info', $a['credential']['tone']);
        $this->assertTrue($a['credential']['hasChip']);
        $this->assertFalse($a['credential']['hasTan']);
    }

    public function test_trailer_with_only_tan_is_assignable_and_tagged_tan(): void
    {
        $trailer = $this->makeTrailer();
        $this->attachTan($trailer);

        $this->assertTrue($trailer->fresh()->is_assignable);

        $a = $this->assignment($trailer);
        $this->assertTrue($a['assignable']);
        $this->assertSame('tan', $a['credential']['value']);
        $this->assertSame('TAN', $a['credential']['label']);
        $this->assertFalse($a['credential']['hasChip']);
        $this->assertTrue($a['credential']['hasTan']);
    }

    public function test_trailer_with_chip_and_tan_is_tagged_both(): void
    {
        $trailer = $this->makeTrailer();
        $this->attachChip($trailer);
        $this->attachTan($trailer);

        $a = $this->assignment($trailer);
        $this->assertTrue($a['assignable']);
        $this->assertSame('both', $a['credential']['value']);
        $this->assertSame('Chip + TAN', $a['credential']['label']);
    }

    public function test_trailer_without_credential_is_disabled_with_no_credential_reason(): void
    {
        $trailer = $this->makeTrailer();

        $this->assertFalse($trailer->fresh()->is_assignable);
        $this->assertSame('TRAILER_NO_CREDENTIAL', $trailer->fresh()->assignment_block_reason);

        $a = $this->assignment($trailer);
        $this->assertFalse($a['assignable']);
        $this->assertSame('TRAILER_NO_CREDENTIAL', $a['reason']);
        $this->assertSame('No chip or TAN assigned', $a['reasonLabel']);
        $this->assertSame('none', $a['credential']['value']);
        $this->assertSame('No credential', $a['credential']['label']);
        $this->assertSame('warning', $a['credential']['tone']);
    }

    public function test_inactive_credential_does_not_count(): void
    {
        $trailer = $this->makeTrailer();
        $this->attachChip($trailer, AuthMediumStatus::BLOCKED);
        $this->attachTan($trailer, AuthMediumStatus::EXPIRED);

        $this->assertFalse($trailer->fresh()->is_assignable);
        $this->assertSame('TRAILER_NO_CREDENTIAL', $trailer->fresh()->assignment_block_reason);
        $this->assertSame('none', $this->assignment($trailer)['credential']['value']);
    }

    public function test_blocked_trailer_is_disabled_but_still_shows_its_chip(): void
    {
        $trailer = $this->makeTrailer(['status' => TrailerStatus::BLOCKED]);
        $this->attachChip($trailer);

        $this->assertFalse($trailer->fresh()->is_assignable);
        $this->assertSame('TRAILER_BLOCKED', $trailer->fresh()->assignment_block_reason);

        $a = $this->assignment($trailer);
        $this->assertFalse($a['assignable']);
        $this->assertSame('TRAILER_BLOCKED', $a['reason']);
        // Block status hides assignability, NOT the credential the trailer carries.
        $this->assertSame('chip', $a['credential']['value']);
        $this->assertTrue($a['credential']['hasChip']);
    }

    public function test_inactive_and_archived_trailers_are_disabled(): void
    {
        $inactive = $this->makeTrailer(['status' => TrailerStatus::INACTIVE, 'is_active' => false]);
        $this->attachChip($inactive);
        $archived = $this->makeTrailer(['status' => TrailerStatus::ARCHIVED, 'is_active' => false]);
        $this->attachTan($archived);

        foreach ([$inactive, $archived] as $trailer) {
            $this->assertFalse($trailer->fresh()->is_assignable);
            $this->assertSame('TRAILER_INACTIVE', $trailer->fresh()->assignment_block_reason);
            $this->assertFalse($this->assignment($trailer)['assignable']);
        }
    }

    public function test_assignable_scope_returns_only_eligible_trailers(): void
    {
        $chip = $this->makeTrailer();
        $this->attachChip($chip);
        $tan = $this->makeTrailer();
        $this->attachTan($tan);
        $none = $this->makeTrailer();
        $blocked = $this->makeTrailer(['status' => TrailerStatus::BLOCKED]);
        $this->attachChip($blocked);
        $inactive = $this->makeTrailer(['status' => TrailerStatus::ARCHIVED, 'is_active' => false]);
        $this->attachChip($inactive);

        $assignableIds = Trailer::query()->assignable()->pluck('id')->all();

        $this->assertContains($chip->id, $assignableIds);
        $this->assertContains($tan->id, $assignableIds);
        $this->assertNotContains($none->id, $assignableIds);
        $this->assertNotContains($blocked->id, $assignableIds);
        $this->assertNotContains($inactive->id, $assignableIds);
        $this->assertCount(2, $assignableIds);
    }
}
