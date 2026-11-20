<?php

namespace App\Enums;

/**
 * The direction a record link travels in.
 *
 * Every relationship is written once from the source and once from the target, with
 * the inverse applied on the mirror row. `related` is its own inverse.
 */
enum LinkRelationship: string
{
    case Related = 'related';
    case Supports = 'supports';
    case SupportedBy = 'supported_by';
    case Supersedes = 'supersedes';
    case SupersededBy = 'superseded_by';
    case EvidenceFor = 'evidence_for';
    case HasEvidence = 'has_evidence';

    public function label(): string
    {
        return match ($this) {
            self::Related => 'Related Record',
            self::Supports => 'Supports',
            self::SupportedBy => 'Supported By',
            self::Supersedes => 'Supersedes',
            self::SupersededBy => 'Superseded By',
            self::EvidenceFor => 'Evidence For',
            self::HasEvidence => 'Has Evidence',
        };
    }

    /**
     * The relationship stored on the mirror row, read from the other record's side.
     */
    public function inverse(): self
    {
        return match ($this) {
            self::Related => self::Related,
            self::Supports => self::SupportedBy,
            self::SupportedBy => self::Supports,
            self::Supersedes => self::SupersededBy,
            self::SupersededBy => self::Supersedes,
            self::EvidenceFor => self::HasEvidence,
            self::HasEvidence => self::EvidenceFor,
        };
    }

    /**
     * The relationships a user picks from. The inverses are never chosen directly:
     * they are written automatically on the other record.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Related->value => self::Related->label(),
            self::Supports->value => self::Supports->label(),
            self::Supersedes->value => self::Supersedes->label(),
            self::EvidenceFor->value => self::EvidenceFor->label(),
        ];
    }
}
