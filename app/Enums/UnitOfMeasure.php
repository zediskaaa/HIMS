<?php

namespace App\Enums;

use App\Models\InventoryItem;

enum UnitOfMeasure: string
{
    case Piece = 'piece';
    case Box = 'box';
    case Pack = 'pack';
    case Bottle = 'bottle';
    case Vial = 'vial';
    case Ampoule = 'ampoule';
    case Tube = 'tube';
    case Sachet = 'sachet';
    case Strip = 'strip';
    case Tablet = 'tablet';
    case Capsule = 'capsule';
    case Canister = 'canister';
    case Roll = 'roll';
    case Pair = 'pair';
    case Set = 'set';
    case Kit = 'kit';
    case Bag = 'bag';
    case Pouch = 'pouch';
    case Jar = 'jar';
    case Container = 'container';
    case Cartridge = 'cartridge';
    case Syringe = 'syringe';
    case Applicator = 'applicator';
    case Meter = 'meter';
    case Centimeter = 'centimeter';
    case Liter = 'liter';
    case Milliliter = 'milliliter';
    case Gram = 'gram';
    case Kilogram = 'kilogram';
    case Unit = 'unit';

    public function label(): string
    {
        return match ($this) {
            self::Piece => 'Piece (pc)',
            self::Box => 'Box (box)',
            self::Pack => 'Pack (pack)',
            self::Bottle => 'Bottle (bottle)',
            self::Vial => 'Vial (vial)',
            self::Ampoule => 'Ampoule (amp)',
            self::Tube => 'Tube (tube)',
            self::Sachet => 'Sachet (sachet)',
            self::Strip => 'Strip (strip)',
            self::Tablet => 'Tablet (tablet)',
            self::Capsule => 'Capsule (capsule)',
            self::Canister => 'Canister (canister)',
            self::Roll => 'Roll (roll)',
            self::Pair => 'Pair (pair)',
            self::Set => 'Set (set)',
            self::Kit => 'Kit (kit)',
            self::Bag => 'Bag (bag)',
            self::Pouch => 'Pouch (pouch)',
            self::Jar => 'Jar (jar)',
            self::Container => 'Container (container)',
            self::Cartridge => 'Cartridge (cartridge)',
            self::Syringe => 'Syringe (syringe)',
            self::Applicator => 'Applicator (applicator)',
            self::Meter => 'Meter (m)',
            self::Centimeter => 'Centimeter (cm)',
            self::Liter => 'Liter (L)',
            self::Milliliter => 'Milliliter (mL)',
            self::Gram => 'Gram (g)',
            self::Kilogram => 'Kilogram (kg)',
            self::Unit => 'Unit (unit)',
        };
    }

    public function abbreviation(): string
    {
        return match ($this) {
            self::Piece => 'pc',
            self::Box => 'box',
            self::Pack => 'pack',
            self::Bottle => 'bottle',
            self::Vial => 'vial',
            self::Ampoule => 'amp',
            self::Tube => 'tube',
            self::Sachet => 'sachet',
            self::Strip => 'strip',
            self::Tablet => 'tablet',
            self::Capsule => 'capsule',
            self::Canister => 'canister',
            self::Roll => 'roll',
            self::Pair => 'pair',
            self::Set => 'set',
            self::Kit => 'kit',
            self::Bag => 'bag',
            self::Pouch => 'pouch',
            self::Jar => 'jar',
            self::Container => 'container',
            self::Cartridge => 'cartridge',
            self::Syringe => 'syringe',
            self::Applicator => 'applicator',
            self::Meter => 'm',
            self::Centimeter => 'cm',
            self::Liter => 'L',
            self::Milliliter => 'mL',
            self::Gram => 'g',
            self::Kilogram => 'kg',
            self::Unit => 'unit',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $uom) => [$uom->value => $uom->label()])
            ->all();
    }

    /**
     * Returns standard options merged with any existing unstandardized units from
     * the database to safely support legacy records without silent data corruption.
     *
     * @param  iterable<string>|null  $existingUnits
     * @return array<string, string>
     */
    public static function optionsWithLegacy(?iterable $existingUnits = null): array
    {
        $options = self::options();

        if ($existingUnits === null) {
            try {
                $existingUnits = InventoryItem::query()
                    ->whereNotNull('unit')
                    ->where('unit', '!=', '')
                    ->distinct()
                    ->pluck('unit');
            } catch (\Throwable) {
                $existingUnits = [];
            }
        }

        foreach ($existingUnits as $existing) {
            $key = strtolower(trim((string) $existing));
            if ($key !== '' && ! isset($options[$key])) {
                $options[$existing] = ucfirst((string) $existing).' (Legacy)';
            }
        }

        return $options;
    }

    /**
     * Allowed values for validation rules including any legacy database values.
     *
     * @return array<int, string>
     */
    public static function allowedValuesWithLegacy(): array
    {
        return array_keys(self::optionsWithLegacy());
    }

    /**
     * Safe lookup that normalizes common synonyms, plurals, and abbreviations.
     */
    public static function tryFromNormalized(?string $value): ?self
    {
        if (blank($value)) {
            return null;
        }

        $clean = strtolower(trim($value));

        if ($case = self::tryFrom($clean)) {
            return $case;
        }

        return match ($clean) {
            'pc', 'pcs', 'piece', 'pieces', 'each' => self::Piece,
            'boxes' => self::Box,
            'packs', 'pk' => self::Pack,
            'bottles', 'btl' => self::Bottle,
            'vials' => self::Vial,
            'amp', 'amps', 'ampule', 'ampules' => self::Ampoule,
            'tubes' => self::Tube,
            'sachets' => self::Sachet,
            'strips' => self::Strip,
            'tablets', 'tab', 'tabs' => self::Tablet,
            'capsules', 'cap', 'caps' => self::Capsule,
            'canisters' => self::Canister,
            'rolls' => self::Roll,
            'pairs' => self::Pair,
            'sets' => self::Set,
            'kits' => self::Kit,
            'bags' => self::Bag,
            'pouches' => self::Pouch,
            'jars' => self::Jar,
            'containers' => self::Container,
            'cartridges' => self::Cartridge,
            'syringes', 'syr' => self::Syringe,
            'applicators' => self::Applicator,
            'm', 'meter', 'meters' => self::Meter,
            'cm', 'centimeter', 'centimeters' => self::Centimeter,
            'l', 'liter', 'liters' => self::Liter,
            'ml', 'milliliter', 'milliliters' => self::Milliliter,
            'g', 'gram', 'grams' => self::Gram,
            'kg', 'kilogram', 'kilograms' => self::Kilogram,
            'units' => self::Unit,
            default => null,
        };
    }

    public static function labelFor(?string $value): string
    {
        if (blank($value)) {
            return 'Unit (unit)';
        }

        $normalized = self::tryFromNormalized($value);
        if ($normalized) {
            return $normalized->label();
        }

        return ucfirst($value).' (Legacy)';
    }
}
