<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Setting;
use App\Models\Subject;

/**
 * What a subject costs and how much of it is free. Prices are whole naira.
 * A subject's own price wins; otherwise the site default applies. A price of 0 makes the subject free for everyone.
 */
class Pricing
{
    public const DEFAULT_PRICE = 1000;
    public const DEFAULT_FREE_QUESTIONS = 30;
    public const DEFAULT_ACCESS_DAYS = 365;

    public static function defaultPrice(): int
    {
        return max(0, Setting::int('pricing.default_price', self::DEFAULT_PRICE));
    }

    /** Questions a student can try in each subject before paying. */
    public static function defaultFreeQuestions(): int
    {
        return max(0, Setting::int('pricing.free_questions', self::DEFAULT_FREE_QUESTIONS));
    }

    /** How long a purchase lasts. */
    public static function accessDays(): int
    {
        return max(1, Setting::int('pricing.access_days', self::DEFAULT_ACCESS_DAYS));
    }

    /** @param  object|null  $pivot  the exam_subject row (price, free_questions), or null when the pair is unknown */
    public static function price(?object $pivot): int
    {
        return $pivot && $pivot->price !== null ? (int) $pivot->price : self::defaultPrice();
    }

    public static function freeQuestions(?object $pivot): int
    {
        return $pivot && $pivot->free_questions !== null ? (int) $pivot->free_questions : self::defaultFreeQuestions();
    }

    /** The pivot row for an exam and subject, or null when the subject is not part of the exam. */
    public static function pivot(Exam|int $exam, Subject|int $subject): ?object
    {
        return \Illuminate\Support\Facades\DB::table('exam_subject')
            ->where('exam_id', $exam instanceof Exam ? $exam->id : $exam)
            ->where('subject_id', $subject instanceof Subject ? $subject->id : $subject)
            ->first();
    }

    public static function subjectPrice(Exam|int $exam, Subject|int $subject): int
    {
        return self::price(self::pivot($exam, $subject));
    }

    public static function isFreeSubject(Exam|int $exam, Subject|int $subject): bool
    {
        return self::subjectPrice($exam, $subject) === 0;
    }

    public static function naira(int $amount): string
    {
        return '₦' . number_format($amount);
    }
}
