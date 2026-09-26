<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    public const PENDING = 'pending';
    public const AWAITING = 'awaiting_confirmation';
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const REJECTED = 'rejected';
    public const CANCELLED = 'cancelled';

    public const PAYSTACK = 'paystack';
    public const BANK = 'bank_transfer';

    protected $fillable = [
        'user_id', 'reference', 'method', 'status', 'exam_id', 'is_bundle', 'amount', 'currency', 'access_days',
        'payer_name', 'proof_path', 'note', 'decision_note', 'gateway_data', 'paid_at', 'submitted_at', 'decided_by',
    ];

    protected function casts(): array
    {
        return ['is_bundle' => 'boolean', 'gateway_data' => 'array', 'paid_at' => 'datetime', 'submitted_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }

    /** Still waiting for the student to pay, or for an admin to check a transfer. */
    public function isOpen(): bool
    {
        return in_array($this->status, [self::PENDING, self::AWAITING], true);
    }

    public function statusLabel(): string
    {
        return [
            self::PENDING => 'Waiting for payment', self::AWAITING => 'Checking your transfer', self::PAID => 'Paid',
            self::FAILED => 'Payment failed', self::REJECTED => 'Not confirmed', self::CANCELLED => 'Cancelled',
        ][$this->status] ?? Str::headline($this->status);
    }

    public function statusBadge(): string
    {
        return [self::PAID => 'g', self::FAILED => 'r', self::REJECTED => 'r', self::CANCELLED => 'neutral'][$this->status] ?? '';
    }

    public function methodLabel(): string
    {
        return $this->method === self::BANK ? 'Bank transfer' : 'Card or online';
    }

    /** A short code such as TCB-7K2M9QXA4T. Shown to the student and used as the bank transfer narration. */
    public static function newReference(): string
    {
        do {
            $reference = 'TCB-' . strtoupper(Str::random(10));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }
}
