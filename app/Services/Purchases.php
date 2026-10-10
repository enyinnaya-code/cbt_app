<?php

namespace App\Services;

use App\Models\Entitlement;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;

/** What a student has unlocked and what they paid, for the website's My purchases page and for the app. */
class Purchases
{
    /** Show a "renew" reminder this many days before a subject ends. */
    public const REMIND_DAYS = 14;

    public function __construct(private User $user) {}

    /**
     * Every subject the student has ever unlocked, soonest to end first. The newest entitlement of each subject only, so a
     * renewal replaces the old one. Expired ones are included and marked.
     *
     * @return Collection<int, Entitlement>
     */
    public function unlocked(): Collection
    {
        return Entitlement::where('user_id', $this->user->id)->with(['exam:id,name,slug', 'subject:id,name,slug'])
            ->orderByDesc('expires_at')->get()
            ->unique(fn ($e) => $e->exam_id . '-' . $e->subject_id)
            ->sortBy('expires_at')->values();
    }

    public static function daysLeft(Entitlement $e): int
    {
        // Part of a day counts as a day: with 4 days and 20 hours left, "ends in 5 days" is the honest thing to say.
        return (int) ceil(now()->diffInSeconds($e->expires_at, false) / 86400);
    }

    public static function renewUrl(Entitlement $e): string
    {
        return route('checkout.show', ['exam' => $e->exam->slug, 'subjects' => [$e->subject->slug]]);
    }

    /** Unlocked subjects that end within REMIND_DAYS (and have not ended yet). @return Collection<int, Entitlement> */
    public function endingSoon(): Collection
    {
        return $this->unlocked()->filter(fn ($e) => $e->expires_at->isFuture() && $e->expires_at->lte(now()->addDays(self::REMIND_DAYS)))->values();
    }

    /** @return Collection<int, Order> */
    public function orders(int $limit = 50): Collection
    {
        return Order::where('user_id', $this->user->id)->with(['exam:id,name,slug', 'items.subject:id,name'])->latest('id')->limit($limit)->get();
    }

    /** The shape the app reads. Own data only. */
    public function toArray(): array
    {
        return [
            'remind_days' => self::REMIND_DAYS,
            'unlocked' => $this->unlocked()->map(fn ($e) => [
                'exam' => $e->exam->name, 'exam_slug' => $e->exam->slug, 'subject' => $e->subject->name, 'subject_slug' => $e->subject->slug,
                'expires_at' => $e->expires_at->toIso8601String(), 'days_left' => self::daysLeft($e), 'expired' => $e->expires_at->isPast(),
            ])->all(),
            'payments' => $this->orders()->map(fn (Order $o) => [
                'reference' => $o->reference, 'exam' => $o->exam->name, 'exam_slug' => $o->exam->slug,
                'subjects' => $o->is_bundle ? ['All subjects'] : $o->items->pluck('subject.name')->all(),
                'amount' => $o->amount, 'currency' => $o->currency, 'method' => $o->method, 'method_label' => $o->methodLabel(),
                'status' => $o->status, 'status_label' => $o->statusLabel(), 'created_at' => $o->created_at->toIso8601String(),
                'paid_at' => $o->paid_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
