<?php
namespace SAG\Tests;

final class AuditTest extends TestCase {

    public function test_missing_when_alt_is_null() {
        $this->assertSame( array( 'missing' ), \INSAG_Audit::classify( null, 'photo.jpg' ) );
    }

    public function test_empty_when_blank_or_whitespace() {
        $this->assertSame( array( 'empty' ), \INSAG_Audit::classify( '', 'photo.jpg' ) );
        $this->assertSame( array( 'empty' ), \INSAG_Audit::classify( '   ', 'photo.jpg' ) );
    }

    public function test_healthy_alt_has_no_flags() {
        $this->assertSame( array(), \INSAG_Audit::classify( 'A golden retriever running on a beach', 'dog.jpg' ) );
    }

    public function test_too_long_uses_multibyte_length() {
        $this->assertContains( 'too_long', \INSAG_Audit::classify( str_repeat( 'á', 126 ), 'x.jpg' ) );
        $this->assertNotContains( 'too_long', \INSAG_Audit::classify( str_repeat( 'á', 100 ), 'x.jpg' ) );
    }

    public function test_placeholder_matches_filename_and_camera_patterns() {
        $this->assertContains( 'placeholder', \INSAG_Audit::classify( 'IMG_1234', 'IMG_1234.jpg' ) );
        $this->assertContains( 'placeholder', \INSAG_Audit::classify( 'IMG_1234.jpg', 'IMG_1234.jpg' ) );
        foreach ( array( 'DSC0001', 'img_20240101', 'screenshot 2024-01-01', 'untitled', '12345' ) as $alt ) {
            $this->assertContains( 'placeholder', \INSAG_Audit::classify( $alt, 'whatever.png' ), $alt );
        }
    }

    public function test_real_description_is_not_placeholder() {
        $this->assertNotContains( 'placeholder', \INSAG_Audit::classify( 'A red bicycle leaning on a wall', 'IMG_1234.jpg' ) );
    }

    public function test_find_duplicates_flags_repeated_normalized_alts() {
        $dupes = \INSAG_Audit::find_duplicates( array( 'Sunset', 'sunset ', 'Unique', '', '  ' ) );
        $this->assertArrayHasKey( 'sunset', $dupes );
        $this->assertArrayNotHasKey( 'unique', $dupes );
        $this->assertArrayNotHasKey( '', $dupes );
    }

    public function test_summarize_counts_items_and_healthy() {
        $records = array(
            array( 'id' => 1, 'alt' => null, 'filename' => 'a.jpg', 'dismissed' => false ),               // missing
            array( 'id' => 2, 'alt' => 'A clear description here', 'filename' => 'b.jpg', 'dismissed' => false ), // healthy
            array( 'id' => 3, 'alt' => 'Sunset', 'filename' => 'c.jpg', 'dismissed' => false ),            // duplicate (via dupes)
            array( 'id' => 4, 'alt' => 'IMG_9', 'filename' => 'IMG_9.jpg', 'dismissed' => true ),          // placeholder but dismissed -> healthy
        );
        $out = \INSAG_Audit::summarize( $records, array( 'sunset' => true ) );
        $this->assertSame( 1, $out['counts']['missing'] );
        $this->assertSame( 1, $out['counts']['duplicate'] );
        $this->assertSame( 0, $out['counts']['placeholder'] ); // dismissed excluded
        $this->assertSame( 2, $out['healthy'] );               // id2 + dismissed id4
        $this->assertCount( 2, $out['items'] );                // id1 + id3
    }

    public function test_score_percentage() {
        $this->assertSame( 100, \INSAG_Audit::score( 0, 0 ) );
        $this->assertSame( 50, \INSAG_Audit::score( 5, 10 ) );
        $this->assertSame( 82, \INSAG_Audit::score( 82, 100 ) );
    }
}
