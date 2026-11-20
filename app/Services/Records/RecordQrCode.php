<?php

namespace App\Services\Records;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Renders a record's QR code for its printed label.
 *
 * SVG rather than PNG, deliberately. A label is printed, and SVG is resolution
 * independent — it comes out crisp on a 203dpi label printer and a laser printer
 * alike, where a rasterised code would have to be regenerated per density. It also
 * means no image extension is required at all: AGENTS.md's nominated
 * simplesoftwareio/simple-qrcode hard-depends on ext-gd, which is not present
 * here, and Bacon writes SVG without a rasteriser. Intervention Image, the other
 * package AGENTS.md names, is for the same reason not needed.
 *
 * The code resolves to a route, never a raw disk path or a bare id, so scanning it
 * lands on a URL the existing record middleware already authorises.
 */
class RecordQrCode
{
    /**
     * Total rendered size in pixels, not a module size — Bacon divides this by the
     * module count plus the quiet zone to get each module's edge.
     *
     * 240px prints at roughly 25mm on a 203dpi label printer while staying well
     * inside a QR's comfortable range, so the smallest version Bacon picks still
     * yields scannable modules.
     */
private const SIZE = 240;

    /**
     * Quiet zone in modules. The spec requires four, and printers crop hard enough
     * that fewer than that produces codes some scanners will not read.
     */
    private const MARGIN = 4;

    /**
     * The SVG for a record label code.
     */
    public function svg(string $value, string $foreground = '#0f172a', string $background = '#ffffff'): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(
                self::SIZE,
                self::MARGIN,
                null,
                null,
                Fill::uniformColor(
                    new Rgb(...$this->channels($background)),
                    new Rgb(...$this->channels($foreground)),
                ),
            ),
            new SvgImageBackEnd,
        );

        // Inline SVG is written into the label document, so the print stylesheet
        // controls it. An <img> would have been simpler and would have needed either
        // a data URI or a second route for one small graphic.
        return (new Writer($renderer))->writeString($value);
    }

    /**
     * Bacon takes integer channels; the label CSS is written in hex.
     *
     * @return array{int, int, int}
     */
    private function channels(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return array_map(
            static fn (string $pair): int => (int) hexdec($pair),
            str_split(substr($hex, 0, 6), 2),
        );
    }
}