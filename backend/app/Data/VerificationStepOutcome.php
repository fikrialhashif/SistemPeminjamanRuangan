<?php

namespace App\Data;

use App\Enums\VerificationStage;

readonly class VerificationStepOutcome
{
    public function __construct(
        public VerificationStage $stage,
        public bool $passed,
        public ?string $reason = null,
    ) {}

    public function toArray(): array
    {
        return [
            'stage' => $this->stage->value,
            'label' => $this->stage->label(),
            'passed' => $this->passed,
            'reason' => $this->reason,
        ];
    }
}
