<?php
defined('ABSPATH') || exit;

class SIS_Validator {

    const SOLAR_GEN_MIN_GWH  = 500;
    const SOLAR_GEN_MAX_GWH  = 8000;
    const SOLAR_CAP_MIN_GW   = 15.0;   // Spanyol ~16 GW di awal 2022, naik ke 20+ GW akhir 2022
    const SOLAR_CAP_MAX_GW   = 100.0;
    const MAX_MOM_CHANGE_PCT = 80;

    public function validate_generation(array $data, int $year, int $month): void {
        $gwh = $data['gwh'] ?? null;

        if ($gwh === null || !is_numeric($gwh)) {
            throw new SIS_Validation_Exception("Generation value is null or non-numeric for {$year}-{$month}");
        }
        $gwh = (float) $gwh;

        if ($gwh < self::SOLAR_GEN_MIN_GWH || $gwh > self::SOLAR_GEN_MAX_GWH) {
            throw new SIS_Validation_Exception(
                "Generation {$gwh} GWh is outside expected range [" . self::SOLAR_GEN_MIN_GWH . ", " . self::SOLAR_GEN_MAX_GWH . "] for {$year}-{$month}"
            );
        }

        $prev = SIS_Database::get_generation(...SIS_Derived_Metrics::prev_month($year, $month));
        if ($prev) {
            $mom = abs(($gwh - (float)$prev->generation_gwh) / (float)$prev->generation_gwh * 100);
            if ($mom > self::MAX_MOM_CHANGE_PCT) {
                throw new SIS_Validation_Exception(
                    "MoM change {$mom}% exceeds threshold of " . self::MAX_MOM_CHANGE_PCT . "%. Verify data."
                );
            }
        }
    }

    public function validate_capacity(array $data, int $year, int $month): void {
        $gw = $data['gw'] ?? null;

        if ($gw === null || !is_numeric($gw)) {
            throw new SIS_Validation_Exception("Capacity value is null or non-numeric for {$year}-{$month}");
        }
        $gw = (float) $gw;

        if ($gw < self::SOLAR_CAP_MIN_GW || $gw > self::SOLAR_CAP_MAX_GW) {
            throw new SIS_Validation_Exception(
                "Capacity {$gw} GW is outside expected range [" . self::SOLAR_CAP_MIN_GW . ", " . self::SOLAR_CAP_MAX_GW . "]"
            );
        }

        $prev = SIS_Database::get_capacity(...SIS_Derived_Metrics::prev_month($year, $month));
        if ($prev && ($gw < (float)$prev->capacity_gw - 0.5)) {
            throw new SIS_Validation_Exception(
                "Capacity dropped by more than 0.5 GW ({$prev->capacity_gw} → {$gw}). Verify source data."
            );
        }
    }
}

class SIS_Validation_Exception extends RuntimeException {}
