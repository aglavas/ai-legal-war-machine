<?php

namespace Tests\Unit;

use App\Models\EoglasnaOsijekMonitoring;
use App\Repositories\EoglasnaOsijekMonitoringRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EoglasnaOsijekMonitoringRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function repo(): EoglasnaOsijekMonitoringRepository
    {
        return new class extends EoglasnaOsijekMonitoringRepository {
            public function _normalize(array $p): array { return $this->normalizeParticipants($p); }
            public function _parse(string $a): array { return $this->parseAddress($a); }
        };
    }

    public function test_normalize_participants_decodes_unicode_and_slashes(): void
    {
        $repo = $this->repo();
        $in = [[
            'participantType' => 'NATURAL_PERSON',
            'titles' => 'Punomo\u0107nik tu\u017eenika \/ Pravni zastupnik obrane',
            'address' => 'Ul. Radoslava Lopa\u0161i\u0107a 6, 10000 Zagreb, Hrvatska',
            'fullName' => 'Kre\u0161imir Kuli\u0107',
            'name' => 'Kre\u0161imir',
            'surname' => 'Kuli\u0107',
            'oib' => '08055780332',
        ]];
        $norm = $repo->_normalize($in);
        $this->assertSame('Punomoćnik tuženika / Pravni zastupnik obrane', $norm[0]['titles']);
        $this->assertSame('Ul. Radoslava Lopašića 6, 10000 Zagreb, Hrvatska', $norm[0]['address']);
        $this->assertSame('Krešimir Kulić', $norm[0]['fullName']);
    }

    public function test_parse_address_hr_format(): void
    {
        $repo = $this->repo();
        $parsed = $repo->_parse('Ivana Gundulića 123, 31000 Osijek, Hrvatska');
        $this->assertSame('Ivana Gundulića', $parsed['street']);
        $this->assertSame('123', $parsed['street_number']);
        $this->assertSame(123, $parsed['street_number_int']);
        $this->assertSame('31000', $parsed['zip']);
        $this->assertSame(31000, $parsed['zip_int']);
        $this->assertSame('Osijek', $parsed['city']);
        $this->assertSame('Hrvatska', $parsed['country']);
    }

    public function test_fill_participant_columns_for_natural_and_legal(): void
    {
        $repo = new EoglasnaOsijekMonitoringRepository();
        $payloadNat = [
            'uuid' => 'abc',
            'participants' => [[
                'participantType' => 'NATURAL_PERSON',
                'name' => 'Ana',
                'surname' => 'Marić',
                'oib' => '123-456-78901',
                'address' => 'Ulica 1 10, 10000 Zagreb, Hrvatska',
            ]],
        ];
        $modelNat = $repo->upsertFromApiPayload($payloadNat);
        $this->assertSame('Ana', $modelNat->name);
        $this->assertSame('Marić', $modelNat->last_name);
        $this->assertSame('12345678901', $modelNat->oib);
        $this->assertSame('Ulica 1', $modelNat->street);
        $this->assertSame(10, $modelNat->street_number);
        $this->assertSame('Zagreb', $modelNat->city);
        $this->assertSame(10000, $modelNat->zip);

        $payloadLeg = [
            'uuid' => 'def',
            'participants' => [[
                'participantType' => 'LEGAL_PERSON',
                'name' => 'ACME d.d.',
                'oib' => '00112233445',
                'address' => 'Trg 123 1A, 21000 Split, Hrvatska',
            ]],
        ];
        $modelLeg = $repo->upsertFromApiPayload($payloadLeg);
        $this->assertSame('ACME d.d.', $modelLeg->name);
        $this->assertNull($modelLeg->last_name);
        $this->assertSame('00112233445', $modelLeg->oib);
        $this->assertSame('Trg 123', $modelLeg->street);
        $this->assertSame(1, $modelLeg->street_number);
        $this->assertSame('Split', $modelLeg->city);
        $this->assertSame(21000, $modelLeg->zip);
    }
}

