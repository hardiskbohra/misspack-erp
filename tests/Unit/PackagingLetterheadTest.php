<?php

namespace Tests\Unit;

use App\Models\Organisation;
use App\Models\PackagingList;
use PHPUnit\Framework\TestCase;

/**
 * Whose letterhead does the sheet print on?
 *
 * A packaging list documents a packing run, and the party that packed issues
 * it. Our own export carries our logo; a list drawn from a vendor's bill is
 * that vendor's document and prints without it — a MissPack logo on Solo
 * Beauty's sheet would claim work we did not do.
 *
 * The decision is made from the sender name, which is what a sales invoice
 * already puts there, so no new field had to be invented. The Name is compared
 * with legal boilerplate ignored ("Pvt Ltd" against "Private Limited"), and a
 * list with no sender at all falls back to whether a vendor is linked.
 */
class PackagingLetterheadTest extends TestCase
{
    private function organisation(): Organisation
    {
        return new Organisation([
            'legal_name' => 'MissPack India Pvt Ltd',
            'trade_name' => 'MissPack',
        ]);
    }

    private function list(array $attributes = []): PackagingList
    {
        return new PackagingList($attributes);
    }

    public function test_our_own_sender_name_puts_the_sheet_on_our_letterhead(): void
    {
        $this->assertTrue($this->list(['from_name' => 'MissPack India Pvt Ltd'])->issuedByUs($this->organisation()));
        $this->assertTrue($this->list(['from_name' => 'MissPack'])->issuedByUs($this->organisation()));
    }

    public function test_legal_boilerplate_does_not_change_who_issued_the_sheet(): void
    {
        $this->assertTrue($this->list(['from_name' => 'MISSPACK INDIA PRIVATE LIMITED'])->issuedByUs($this->organisation()));
        $this->assertTrue($this->list(['from_name' => 'MissPack India Pvt. Ltd. (Export Division)'])->issuedByUs($this->organisation()));
    }

    public function test_a_vendors_sheet_never_carries_our_logo(): void
    {
        $this->assertFalse($this->list(['from_name' => 'Solo Beauty Packaging Co., Ltd'])->issuedByUs($this->organisation()));
        $this->assertFalse($this->list(['from_name' => 'Solo Beauty Packaging Co., Ltd', 'vendor_id' => 3])->issuedByUs($this->organisation()));
    }

    public function test_an_unnamed_sender_falls_back_to_the_linked_vendor(): void
    {
        $this->assertTrue($this->list(['from_name' => ''])->issuedByUs($this->organisation()));
        $this->assertFalse($this->list(['from_name' => '  ', 'vendor_id' => 9])->issuedByUs($this->organisation()));
    }

    public function test_names_are_compared_as_words_not_fragments(): void
    {
        /* "MissPackaging" is not MissPack. The space in the normalised name is
           what keeps a fragment from matching. */
        $this->assertFalse($this->list(['from_name' => 'MissPackaging Solutions'])->issuedByUs($this->organisation()));
    }
}
