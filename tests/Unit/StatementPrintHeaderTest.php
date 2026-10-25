<?php

namespace Tests\Unit;

use App\Services\StatementPrintHeader;
use PHPUnit\Framework\TestCase;

class StatementPrintHeaderTest extends TestCase
{
    public function test_long_issuer_street_is_balanced_into_two_lines_with_locality_third(): void
    {
        $issuer = ['seller_address'=>'E-410, 4th Floor, City Centre, Near Idgah Circle, Prem Darwaja Road, Idgah',
            'seller_city'=>'Ahmedabad','seller_state'=>'Gujarat','seller_pincode'=>'380016','seller_country'=>'India'];
        $this->assertSame(['E-410, 4th Floor, City Centre','Near Idgah Circle, Prem Darwaja Road, Idgah',
            'Ahmedabad, Gujarat 380016, India'], StatementPrintHeader::addressLines($issuer));
    }

    public function test_multiline_addresses_and_missing_fields_are_not_invented_or_truncated(): void
    {
        $this->assertSame(['Building 1','Industrial Park','Surat, Gujarat'],StatementPrintHeader::addressLines([
            'seller_address'=>"Building 1\nIndustrial Park",'seller_city'=>'Surat','seller_state'=>'Gujarat']));
        $this->assertSame(['Street','',''],StatementPrintHeader::addressLines(['seller_address'=>'Street']));
    }
}
