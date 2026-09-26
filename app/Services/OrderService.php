<?php

namespace App\Services;

use App\Models\Entitlement;
use App\Models\Exam;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns "I want these subjects" into a priced order, and a paid order into access.
 * Every price is read from the database here; nothing the browser sends is trusted for money.
 */
class OrderService
{
    /** A purchase can be renewed once it has this many days or fewer left. */
    public const RENEW_WITHIN_DAYS = 30;

    public function __construct(private QuestionSelector $selector) {}

    /**
     * The subjects of an exam that can be bought, with what this student already has.
     * A subject is sold only when it has a price above 0 and at least one question.
     *
     * @return Collection<int, object{id:int,slug:string,name:string,code:string,price:int,free:int,total:int,expires:?\Illuminate\Support\Carbon,buyable:bool,renewal:bool}>
     */
    public function catalog(User $user, Exam $exam): Collection
    {
        $access = Access::for($user);
        $available = $this->selector->availability($exam);

        return $exam->subjects()->where('subjects.is_active', true)->orderBy('subjects.name')->get()
            ->map(function ($s) use ($access, $exam, $available) {
                $expires = $access->expiresAt($exam->id, $s->id);
                $price = $access->price($exam->id, $s->id);
                $renewal = $expires !== null && $expires->lte(now()->addDays(self::RENEW_WITHIN_DAYS));

                return (object) [
                    'id' => $s->id, 'slug' => $s->slug, 'code' => $s->code,
                    'name' => $s->pivot->display_name ?: $s->name,
                    'price' => $price,
                    'free' => $access->freeLimit($exam->id, $s->id),
                    'total' => (int) ($available[$s->id] ?? 0),
                    'expires' => $expires,
                    'renewal' => $renewal,
                    'buyable' => $price > 0 && (int) ($available[$s->id] ?? 0) > 0 && ($expires === null || $renewal),
                ];
            })
            ->filter(fn ($s) => $s->price > 0 && $s->total > 0)->values();
    }

    /**
     * Prices a basket. A bundle takes every subject at the exam's bundle price.
     *
     * @param  array<int,string>  $slugs
     * @return array{subjects:Collection,amount:int,is_bundle:bool}
     * @throws ValidationException
     */
    public function quote(User $user, Exam $exam, array $slugs, bool $bundle = false): array
    {
        $catalog = $this->catalog($user, $exam);
        $fail = fn (string $message) => throw ValidationException::withMessages(['subjects' => $message]);

        if ($bundle) {
            if ($exam->bundle_price === null || $exam->bundle_price <= 0) { $fail('That exam has no bundle price.'); }
            if ($catalog->isEmpty()) { $fail('There is nothing to buy for this exam yet.'); }

            return ['subjects' => $catalog, 'amount' => (int) $exam->bundle_price, 'is_bundle' => true];
        }

        $slugs = array_values(array_unique($slugs));
        if (! $slugs) { $fail('Choose at least one subject.'); }

        $chosen = $catalog->whereIn('slug', $slugs)->values();
        if ($chosen->count() !== count($slugs)) { $fail('One of those subjects cannot be bought.'); }

        $owned = $chosen->first(fn ($s) => ! $s->buyable);
        if ($owned) { $fail("You already have {$owned->name}. You can renew it when it has {$this->renewWindow()} days or less left."); }

        return ['subjects' => $chosen, 'amount' => (int) $chosen->sum('price'), 'is_bundle' => false];
    }

    public function create(User $user, Exam $exam, array $slugs, bool $bundle, string $method): Order
    {
        $quote = $this->quote($user, $exam, $slugs, $bundle);

        return DB::transaction(function () use ($user, $exam, $quote, $method) {
            $order = Order::create([
                'user_id' => $user->id, 'reference' => Order::newReference(), 'method' => $method, 'status' => Order::PENDING,
                'exam_id' => $exam->id, 'is_bundle' => $quote['is_bundle'], 'amount' => $quote['amount'], 'currency' => 'NGN',
                'access_days' => Pricing::accessDays(),
            ]);

            $order->items()->createMany($quote['subjects']->map(fn ($s) => ['subject_id' => $s->id, 'list_price' => $s->price])->all());

            return $order;
        });
    }

    /**
     * Marks an order paid and opens the subjects. Safe to call twice (Paystack sends both a redirect and a webhook).
     * Buying again while a purchase is still running adds the time on to the end of it.
     */
    public function markPaid(Order $order, ?User $decidedBy = null, ?array $gatewayData = null): Order
    {
        return DB::transaction(function () use ($order, $decidedBy, $gatewayData) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->isPaid()) { return $order; }

            foreach ($order->items as $item) {
                $entitlement = Entitlement::firstOrNew(['user_id' => $order->user_id, 'exam_id' => $order->exam_id, 'subject_id' => $item->subject_id]);
                $from = $entitlement->exists && $entitlement->expires_at->isFuture() ? $entitlement->expires_at : now();

                $entitlement->fill(['order_id' => $order->id, 'expires_at' => $from->copy()->addDays($order->access_days)])->save();
            }

            $order->update([
                'status' => Order::PAID, 'paid_at' => now(),
                'decided_by' => $decidedBy?->id ?? $order->decided_by,
                'gateway_data' => $gatewayData ?? $order->gateway_data,
            ]);

            return $order;
        });
    }

    private function renewWindow(): int
    {
        return self::RENEW_WITHIN_DAYS;
    }
}
