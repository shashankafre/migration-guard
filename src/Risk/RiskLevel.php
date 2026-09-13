<?php

namespace MigrationSafe\Laravel\Risk;

enum RiskLevel: int
{
    case Low = 1;
    case Medium = 2;
    case High = 3;
    case Critical = 4;

    public static function fromName(string $level): self
    {
        return match (strtolower($level)) {
            'low' => self::Low,
            'medium' => self::Medium,
            'high' => self::High,
            'critical' => self::Critical,
            default => throw new \InvalidArgumentException("Unknown risk level [{$level}]."),
        };
    }

    public function label(): string
    {
        return strtoupper($this->name);
    }
}
