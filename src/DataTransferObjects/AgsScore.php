<?php

namespace RefBytes\Lti\DataTransferObjects;

class AgsScore
{
    public const ACTIVITY_INITIALIZED = 'Initialized';

    public const ACTIVITY_STARTED = 'Started';

    public const ACTIVITY_IN_PROGRESS = 'InProgress';

    public const ACTIVITY_SUBMITTED = 'Submitted';

    public const ACTIVITY_COMPLETED = 'Completed';

    public const GRADING_FULLY_GRADED = 'FullyGraded';

    public const GRADING_PENDING = 'Pending';

    public const GRADING_PENDING_MANUAL = 'PendingManual';

    public const GRADING_NOT_READY = 'NotReady';

    public ?float $scoreGiven = null;

    public ?float $scoreMaximum = null;

    public ?string $comment = null;

    public string $activityProgress;

    public string $gradingProgress;

    public string $timestamp;

    public function __construct(
        public readonly string $userId,
    ) {
        $this->activityProgress = self::ACTIVITY_COMPLETED;
        $this->gradingProgress = self::GRADING_FULLY_GRADED;
        $this->timestamp = now()->toIso8601String();
    }

    public static function make(string $userId): self
    {
        return new self($userId);
    }

    public function scoreGiven(float $scoreGiven): self
    {
        $this->scoreGiven = $scoreGiven;

        return $this;
    }

    public function scoreMaximum(float $scoreMaximum): self
    {
        $this->scoreMaximum = $scoreMaximum;

        return $this;
    }

    public function activityProgress(string $activityProgress): self
    {
        $this->activityProgress = $activityProgress;

        return $this;
    }

    public function gradingProgress(string $gradingProgress): self
    {
        $this->gradingProgress = $gradingProgress;

        return $this;
    }

    public function comment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function timestamp(string $timestamp): self
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'userId' => $this->userId,
            'scoreGiven' => $this->scoreGiven,
            'scoreMaximum' => $this->scoreMaximum,
            'activityProgress' => $this->activityProgress,
            'gradingProgress' => $this->gradingProgress,
            'timestamp' => $this->timestamp,
            'comment' => $this->comment,
        ], fn ($v) => $v !== null);
    }
}
