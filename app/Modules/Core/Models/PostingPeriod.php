<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Enums\PostingPeriodStatus;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class PostingPeriod extends Model
{
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'starts_on',
        'ends_on',
        'status',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'status' => PostingPeriodStatus::class,
            'closed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function statusEnum(): PostingPeriodStatus
    {
        $raw = $this->getRawOriginal('status');
        if (! is_string($raw)) {
            throw new LogicException('Persisted posting period status must be a string.');
        }

        return PostingPeriodStatus::tryFrom($raw)
            ?? throw new LogicException('Persisted posting period status is invalid.');
    }

    public function startsOnDate(): string
    {
        return $this->rawDate('starts_on');
    }

    public function endsOnDate(): string
    {
        return $this->rawDate('ends_on');
    }

    public function closedAtUtcIso(): ?string
    {
        $raw = $this->getRawOriginal('closed_at');
        if ($raw === null) {
            return null;
        }

        if ($raw instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($raw)
                ->setTimezone(new DateTimeZone('UTC'))
                ->format(DATE_ATOM);
        }

        if (! is_string($raw) || trim($raw) === '') {
            throw new LogicException('Persisted posting period closure time is invalid.');
        }

        return (new DateTimeImmutable($raw, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(DATE_ATOM);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    private function rawDate(string $attribute): string
    {
        $raw = $this->getRawOriginal($attribute);

        if ($raw instanceof DateTimeInterface) {
            return $raw->format('Y-m-d');
        }

        if (! is_string($raw) || strlen($raw) < 10) {
            throw new LogicException('Persisted posting period date is invalid.');
        }

        return substr($raw, 0, 10);
    }
}
