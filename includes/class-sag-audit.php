<?php
/**
 * Audit — pure classification of image alt-text quality.
 *
 * No WordPress calls and no I/O: everything here operates on plain strings so
 * it can be unit-tested in isolation. Data access (querying attachments) lives
 * in the REST controller; this class only judges the values it is handed.
 *
 * @package Smart_Alt_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class INSAG_Audit {

    /** Alt text longer than this many characters is flagged (WCAG guidance). */
    const MAX_ALT_LEN = 125;

    const FLAG_MISSING     = 'missing';
    const FLAG_EMPTY       = 'empty';
    const FLAG_TOO_LONG    = 'too_long';
    const FLAG_PLACEHOLDER = 'placeholder';
    const FLAG_DUPLICATE   = 'duplicate';

    /** All flag keys in display order. */
    public static function flags() {
        return array(
            self::FLAG_MISSING,
            self::FLAG_EMPTY,
            self::FLAG_DUPLICATE,
            self::FLAG_TOO_LONG,
            self::FLAG_PLACEHOLDER,
        );
    }

    /** Trim + lowercase (multibyte safe) for comparison. */
    public static function normalize( $alt ) {
        return mb_strtolower( trim( (string) $alt ) );
    }

    /**
     * Does this non-empty alt look like an auto/placeholder value rather than a
     * real description (the file name, or a camera/scanner/editor default)?
     *
     * @param string $alt      The alt text (already known to be non-empty).
     * @param string $filename The attachment's file base name (e.g. "IMG_1234.jpg").
     * @return bool
     */
    public static function is_placeholder( $alt, $filename ) {
        $norm = self::normalize( $alt );
        if ( '' === $norm ) {
            return false;
        }

        $file       = self::normalize( $filename );
        $file_noext = preg_replace( '/\.[a-z0-9]{1,5}$/', '', $file );
        if ( $norm === $file || $norm === $file_noext ) {
            return true;
        }

        $patterns = array(
            // A default-name keyword followed only by digits/dates/separators
            // (e.g. "img_1234", "screenshot 2024-01-01"), not a real sentence.
            '/^(img|dsc|dscn|image|photo|foto|screenshot|screen shot|scan|untitled|capture)[\s\d_\-.:]*$/',
            '/^\d+$/',
        );
        foreach ( $patterns as $re ) {
            if ( preg_match( $re, $norm ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Classify one image's alt against the non-duplicate rules.
     *
     * @param string|null $alt      Raw alt; null when the meta is absent (missing).
     * @param string      $filename Attachment file base name (for placeholder check).
     * @return string[] Flag constants (never 'duplicate'; that is set-level).
     */
    public static function classify( $alt, $filename ) {
        if ( null === $alt ) {
            return array( self::FLAG_MISSING );
        }
        if ( '' === trim( $alt ) ) {
            return array( self::FLAG_EMPTY );
        }

        $flags = array();
        if ( mb_strlen( $alt ) > self::MAX_ALT_LEN ) {
            $flags[] = self::FLAG_TOO_LONG;
        }
        if ( self::is_placeholder( $alt, $filename ) ) {
            $flags[] = self::FLAG_PLACEHOLDER;
        }
        return $flags;
    }

    /**
     * From a flat list of alt strings, return the set of normalized signatures
     * that occur 2+ times (empty alts ignored).
     *
     * @param string[] $alts
     * @return array<string,bool> signature => true
     */
    public static function find_duplicates( array $alts ) {
        $counts = array();
        foreach ( $alts as $alt ) {
            $sig = self::normalize( $alt );
            if ( '' === $sig ) {
                continue;
            }
            $counts[ $sig ] = isset( $counts[ $sig ] ) ? $counts[ $sig ] + 1 : 1;
        }
        $dupes = array();
        foreach ( $counts as $sig => $n ) {
            if ( $n >= 2 ) {
                $dupes[ $sig ] = true;
            }
        }
        return $dupes;
    }

    /**
     * Summarize one page of records: per-check counts, healthy count, and the
     * list of problem items (non-dismissed images that have at least one flag).
     *
     * @param array<int,array{id:int,alt:?string,filename:string,dismissed:bool}> $records
     * @param array<string,bool>                                                  $dupes
     * @return array{counts:array<string,int>,healthy:int,items:array<int,array{id:int,alt:string,flags:string[]}>}
     */
    public static function summarize( array $records, array $dupes ) {
        $counts  = array_fill_keys( self::flags(), 0 );
        $healthy = 0;
        $items   = array();

        foreach ( $records as $r ) {
            $alt   = $r['alt'];
            $flags = self::classify( $alt, $r['filename'] );

            if ( null !== $alt && '' !== trim( $alt ) && isset( $dupes[ self::normalize( $alt ) ] ) ) {
                $flags[] = self::FLAG_DUPLICATE;
            }

            if ( ! empty( $r['dismissed'] ) || empty( $flags ) ) {
                $healthy++;
                continue;
            }

            foreach ( $flags as $f ) {
                $counts[ $f ]++;
            }
            $items[] = array(
                'id'    => (int) $r['id'],
                'alt'   => null === $alt ? '' : $alt,
                'flags' => $flags,
            );
        }

        return array( 'counts' => $counts, 'healthy' => $healthy, 'items' => $items );
    }

    /** Health score 0-100 = percentage of healthy images over total. */
    public static function score( $healthy, $total ) {
        if ( $total <= 0 ) {
            return 100;
        }
        return (int) round( ( $healthy / $total ) * 100 );
    }
}
