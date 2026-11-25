<?php

namespace App\Data;

use App\Enums\VerificationStage;

readonly class VerificationSession
{
    public function __construct(
        public string $subjectType,
        public int $subjectId,
        public array $steps,
    ) {}

    public function accepted(): bool
    {
        foreach ($this->steps as $outcome) {
            if (! $outcome instanceof VerificationStepOutcome || ! $outcome->passed) {
                return false;
            }
        }

        return count($this->steps) === count(VerificationStage::cases());
    }

    public function firstFailure(): ?VerificationStepOutcome
    {
        foreach ($this->steps as $outcome) {
            if ($outcome instanceof VerificationStepOutcome && ! $outcome->passed) {
                return $outcome;
            }
        }

        return null;
    }

    public function toArray(): array
    {
        return [
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'accepted' => $this->accepted(),
            'steps' => array_map(
                fn (VerificationStepOutcome $outcome) => $outcome->toArray(),
                array_values($this->steps),
            ),
        ];
    }
}
